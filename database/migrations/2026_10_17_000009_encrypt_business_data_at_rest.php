<?php

use App\Support\DataCipher;
use App\Support\EncryptedColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encryption at rest: ciphertext is longer than the text it hides, so the
 * columns become MEDIUMTEXT; unique indexes on encrypted columns go (checked in
 * PHP instead); then every existing value is encrypted in place. Rows that are
 * already encrypted are left alone, so this is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (EncryptedColumns::DROPPED_UNIQUE as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                // The foreign key on the leading *_id column relied on this index; give it its own first.
                if (str_ends_with($columns[0], '_id')) {
                    $t->index($columns[0]);
                }

                $t->dropUnique($columns);
            });
        }

        foreach (EncryptedColumns::MAP as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach (array_keys($columns) as $column) {
                    $t->mediumText($column)->nullable()->change();
                }
            });
        }

        $cipher = app(DataCipher::class);

        foreach (EncryptedColumns::MAP as $table => $columns) {
            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $cipher) {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach (array_keys($columns) as $column) {
                        $value = $row->{$column};

                        if ($value !== null && $value !== '' && ! $cipher->isEncrypted($value)) {
                            $changes[$column] = $cipher->encryptString((string) $value);
                        }
                    }

                    if ($changes) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Decrypting back to plain text is deliberately not automatic.
        throw new RuntimeException('Encryption at rest is not reversed by rolling back.');
    }
};
