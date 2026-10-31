<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'bio',
        'avatar_url',
        'ui_preferences',
    ];

    protected $casts = [
        'ui_preferences' => 'array',
    ];

    public const DEFAULT_UI_PREFERENCES = [
        'theme' => 'dark',
        'language' => 'tr',
        'activeBoard' => 'arduino_uno',
        'stageQuality' => 'high',
        'showPinLabels' => true,
        'autoRunSimulation' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMergedPreferences(): array
    {
        return array_replace_recursive(
            self::DEFAULT_UI_PREFERENCES,
            is_array($this->ui_preferences) ? $this->ui_preferences : []
        );
    }
}
