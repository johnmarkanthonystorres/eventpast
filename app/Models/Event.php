<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'description',
        'start_at',
        'end_at',
        'timezone',
        'venue_name',
        'venue_address',
        'online_link',
        'status',
        'capacity',
        'image',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($event) {
            $base = $event->slug ?: Str::slug($event->title);
            $base = $base !== '' ? $base : 'event';

            // (tenant_id, slug) is unique, so a repeated title would otherwise
            // crash with a database error. Suffix -2, -3, ... instead.
            $slug = $base;
            $n = 2;
            while (static::withTrashed()->where('tenant_id', $event->tenant_id)->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$n}";
                $n++;
            }

            $event->slug = $slug;
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function confirmedRegistrations(): HasMany
    {
        return $this->registrations()->where('status', 'confirmed');
    }

    public function waitlistedRegistrations(): HasMany
    {
        return $this->registrations()->where('status', 'waitlisted');
    }

    /**
     * Registrations that occupy a seat: confirmed, already checked in, and
     * paid-ticket buyers mid-checkout (held until they pay or it expires).
     */
    public function seatHoldingRegistrations(): HasMany
    {
        return $this->registrations()->whereIn('status', ['confirmed', 'checked_in', 'pending_payment']);
    }

    public function getAvailableSeatsAttribute(): int
    {
        $capacity = $this->capacity ?? $this->tenant->participant_limit;
        $held = $this->seatHoldingRegistrations()->count();
        return max(0, $capacity - $held);
    }

    public function getIsFullAttribute(): bool
    {
        return $this->available_seats <= 0;
    }

    public function canAcceptRegistrations(): bool
    {
        return $this->status === 'published' && !$this->is_full;
    }

    /**
     * The event start as an absolute instant.
     *
     * start_at is stored as the organiser's wall-clock time (the form sends
     * no offset), so it must be read in the event's own timezone rather than
     * the app timezone.
     */
    public function startsAtInstant(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse(
            $this->start_at->format('Y-m-d H:i:s'),
            $this->timezone ?: 'UTC'
        );
    }

    public function getRegistrationUrl(): string
    {
        return route('public.events.show', [$this->tenant, $this]);
    }
}
