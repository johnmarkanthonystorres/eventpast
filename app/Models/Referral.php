<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'code',
        'clicks',
        'conversions',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($referral) {
            if (empty($referral->code)) {
                $referral->code = Str::random(10);
            }
        });
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    public function incrementConversions(): void
    {
        $this->increment('conversions');
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->clicks === 0) {
            return 0;
        }
        return ($this->conversions / $this->clicks) * 100;
    }
}
