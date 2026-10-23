<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Registration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'attendee_id',
        'ticket_type_id',
        'order_id',
        'status',
        'qr_token',
        'source',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($registration) {
            if (empty($registration->qr_token)) {
                $registration->qr_token = Str::random(64);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }

    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class);
    }

    public function isCheckedIn(): bool
    {
        return $this->checkIn()->exists();
    }

    public function canCheckIn(): bool
    {
        return $this->status === 'confirmed' && !$this->isCheckedIn();
    }

    public function promoteFromWaitlist(): bool
    {
        if ($this->status !== 'waitlisted') {
            return false;
        }

        if (!$this->event->canAcceptRegistrations()) {
            return false;
        }

        // A paid ticket cannot be confirmed without payment.
        if (!$this->ticketType->is_free) {
            return false;
        }

        $this->update(['status' => 'confirmed']);
        
        // TODO: Send promotion email notification
        
        return true;
    }
}
