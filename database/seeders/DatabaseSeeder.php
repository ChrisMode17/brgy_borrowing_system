<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['role_id' => 1], ['role_name' => 'Admin']);
        Role::firstOrCreate(['role_name' => 'User']);

        foreach (['Equipment', 'Tool', 'Unit'] as $category) {
            InventoryCategory::firstOrCreate(['category_name' => $category]);
        }

        // Default admin account -- change the password immediately after first login.
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123'),
                'contact_number' => '09171234567',
                'role_id' => $adminRole->role_id,
                'access_level' => 0,
            ]
        );
    }
}
