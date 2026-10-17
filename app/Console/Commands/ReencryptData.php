<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\User;
use App\Support\DataCipher;
use App\Support\EncryptedColumns;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Brings everything at rest under the current DATA_ENCRYPTION_KEY: database
 * columns (plain or encrypted with a previous key) and uploaded files / avatars
 * (plain or encrypted). Run it after rotating the key, then drop the old key
 * from DATA_ENCRYPTION_PREVIOUS_KEYS.
 */
class ReencryptData extends Command
{
    protected $signature = 'app:reencrypt {--files-only : Only the files on disk}';

    protected $description = 'Encrypt or re-encrypt all business data and files with the current data key';

    public function handle(DataCipher $cipher): int
    {
        if (! $this->option('files-only')) {
            foreach (EncryptedColumns::MAP as $table => $columns) {
                $count = 0;

                DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $cipher, &$count) {
                    foreach ($rows as $row) {
                        $changes = [];

                        foreach (array_keys($columns) as $column) {
                            $value = $row->{$column};

                            if ($value === null || $value === '') {
                                continue;
                            }

                            // Plain text, or ciphertext under any known key, back under the current key.
                            $plain = $cipher->isEncrypted($value) ? $cipher->decryptString($value) : $value;
                            $changes[$column] = $cipher->encryptString($plain);
                        }

                        if ($changes) {
                            DB::table($table)->where('id', $row->id)->update($changes);
                            $count++;
                        }
                    }
                });

                $this->line(sprintf('  %-24s %d rows', $table, $count));
            }
        }

        $files = 0;
        $disk = Storage::disk('local');
        $paths = Attachment::pluck('path')->merge(User::whereNotNull('avatar_path')->pluck('avatar_path'));

        foreach ($paths as $path) {
            if (! $disk->exists($path)) {
                continue;
            }

            $disk->put($path, $cipher->encryptFile($cipher->decryptFile((string) $disk->get($path))));
            $files++;
        }

        $this->info("Done. {$files} files encrypted with the current key.");

        return self::SUCCESS;
    }
}
