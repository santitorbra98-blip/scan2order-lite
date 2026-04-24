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
                            {--name= : Name of the superadmin}
                            {--email= : Email address}
                            {--password= : Password}';

    protected $description = 'Create a superadmin user interactively';

    public function handle(): int
    {
        $this->info('=== Create SuperAdmin User ===');

        $name = $this->option('name') ?? $this->ask('Name');
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
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
