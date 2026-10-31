<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'role_id',
        'whop_plan_id',
        'price',
        'price_suffix',
        'currency',
        'monthly_points',
        'bonus_points',
        'features',
        'cta_text',
        'is_highlighted',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'price' => 'decimal:2',
            'monthly_points' => 'decimal:4',
            'bonus_points' => 'decimal:4',
            'features' => 'array',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
