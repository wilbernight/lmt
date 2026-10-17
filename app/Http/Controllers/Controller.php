<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Morilog\Jalali\CalendarUtils;

abstract class Controller
{
    use AuthorizesRequests;

    /** Escapes LIKE wildcards so user input is matched literally (plain-text columns only). */
    protected function like(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }

    /**
     * Search over encrypted columns: the database only holds ciphertext, so the
     * candidates (already narrowed by the query's other conditions) are
     * decrypted here and matched in PHP. Returns the matching primary keys.
     *
     * @param  array<int, string>  $fields
     */
    protected function matchingIds(Builder $query, array $fields, string $term, int $limit = 5000): array
    {
        $needle = $this->normaliseSearch($term);

        if ($needle === '') {
            return [];
        }

        $key = $query->getModel()->getKeyName();
        $table = $query->getModel()->getTable();

        return $query->clone()
            ->setEagerLoads([])
            ->select(array_map(fn ($c) => "{$table}.{$c}", [$key, ...$fields]))
            ->reorder()
            ->limit($limit)
            ->get()
            ->filter(fn ($model) => collect($fields)->contains(
                fn ($field) => str_contains($this->normaliseSearch((string) $model->getAttribute($field)), $needle)
            ))
            ->pluck($key)
            ->all();
    }

    /** Lower-case, Latin digits, unified Persian/Arabic letters: so "۰۹۱۲" finds "0912" and "ي" finds "ی". */
    protected function normaliseSearch(string $text): string
    {
        $text = CalendarUtils::convertNumbers(trim($text), true);
        $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', '‌' => ' ']);

        return mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
