<?php

namespace Database\Seeders;

use App\Models\Tenants;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // password: "password" (UserFactory default)
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $tenant = Tenants::create([
            'name' => "Test User's Workspace",
            'slug' => 'test-users-workspace-' . Str::random(6),
            'status' => 'active',
        ]);

        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $user->update(['current_tenant_id' => $tenant->id]);
    }
}
