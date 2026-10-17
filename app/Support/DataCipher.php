<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Encryption at rest with AES-256-GCM (authenticated: tampered data fails to
 * decrypt instead of decrypting to garbage), keyed by DATA_ENCRYPTION_KEY.
 *
 * Text columns go through Laravel's Encrypter (wired into Eloquent's
 * "encrypted" casts in AppServiceProvider). Files use a compact binary format:
 *   "WNE1" | key-id (1 byte) | 12-byte nonce | 16-byte tag | ciphertext
 */
class DataCipher
{
    private const FILE_MAGIC = 'WNE1';

    private Encrypter $encrypter;

    /** @var array<int, string> raw keys; index 0 is the current key */
    private array $keys;

    public function __construct(string $key, array $previousKeys = [], private string $cipher = 'aes-256-gcm')
    {
        $this->keys = array_map(fn ($k) => $this->raw($k), [$key, ...$previousKeys]);
        $this->encrypter = new Encrypter($this->keys[0], $cipher);
        $this->encrypter->previousKeys(array_slice($this->keys, 1));
    }

    public static function fromConfig(): self
    {
        $key = config('daftar.data_key');

        if (blank($key)) {
            throw new RuntimeException('DATA_ENCRYPTION_KEY is not set. Business data cannot be encrypted or read without it.');
        }

        return new self($key, config('daftar.data_previous_keys', []), config('daftar.data_cipher', 'aes-256-gcm'));
    }

    public function encrypter(): Encrypter
    {
        return $this->encrypter;
    }

    public function encryptString(string $value): string
    {
        return $this->encrypter->encryptString($value);
    }

    public function decryptString(string $value): string
    {
        return $this->encrypter->decryptString($value);
    }

    /** True when the value is ciphertext this app can open (with the current or a previous key). */
    public function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '' || ! str_starts_with($value, 'eyJ')) {
            return false;
        }

        try {
            $this->encrypter->decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ---- Files -------------------------------------------------------------

    public function encryptFile(string $plain): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->keys[0], OPENSSL_RAW_DATA, $nonce, $tag, self::FILE_MAGIC, 16);

        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt the file.');
        }

        return self::FILE_MAGIC."\x00".$nonce.$tag.$cipher;
    }

    public function isEncryptedFile(string $bytes): bool
    {
        return str_starts_with($bytes, self::FILE_MAGIC);
    }

    /** Decrypts an encrypted file; files stored before encryption existed are returned as they are. */
    public function decryptFile(string $bytes): string
    {
        if (! $this->isEncryptedFile($bytes)) {
            return $bytes;
        }

        $nonce = substr($bytes, 5, 12);
        $tag = substr($bytes, 17, 16);
        $cipher = substr($bytes, 33);

        // Try the current key, then previous ones (after a rotation).
        foreach ($this->keys as $key) {
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::FILE_MAGIC);

            if ($plain !== false) {
                return $plain;
            }
        }

        throw new RuntimeException('The file could not be decrypted (wrong key or tampered data).');
    }

    private function raw(string $key): string
    {
        $raw = Str::startsWith($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        if (! is_string($raw) || strlen($raw) !== 32) {
            throw new RuntimeException('Data encryption keys must be 32 bytes (base64:...).');
        }

        return $raw;
    }
}
