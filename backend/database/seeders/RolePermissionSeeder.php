<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const ALL_PERMISSIONS = [
        // Scenarios (3D Circuit projects)
        'scenarios.view',
        'scenarios.create',
        'scenarios.edit',
        'scenarios.delete',
        'scenarios.publish',
        'scenarios.fork',

        // Open Study Groups (No teacher/student division — everyone can create & join)
        'groups.view',
        'groups.create',
        'groups.manage',
        'groups.invite',
        'groups.join',

        // Custom 3D Components & Datasheet Synthesis
        'components.view',
        'components.synthesize_ai',
        'components.publish',

        // AI Lab (OpenAI-Compatible / Nebius Nemotron + Tavily + Flownie Code Mode)
        'ai.copilot',
        'ai.circuit_builder',
        'ai.datasheet_search',
        'ai.generate_circuit',
        'ai.code_mode',
        'ai.search_tools',
        'credits.purchase',

        // Admin
        'admin.access',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ALL_PERMISSIONS as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // 1. unverified
        $unverified = Role::firstOrCreate(['name' => 'unverified', 'guard_name' => 'web']);
        $unverified->syncPermissions([
            'scenarios.view',
            'groups.view',
            'components.view',
        ]);

        // 2. free (every regular user can create groups, share circuits, fork, and use AI within quota)
        $free = Role::firstOrCreate(['name' => 'free', 'guard_name' => 'web']);
        $free->syncPermissions([
            'scenarios.view',
            'scenarios.create',
            'scenarios.edit',
            'scenarios.delete',
            'scenarios.publish',
            'scenarios.fork',
            'groups.view',
            'groups.create', // FREE_ROLE_GROUPS_CREATE
            'groups.manage',
            'groups.invite',
            'groups.join',
            'components.view',
            'components.synthesize_ai',
            'components.publish',
            'ai.copilot',
            'ai.circuit_builder',
            'ai.datasheet_search',
            'ai.generate_circuit',
            'ai.code_mode',
            'ai.search_tools',
            'credits.purchase',
        ]);

        // 3. pro
        $pro = Role::firstOrCreate(['name' => 'pro', 'guard_name' => 'web']);
        $pro->syncPermissions(array_diff(self::ALL_PERMISSIONS, ['admin.access']));

        // 4. judge (Hackathon Judge — full studio & AI access with dedicated high quota)
        $judge = Role::firstOrCreate(['name' => 'judge', 'guard_name' => 'web']);
        $judge->syncPermissions(array_diff(self::ALL_PERMISSIONS, ['admin.access']));

        // 5. admin
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(self::ALL_PERMISSIONS);
    }
}
