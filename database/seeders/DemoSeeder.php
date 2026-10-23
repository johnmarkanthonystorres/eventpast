<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo tenant
        $tenant = \App\Models\Tenant::create([
            'name' => 'Demo Organization',
            'subdomain' => 'demo',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        // Create admin user
        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->command->info('Demo tenant and user created successfully!');
        $this->command->info('Email: admin@demo.com');
        $this->command->info('Password: password');
    }
}
