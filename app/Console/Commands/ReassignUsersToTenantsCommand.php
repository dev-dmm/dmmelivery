<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;

class ReassignUsersToTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:reassign-tenants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reassign users to their correct tenants based on email addresses';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Reassigning users to their correct tenants...');
        $this->newLine();

        // Get all tenants keyed by subdomain for easy lookup
        $tenants = Tenant::all()->keyBy('subdomain');

        // Define email to subdomain mapping
        $emailMappings = [
            'admin@dmm.gr' => 'dmm',
            'bookstoreplus@demo.com' => 'bookstoreplus',
            'electroshop@demo.com' => 'electroshop',
            'fashionboutique@demo.com' => 'fashionboutique',
        ];

        $updated = 0;
        $errors = [];

        foreach ($emailMappings as $email => $subdomain) {
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $errors[] = "User with email {$email} not found";
                continue;
            }

            $tenant = $tenants->get($subdomain);
            
            if (!$tenant) {
                $errors[] = "Tenant with subdomain {$subdomain} not found";
                continue;
            }

            $oldTenantName = $user->tenant ? $user->tenant->name : 'None';
            
            $user->update(['tenant_id' => $tenant->id]);
            
            $this->line("✓ {$email} → {$tenant->name} (was: {$oldTenantName})");
            $updated++;
        }

        $this->newLine();

        if ($updated > 0) {
            $this->info("Successfully reassigned {$updated} user(s) to their correct tenants.");
        }

        if (!empty($errors)) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        $this->newLine();
        $this->info('Current user-tenant associations:');
        $this->call('users:show-simple');

        return 0;
    }
}

