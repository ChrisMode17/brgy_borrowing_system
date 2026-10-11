<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['role_id' => 1], ['role_name' => 'Admin']);
        Role::firstOrCreate(['role_name' => 'User']);

        foreach (['Equipment', 'Tool', 'Unit'] as $category) {
            InventoryCategory::firstOrCreate(['category_name' => $category]);
        }

        $adminPassword = config('app.initial_admin.password');
        if (app()->isProduction() && blank($adminPassword)) {
            throw new RuntimeException('INITIAL_ADMIN_PASSWORD must be set before seeding production.');
        }

        User::firstOrCreate(
            ['username' => config('app.initial_admin.username')],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'email' => config('app.initial_admin.email'),
                'password' => Hash::make($adminPassword ?: 'admin123'),
                'contact_number' => '09171234567',
                'role_id' => $adminRole->role_id,
                'access_level' => 0,
            ]
        );
    }
}
