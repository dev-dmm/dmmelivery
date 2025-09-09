<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update existing users with email-based super admin to have super_admin role
        $superAdminEmails = [
            'admin@dmm.gr',
            'dev@dmm.gr',
            'super@dmm.gr'
        ];

        User::whereIn('email', $superAdminEmails)
            ->update(['role' => 'super_admin']);

        $this->command->info('Updated existing super admin users based on email addresses.');

        // Create a default super admin user if none exists
        if (!User::where('role', 'super_admin')->exists()) {
            // Create a default tenant for the super admin
            $tenant = Tenant::firstOrCreate([
                'subdomain' => 'admin'
            ], [
                'name' => 'DMM Administration',
                'contact_email' => 'admin@dmm.gr',
                'contact_phone' => null,
                'business_address' => null,
                'city' => null,
                'postal_code' => null,
                'country' => 'GR',
                'vat_number' => null,
                'onboarding_status' => 'active',
                'is_active' => true
            ]);

            User::create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@dmm.gr',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]);

            $this->command->info('Created default super admin user: admin@dmm.gr (password: password)');
        }

        // Update all other users to have 'user' role if they don't have a role set
        User::whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => 'user']);

        $this->command->info('Updated all users without roles to have "user" role.');

        // Show role statistics
        $roleCounts = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->get();

        $this->command->info('Current role distribution:');
        foreach ($roleCounts as $roleCount) {
            $this->command->info("- {$roleCount->role}: {$roleCount->count} users");
        }
    }
}