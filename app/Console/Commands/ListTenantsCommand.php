<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;

class ListTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tenants with their user counts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenants = Tenant::withCount('users')
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found in the database.');
            return 0;
        }

        $this->info('Tenants and User Counts');
        $this->info('=======================');
        $this->newLine();

        $tableData = [];
        foreach ($tenants as $tenant) {
            $tableData[] = [
                'ID' => substr($tenant->id, 0, 8) . '...',
                'Name' => $tenant->name,
                'Business Name' => $tenant->business_name ?? 'N/A',
                'Subdomain' => $tenant->subdomain,
                'Users' => $tenant->users_count,
                'Status' => $tenant->is_active ? '<fg=green>Active</>' : '<fg=red>Inactive</>',
                'Onboarding' => $tenant->onboarding_status ?? 'N/A',
                'Created' => $tenant->created_at->format('Y-m-d'),
            ];
        }

        $this->table(
            ['ID', 'Name', 'Business Name', 'Subdomain', 'Users', 'Status', 'Onboarding', 'Created'],
            $tableData
        );

        $this->newLine();
        $this->line("Total Tenants: {$tenants->count()}");
        $this->line("Active Tenants: " . $tenants->where('is_active', true)->count());
        $this->line("Total Users Across All Tenants: " . $tenants->sum('users_count'));

        return 0;
    }
}

