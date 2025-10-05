<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FilamentUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create System Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@xpresspos.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        if (!$admin->hasRole('admin_sistem')) {
            $admin->assignRole('admin_sistem');
        }

        // Create Store Owner user
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.com'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        if (!$owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }

        $this->command->info('Filament users created successfully!');
        $this->command->info('Admin: admin@xpresspos.com / password123');
        $this->command->info('Owner: owner@xpresspos.com / password123');
    }
}
