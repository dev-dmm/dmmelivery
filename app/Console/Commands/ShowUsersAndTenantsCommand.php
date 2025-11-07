<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ShowUsersAndTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:show-simple';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show user emails and their associated tenant names';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::with('tenant:id,name')
            ->orderBy('email')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users found in the database.');
            return 0;
        }

        $this->info('Users and Their Tenants');
        $this->info('=======================');
        $this->newLine();

        $tableData = [];
        foreach ($users as $user) {
            $tableData[] = [
                'Email' => $user->email,
                'Tenant Name' => $user->tenant ? $user->tenant->name : '[No Tenant]',
            ];
        }

        $this->table(
            ['Email', 'Tenant Name'],
            $tableData
        );

        $this->newLine();
        $this->line("Total Users: {$users->count()}");

        return 0;
    }
}

