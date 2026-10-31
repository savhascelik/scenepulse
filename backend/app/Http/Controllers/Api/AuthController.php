<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\User;
use App\Services\AiCreditService;
use App\Services\LimitService;
use App\Services\UserAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly LimitService $limitService,
        private readonly AiCreditService $creditService,
        private readonly UserAccessService $userAccessService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:filter', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $email = strtolower(trim($validated['email']));
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if (! $domain || ! str_contains($domain, '.') || strlen($domain) < 4) {
            throw ValidationException::withMessages([
                'email' => ['Lütfen geçerli bir e-posta adresi girin (ör. ad@alanadi.com).'],
            ]);
        }

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Every newly registered user gets the 'free' role and their initial AI credit grant
        $user->assignRole('free');
        $user->ensureInitialCreditGrant();

        // Automatically add new users to the default public "Açık Donanım Atölyesi" study group
        $defaultGroup = StudyGroup::where('slug', 'acik-donanim-atolyesi')->first();
        if ($defaultGroup) {
            StudyGroupMember::firstOrCreate(
                ['study_group_id' => $defaultGroup->id, 'user_id' => $user->id],
                ['member_role' => 'member', 'joined_at' => now()]
            );
        }

        $token = $user->createToken('studio-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['E-posta adresi veya parola hatalı.'],
            ]);
        }

        $user->ensureInitialCreditGrant();
        $token = $user->createToken('studio-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * One-Click Judge Auto-Login endpoint (`POST /api/v1/auth/judge-login`).
     * Authenticates a hackathon judge via URL access key and ensures a 400-credit grant.
     */
    public function judgeLogin(Request $request): JsonResponse
    {
        $providedKey = trim((string) (
            $request->input('key')
            ?? $request->input('judge_key')
            ?? $request->input('code')
            ?? $request->input('token')
            ?? ''
        ));

        $rawCandidates = [
            (string) config('services.judge.access_code', ''),
            (string) config('services.judge.magic_token', ''),
            (string) (getenv('JUDGE_ACCESS_CODE') ?: ''),
            (string) (getenv('JUDGE_MAGIC_TOKEN') ?: ''),
        ];

        $validKeys = array_values(array_filter(array_unique(array_map(
            fn (string $v) => strtolower(trim($v)),
            $rawCandidates
        )), fn (string $v) => $v !== ''));

        if ($providedKey === '' || empty($validKeys) || ! in_array(strtolower($providedKey), $validKeys, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_judge_key',
                'message' => 'Invalid Judge access token.',
            ], 403);
        }

        $requestedEmail = strtolower(trim((string) ($request->input('email') ?? '')));
        $targetEmail = ($requestedEmail !== '' && filter_var($requestedEmail, FILTER_VALIDATE_EMAIL))
            ? $requestedEmail
            : 'judge@nebius-hackathon.local';

        $judgeName = trim((string) ($request->input('name') ?? '')) ?: 'Hackathon Judge';

        $user = User::firstOrCreate(
            ['email' => $targetEmail],
            [
                'name' => $judgeName,
                'password' => Hash::make('judge-nebius-2026'),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('judge')) {
            $user->syncRoles(['judge']);
        }

        $balance = $user->ensureInitialCreditGrant();
        if ((float) $balance->available_credits < 400.0) {
            $balance->update([
                'promotional_credits' => max(400.0, (float) $balance->promotional_credits),
                'last_grant_at' => now(),
            ]);
        }

        $defaultGroup = StudyGroup::where('slug', 'acik-donanim-atolyesi')->first();
        if ($defaultGroup) {
            StudyGroupMember::firstOrCreate(
                ['study_group_id' => $defaultGroup->id, 'user_id' => $user->id],
                ['member_role' => 'owner', 'joined_at' => now()]
            );
        }

        $token = $user->createToken('judge-magic-token')->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->ensureInitialCreditGrant();

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:filter', 'max:180', 'unique:users,email,' . $user->id],
        ]);

        $email = strtolower(trim($validated['email']));
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if (! $domain || ! str_contains($domain, '.') || strlen($domain) < 4) {
            throw ValidationException::withMessages([
                'email' => ['Lütfen geçerli bir e-posta adresi girin (ör. ad@alanadi.com).'],
            ]);
        }

        $user->update([
            'name' => trim($validated['name']),
            'email' => $email,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Hesap bilgileriniz başarıyla güncellendi.',
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mevcut parolanız hatalı.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Parolanız güvenli bir şekilde güncellendi.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['status' => 'logged_out']);
    }

    private function serializeUser(User $user): array
    {
        $credits = $this->creditService->getBalanceSummary($user);
        $accessProfile = $this->userAccessService->getAccessProfile($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $accessProfile['role'],
            'roles' => $user->getRoleNames()->values()->all(),
            'plan' => $accessProfile['plan'],
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'quotas' => $accessProfile['quotas'],
            'credits' => $credits,
            'limits' => $this->limitService->getUserAllLimits($user),
            'access_profile' => $accessProfile,
        ];
    }
}
