<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /** Escapes LIKE wildcards so user input is matched literally. */
    protected function like(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }
}
