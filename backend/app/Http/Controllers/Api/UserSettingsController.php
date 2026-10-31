<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->ensureProfile($user);
        $settings = $profile->getMergedPreferences();

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'data' => [
                'settings' => $settings,
                'allowed_boards' => $user->allowedBoards(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->ensureProfile($user);

        $incoming = $request->input('settings');
        if (!is_array($incoming)) {
            $incoming = $request->except(['_token', '_method']);
        }

        $merged = array_replace_recursive(
            $profile->getMergedPreferences(),
            is_array($incoming) ? $incoming : []
        );

        $profile->update([
            'ui_preferences' => $merged,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User settings synchronized.',
            'settings' => $merged,
            'data' => [
                'settings' => $merged,
                'allowed_boards' => $user->allowedBoards(),
            ],
        ]);
    }

    private function ensureProfile(User $user): UserProfile
    {
        return $user->profile ?: $user->profile()->create([
            'username' => 'maker-' . $user->id,
            'ui_preferences' => UserProfile::DEFAULT_UI_PREFERENCES,
        ]);
    }
}
