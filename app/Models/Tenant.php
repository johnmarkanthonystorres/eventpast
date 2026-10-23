<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'subdomain',
        'plan',
        'stripe_account_id',
        'stripe_charges_enabled',
        'brand_settings',
        'is_active',
        'trial_ends_at',
    ];

    /** Never serialize the payout account id into Inertia/JSON responses. */
    protected $hidden = [
        'stripe_account_id',
    ];

    protected $casts = [
        'brand_settings' => 'array',
        'is_active' => 'boolean',
        'stripe_charges_enabled' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * Whether this organizer can receive ticket payments: a connected Stripe
     * account that Stripe has enabled for charges.
     */
    public function canAcceptPayments(): bool
    {
        return ! empty($this->stripe_account_id) && $this->stripe_charges_enabled;
    }

    public function getParticipantLimitAttribute(): int
    {
        return match($this->plan) {
            'free' => 50,
            'pro' => 500,
            'business' => 5000,
            'enterprise' => PHP_INT_MAX,
            default => 50,
        };
    }

    public function getEventLimitAttribute(): ?int
    {
        return match($this->plan) {
            'free' => 2,
            'pro' => 20,
            'business' => null,
            'enterprise' => null,
            default => 2,
        };
    }

    public function getTicketFeePercentageAttribute(): float
    {
        return match($this->plan) {
            'free' => 5.0,
            'pro' => 2.5,
            'business' => 1.5,
            'enterprise' => 0,
            default => 5.0,
        };
    }

    public function getFixedTicketFeeAttribute(): float
    {
        return match($this->plan) {
            'free' => 0.50,
            'pro' => 0,
            'business' => 0,
            'enterprise' => 0,
            default => 0.50,
        };
    }
}
