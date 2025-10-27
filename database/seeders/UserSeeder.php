<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@xpresspos.id'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super-admin');

        // Create Admin Sistem
        $adminSistem = User::firstOrCreate(
            ['email' => 'admin.sistem@xpresspos.id'],
            [
                'name' => 'Admin Sistem',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
        $adminSistem->assignRole('admin_sistem');

        // Create Owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.id'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('owner123'),
                'email_verified_at' => now(),
                // 'store_id' => 1, // Comment out until store is created
            ]
        );
        $owner->assignRole('owner');

        // Create Cashier
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@xpresspos.id'],
            [
                'name' => 'Cashier',
                'password' => Hash::make('cashier123'),
                'email_verified_at' => now(),
                // 'store_id' => 1, // Comment out until store is created
            ]
        );
        $cashier->assignRole('cashier');

        $this->command->info('Users created successfully!');
        $this->command->info('Super Admin: admin@xpresspos.id / admin123');
        $this->command->info('Admin Sistem: admin.sistem@xpresspos.id / admin123');
        $this->command->info('Owner: owner@xpresspos.id / owner123');
        $this->command->info('Cashier: cashier@xpresspos.id / cashier123');
    }
}