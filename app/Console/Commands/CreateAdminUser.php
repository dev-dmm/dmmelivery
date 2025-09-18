<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin-user {email} {--tenant-id=} {--password=password123}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a super admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $tenantId = $this->option('tenant-id');
        $password = $this->option('password');

        if (!$tenantId) {
            $this->error('Tenant ID is required. Use --tenant-id option.');
            return 1;
        }

        // Check if tenant exists
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant with ID {$tenantId} not found.");
            return 1;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");
            return 1;
        }

        // Create the user
        $user = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt($password),
            'role' => 'super_admin',
            'tenant_id' => $tenantId,
            'email_verified_at' => now(),
        ]);

        $this->info("Super admin user created successfully!");
        $this->info("Email: {$email}");
        $this->info("Password: {$password}");
        $this->info("Tenant: {$tenant->name} ({$tenant->business_name})");
        $this->info("User ID: {$user->id}");

        return 0;
    }
}
