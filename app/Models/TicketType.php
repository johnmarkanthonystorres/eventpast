<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'quantity',
        'quantity_sold',
        'sales_start_at',
        'sales_end_at',
        'is_active',
    ];

    protected $appends = ['available_quantity', 'is_available'];

    protected $casts = [
        'price' => 'decimal:2',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function getAvailableQuantityAttribute(): ?int
    {
        if ($this->quantity === null) {
            return null;
        }
        return max(0, $this->quantity - $this->quantity_sold);
    }

    public function getIsFreeAttribute(): bool
    {
        return $this->price == 0;
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->isAvailableForSale();
    }

    public function isAvailableForSale(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->sales_start_at && $now->lt($this->sales_start_at)) {
            return false;
        }

        if ($this->sales_end_at && $now->gt($this->sales_end_at)) {
            return false;
        }

        if ($this->quantity !== null && $this->quantity_sold >= $this->quantity) {
            return false;
        }

        return true;
    }
}
