<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;

class ListUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all users with their roles and associated tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::with('tenant:id,name,subdomain,business_name')
            ->orderBy('tenant_id')
            ->orderBy('role')
            ->orderBy('last_name')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users found in the database.');
            return 0;
        }

        $this->info('Users, Roles, and Tenants');
        $this->info('========================');
        $this->newLine();

        // Group by tenant for better readability
        $groupedByTenant = $users->groupBy('tenant_id');

        foreach ($groupedByTenant as $tenantId => $tenantUsers) {
            $tenant = $tenantUsers->first()->tenant;
            
            if ($tenant) {
                $this->line("<fg=cyan>Tenant: {$tenant->name}</>");
                if ($tenant->business_name && $tenant->business_name !== $tenant->name) {
                    $this->line("<fg=gray>Business: {$tenant->business_name}</>");
                }
                $this->line("<fg=gray>Subdomain: {$tenant->subdomain}</>");
            } else {
                $this->line("<fg=yellow>Tenant: [No Tenant Assigned] (ID: {$tenantId})</>");
            }
            
            $this->newLine();

            // Prepare table data
            $tableData = [];
            foreach ($tenantUsers as $user) {
                $tableData[] = [
                    'ID' => substr($user->id, 0, 8) . '...',
                    'Name' => $user->first_name . ' ' . $user->last_name,
                    'Email' => $user->email,
                    'Role' => $this->formatRole($user->role),
                    'Status' => $user->is_active ? '<fg=green>Active</>' : '<fg=red>Inactive</>',
                    'Created' => $user->created_at->format('Y-m-d'),
                ];
            }

            $this->table(
                ['ID', 'Name', 'Email', 'Role', 'Status', 'Created'],
                $tableData
            );

            $this->newLine();
        }

        // Summary statistics
        $this->info('Summary Statistics');
        $this->info('==================');
        
        $roleCounts = $users->groupBy('role')->map->count();
        foreach ($roleCounts as $role => $count) {
            $this->line("  {$this->formatRole($role)}: {$count}");
        }

        $this->newLine();
        $this->line("Total Users: {$users->count()}");
        $this->line("Total Tenants: {$groupedByTenant->count()}");
        $this->line("Active Users: " . $users->where('is_active', true)->count());
        $this->line("Inactive Users: " . $users->where('is_active', false)->count());

        return 0;
    }

    /**
     * Format role name for display
     */
    private function formatRole(string $role): string
    {
        $roleMap = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'user' => 'User',
        ];

        return $roleMap[$role] ?? ucfirst($role);
    }
}

