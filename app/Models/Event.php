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
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title);
            }
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

    public function getAvailableSeatsAttribute(): int
    {
        $capacity = $this->capacity ?? $this->tenant->participant_limit;
        $confirmed = $this->confirmedRegistrations()->count();
        return max(0, $capacity - $confirmed);
    }

    public function getIsFullAttribute(): bool
    {
        return $this->available_seats <= 0;
    }

    public function canAcceptRegistrations(): bool
    {
        return $this->status === 'published' && !$this->is_full;
    }

    public function getRegistrationUrl(): string
    {
        return route('events.show', ['tenant' => $this->tenant->subdomain, 'slug' => $this->slug]);
    }
}
