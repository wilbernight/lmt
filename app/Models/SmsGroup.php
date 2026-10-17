<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A named set of colleagues to send one SMS to (site-admin feature). */
class SmsGroup extends Model
{
    public const COLORS = ['slate', 'rose', 'orange', 'amber', 'emerald', 'teal', 'sky', 'indigo', 'violet', 'pink'];

    protected $fillable = ['name', 'color'];

    protected function casts(): array
    {
        return ['name' => 'encrypted'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
