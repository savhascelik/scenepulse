<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoleLimit;
use App\Models\User;
use App\Services\LimitService;
use App\Services\UserAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LimitController extends Controller
{
    private const VALID_RESOURCES = [
        RoleLimit::RESOURCE_SCENARIOS,
        RoleLimit::RESOURCE_CIRCUIT_SCENARIO,
        RoleLimit::RESOURCE_GROUPS,
        RoleLimit::RESOURCE_STUDY_GROUP,
        RoleLimit::RESOURCE_COMPONENTS,
        RoleLimit::RESOURCE_CUSTOM_COMPONENT,
        RoleLimit::RESOURCE_AI_USAGE,
        RoleLimit::RESOURCE_AI_LAB,
    ];

    public function __construct(
        private readonly UserAccessService $userAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $limits = [
            'scenarios' => LimitService::getLimitInfo(RoleLimit::RESOURCE_SCENARIOS, $user),
            'groups' => LimitService::getLimitInfo(RoleLimit::RESOURCE_GROUPS, $user),
            'custom_components' => LimitService::getLimitInfo(RoleLimit::RESOURCE_COMPONENTS, $user),
        ];

        return response()->json([
            'success' => true,
            'data' => $limits,
            'access_profile' => $this->userAccessService->getAccessProfile($user),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $overview = LimitService::getUsageOverview($user);
        $overview['access_profile'] = $this->userAccessService->getAccessProfile($user);

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    public function show(string $resourceType, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! in_array($resourceType, self::VALID_RESOURCES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource type',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => LimitService::getLimitInfo($resourceType, $user),
        ]);
    }

    public function canCreate(string $resourceType, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! in_array($resourceType, self::VALID_RESOURCES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource type',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => LimitService::canCreate($resourceType, $user),
        ]);
    }
}
