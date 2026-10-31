<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiCreditBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'promotional_credits',
        'purchased_credits',
        'total_earned_promotional',
        'total_purchased',
        'total_spent',
    ];

    protected function casts(): array
    {
        return [
            'promotional_credits' => 'decimal:4',
            'purchased_credits' => 'decimal:4',
            'total_earned_promotional' => 'decimal:4',
            'total_purchased' => 'decimal:4',
            'total_spent' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAvailableCreditsAttribute(): float
    {
        return round((float) $this->promotional_credits + (float) $this->purchased_credits, 4);
    }

    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'promotional_credits' => 0,
                'purchased_credits' => 0,
                'total_earned_promotional' => 0,
                'total_purchased' => 0,
                'total_spent' => 0,
            ]
        );
    }

    public function addPromotionalCredits(
        float $amount,
        string $type = 'signup_grant',
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): AiCreditTransaction {
        return DB::transaction(function () use ($amount, $type, $description, $referenceType, $referenceId, $metadata) {
            $locked = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $locked->promotional_credits = round((float) $locked->promotional_credits + $amount, 4);
            $locked->total_earned_promotional = round((float) $locked->total_earned_promotional + $amount, 4);
            $locked->save();

            $this->refresh();

            return AiCreditTransaction::create([
                'user_id' => $locked->user_id,
                'type' => $type,
                'bucket' => 'promotional',
                'amount' => $amount,
                'balance_after' => $locked->available_credits,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
            ]);
        });
    }

    public function addPurchasedCredits(
        float $amount,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): AiCreditTransaction {
        return DB::transaction(function () use ($amount, $description, $referenceType, $referenceId, $metadata) {
            $locked = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $locked->purchased_credits = round((float) $locked->purchased_credits + $amount, 4);
            $locked->total_purchased = round((float) $locked->total_purchased + $amount, 4);
            $locked->save();

            $this->refresh();

            return AiCreditTransaction::create([
                'user_id' => $locked->user_id,
                'type' => 'purchase',
                'bucket' => 'purchased',
                'amount' => $amount,
                'balance_after' => $locked->available_credits,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Spend credits: deducts from promotional_credits first, then purchased_credits.
     */
    public function spendCredits(
        float $amount,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): ?AiCreditTransaction {
        return DB::transaction(function () use ($amount, $description, $referenceType, $referenceId, $metadata) {
            $locked = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            if ($locked->available_credits < $amount) {
                return null;
            }

            $remaining = $amount;
            $fromPromo = 0.0;
            $fromPurchased = 0.0;

            if ((float) $locked->promotional_credits > 0) {
                $fromPromo = min((float) $locked->promotional_credits, $remaining);
                $locked->promotional_credits = round((float) $locked->promotional_credits - $fromPromo, 4);
                $remaining = round($remaining - $fromPromo, 4);
            }

            if ($remaining > 0) {
                $fromPurchased = $remaining;
                $locked->purchased_credits = max(0, round((float) $locked->purchased_credits - $fromPurchased, 4));
            }

            $locked->total_spent = round((float) $locked->total_spent + $amount, 4);
            $locked->save();

            $this->refresh();

            $bucket = ($fromPromo > 0 && $fromPurchased > 0)
                ? 'mixed'
                : ($fromPromo > 0 ? 'promotional' : 'purchased');

            return AiCreditTransaction::create([
                'user_id' => $locked->user_id,
                'type' => 'spend',
                'bucket' => $bucket,
                'amount' => -$amount,
                'balance_after' => $locked->available_credits,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => array_merge($metadata ?? [], [
                    'from_promotional' => $fromPromo,
                    'from_purchased' => $fromPurchased,
                ]),
            ]);
        });
    }
}
