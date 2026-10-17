<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLabels;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToWorkspace, HasAttachments, HasLabels, HasUlids, SoftDeletes;

    public const STAGES = [
        'lead' => 'سرنخ',
        'negotiation' => 'در حال مذاکره',
        'customer' => 'مشتری',
        'lost' => 'از دست رفته',
    ];

    protected $fillable = [
        'kind', 'name', 'company', 'job_title', 'phone', 'mobile', 'email', 'website', 'city', 'address',
        'stage', 'deal_value', 'notes',
    ];

    protected function casts(): array
    {
        // Everything about a customer is encrypted at rest; only the stage stays readable for filtering.
        return collect(['name', 'company', 'job_title', 'phone', 'mobile', 'email', 'website', 'city', 'address', 'notes', 'deal_value'])
            ->mapWithKeys(fn ($column) => [$column => 'encrypted'])
            ->all();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->latest('happened_at');
    }
}
