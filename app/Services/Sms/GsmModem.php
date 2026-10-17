<?php

namespace App\Services\Sms;

use RuntimeException;

/**
 * A GSM modem (SIM800) reached over a TCP-to-serial bridge, speaking raw AT
 * commands. Latin text goes as the GSM 7-bit alphabet; anything else (Persian)
 * as UCS-2 hex, the only way a SIM800 carries non-Latin script.
 *
 * The bridge serves one client at a time, so every session opens, does one
 * job and always closes the socket. Adapted from the WilberNight panel, which
 * drives the same modem.
 */
class GsmModem
{
    public function __construct(
        protected string $host,
        protected int $port,
        protected float $timeout = 8.0,
    ) {}

    public function send(string $number, string $text): void
    {
        // The number is written inside an AT command: anything but digits could
        // close the command and start another one.
        if (! preg_match('/^\+?\d{3,20}\z/', $number)) {
            throw new RuntimeException('Refusing to send to a malformed number.');
        }

        $this->session(function ($socket) use ($number, $text) {
            $this->expectOk($socket, 'AT+CMGF=1');

            if ($this->isGsmAlphabet($text)) {
                $this->expectOk($socket, 'AT+CSCS="GSM"');
                $this->expectOk($socket, 'AT+CSMP=17,167,0,0');
                $body = $text;
            } else {
                $this->expectOk($socket, 'AT+CSCS="UCS2"');
                // DCS 8 = UCS-2; 167 = 24h validity.
                $this->expectOk($socket, 'AT+CSMP=17,167,0,8');
                $number = $this->hex(mb_convert_encoding($number, 'UCS-2BE', 'UTF-8'));
                $body = $this->hex(mb_convert_encoding($text, 'UCS-2BE', 'UTF-8'));
            }

            $this->write($socket, 'AT+CMGS="'.$number.'"');

            if (! str_contains($this->read($socket, '>', 5), '>')) {
                throw new RuntimeException('Modem did not open the message prompt.');
            }

            $this->write($socket, $body."\x1A", false);
            $answer = $this->read($socket, ['OK', 'ERROR'], 40);

            if (! str_contains($answer, '+CMGS') && ! str_contains($answer, 'OK')) {
                throw new RuntimeException('Modem refused the message: '.trim($answer));
            }
        });
    }

    /** Model, signal (0–31), network registration and operator, for the settings test. */
    public function status(): array
    {
        return $this->session(function ($socket) {
            $model = trim(str_replace(['OK', 'ATI'], '', $this->query($socket, 'ATI')));
            preg_match('/\+CSQ:\s*(\d+)/', $this->query($socket, 'AT+CSQ'), $csq);
            preg_match('/\+CREG:\s*\d+,(\d+)/', $this->query($socket, 'AT+CREG?'), $creg);
            preg_match('/\+COPS:\s*\d+,\d+,"([^"]*)"/', $this->query($socket, 'AT+COPS?'), $cops);

            return [
                'model' => $model,
                'signal' => isset($csq[1]) && (int) $csq[1] !== 99 ? (int) $csq[1] : null,
                'registered' => isset($creg[1]) && in_array((int) $creg[1], [1, 5], true),
                'operator' => $cops[1] ?? null,
            ];
        });
    }

    /**
     * @param  callable(resource): mixed  $work
     */
    protected function session(callable $work): mixed
    {
        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout);

        if (! $socket) {
            throw new RuntimeException("GSM modem unreachable at {$this->host}:{$this->port} ({$errstr})");
        }

        try {
            // Non-blocking: every read polls against its own deadline, so a silent
            // modem costs exactly that deadline and never stalls the request.
            stream_set_blocking($socket, false);
            while (($chunk = fread($socket, 1024)) !== false && $chunk !== '') {
            }

            $this->expectOk($socket, 'AT');

            return $work($socket);
        } finally {
            fclose($socket);
        }
    }

    protected function write($socket, string $command, bool $cr = true): void
    {
        $data = $command.($cr ? "\r" : '');
        $deadline = microtime(true) + $this->timeout;

        while ($data !== '' && microtime(true) < $deadline) {
            $written = fwrite($socket, $data);

            if ($written === false) {
                throw new RuntimeException('Could not write to the GSM modem.');
            }

            $data = (string) substr($data, $written);

            if ($data !== '') {
                usleep(20_000);
            }
        }
    }

    protected function read($socket, string|array $until, float $seconds): string
    {
        $until = (array) $until;
        $buffer = '';
        $deadline = microtime(true) + $seconds;

        while (microtime(true) < $deadline) {
            $chunk = fread($socket, 1024);

            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;

                foreach ($until as $token) {
                    if (str_contains($buffer, $token)) {
                        return $buffer;
                    }
                }
            } else {
                usleep(50_000);
            }
        }

        return $buffer;
    }

    protected function query($socket, string $command): string
    {
        $this->write($socket, $command);

        return $this->read($socket, ['OK', 'ERROR'], 5);
    }

    protected function expectOk($socket, string $command): void
    {
        $answer = $this->query($socket, $command);

        if (! str_contains($answer, 'OK')) {
            throw new RuntimeException("Modem answered {$command} with: ".trim($answer));
        }
    }

    protected function hex(string $bytes): string
    {
        return strtoupper(bin2hex($bytes));
    }

    /** Only characters the GSM 7-bit default alphabet can carry. */
    public static function isGsmAlphabet(string $text): bool
    {
        return (bool) preg_match('/^[\x20-\x7E\r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉÄÖÑÜ§¿äöñüà]*\z/u', $text);
    }
}
