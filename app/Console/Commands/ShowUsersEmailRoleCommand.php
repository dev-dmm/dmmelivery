<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ShowUsersEmailRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:show-email-role';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show user emails and their roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::orderBy('email')->get();

        if ($users->isEmpty()) {
            $this->info('No users found in the database.');
            return 0;
        }

        $this->info('Users - Email and Role');
        $this->info('======================');
        $this->newLine();

        $tableData = [];
        foreach ($users as $user) {
            $tableData[] = [
                'Email' => $user->email,
                'Role' => $this->formatRole($user->role),
            ];
        }

        $this->table(
            ['Email', 'Role'],
            $tableData
        );

        $this->newLine();
        $this->line("Total Users: {$users->count()}");

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

