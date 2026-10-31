<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiCreditPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'credits',
        'bonus_credits',
        'price_usd',
        'whop_product_id',
        'whop_plan_id',
        'whop_checkout_url',
        'is_popular',
        'is_active',
        'sort_order',
        'description',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'decimal:2',
            'bonus_credits' => 'decimal:2',
            'price_usd' => 'decimal:2',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'features' => 'array',
        ];
    }

    public function getTotalCreditsAttribute(): float
    {
        return round((float) $this->credits + (float) $this->bonus_credits, 2);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(AiCreditOrder::class, 'package_id');
    }
}
