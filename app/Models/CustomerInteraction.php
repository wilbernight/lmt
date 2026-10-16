<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    use HasUlids;

    public const TYPES = ['call' => 'تماس', 'meeting' => 'جلسه', 'email' => 'ایمیل', 'note' => 'یادداشت'];

    protected $fillable = ['type', 'body', 'happened_at'];

    protected function casts(): array
    {
        return ['happened_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
