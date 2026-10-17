<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    public const STATUSES = [
        'sent' => 'ارسال شد',
        'failed' => 'ناموفق',
        'limited' => 'سقف روزانه',
        'invalid' => 'شماره نامعتبر',
        'logged' => 'فقط ثبت (ارسال خاموش)',
    ];

    protected $fillable = ['phone', 'body', 'status', 'error', 'duration_ms'];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
