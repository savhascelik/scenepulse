<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_UPGRADE = 'upgrade';
    public const SOURCE_WHOP = 'whop';

    protected $fillable = [
        'user_id',
        'plan_id',
        'source',
        'whop_membership_id',
        'status',
        'trial_ends_at',
        'ends_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'plan_id' => 'integer',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
