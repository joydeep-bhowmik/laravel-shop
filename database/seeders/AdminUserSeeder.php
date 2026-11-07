<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the initial super admin from ADMIN_EMAIL and ADMIN_PASSWORD, or a generated password when unset.
     *
     * The generated password is printed once so it can be changed on first login.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@example.com');
        $password = (string) env('ADMIN_PASSWORD', '');
        $generated = $password === '';

        if ($generated) {
            $password = Str::password(20);
        }

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Store Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([AdminRole::SuperAdmin->value]);

        if ($this->command !== null && $generated) {
            $this->command->warn("Generated super admin password for {$email}: {$password}");
            $this->command->warn('Change it after first login. Set ADMIN_PASSWORD in .env to choose your own.');
        }
    }
}
