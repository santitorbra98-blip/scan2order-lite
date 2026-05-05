<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'superadmin:create
                            {--name= : Name of the superadmin (or set SUPERADMIN_NAME env var)}
                            {--email= : Email address (or set SUPERADMIN_EMAIL env var)}
                            {--password= : Password (or set SUPERADMIN_PASSWORD env var, min 12 chars)}';

    protected $description = 'Create a superadmin user. Reads SUPERADMIN_NAME/EMAIL/PASSWORD env vars for non-interactive use.';

    public function handle(): int
    {
        $this->info('=== Create SuperAdmin User ===');

        // Prefer CLI options → then env vars → then interactive prompt
        $name     = $this->option('name')     ?? env('SUPERADMIN_NAME')     ?? $this->ask('Name');
        $email    = $this->option('email')    ?? env('SUPERADMIN_EMAIL')    ?? $this->ask('Email');
        $password = $this->option('password') ?? env('SUPERADMIN_PASSWORD') ?? $this->secret('Password');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $role = Role::ensureDefault('superadmin');

        $user = User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make($password),
            'role_id'           => $role->id,
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->info("SuperAdmin created successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', 'superadmin'],
            ]
        );

        return self::SUCCESS;
    }
}
