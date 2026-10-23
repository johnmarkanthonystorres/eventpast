<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    public const TYPE_WEEK = '7d';
    public const TYPE_DAY = '1d';
    public const TYPE_HOURS = '2h';

    /**
     * Reminder types with the number of minutes before the event start at
     * which each one becomes due. Ordered largest window first.
     */
    public const WINDOWS = [
        self::TYPE_WEEK => 7 * 24 * 60,
        self::TYPE_DAY => 24 * 60,
        self::TYPE_HOURS => 2 * 60,
    ];

    protected $fillable = [
        'registration_id',
        'type',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
