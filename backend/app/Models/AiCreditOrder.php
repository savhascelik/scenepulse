<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiCreditOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'package_id',
        'credits_amount',
        'bonus_credits',
        'price_paid_usd',
        'currency',
        'status',
        'whop_payment_id',
        'whop_checkout_id',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'credits_amount' => 'decimal:2',
            'bonus_credits' => 'decimal:2',
            'price_paid_usd' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getTotalCreditsAttribute(): float
    {
        return round((float) $this->credits_amount + (float) $this->bonus_credits, 2);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(AiCreditPackage::class, 'package_id');
    }

    public static function generateOrderNumber(): string
    {
        return 'NBS-CRD-' . strtoupper(Str::random(10));
    }
}
