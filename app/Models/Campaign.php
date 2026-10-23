<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'channel',
        'audience',
        'scheduled_at',
        'stats',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'stats' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
