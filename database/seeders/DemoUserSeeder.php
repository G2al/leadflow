<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->seedUser('admin@leadflow.it', 'LeadFlow', 'Admin', UserRole::Admin);
        $this->seedUser('giulia.rossi@leadflow.it', 'Giulia', 'Rossi', UserRole::CallCenter);
    }

    /** Every run resets the password; other data of an existing account is kept. */
    private function seedUser(string $email, string $name, string $surname, UserRole $role): void
    {
        $user = User::query()->firstOrNew(['email' => $email], [
            'name' => $name,
            'is_active' => true,
        ]);

        $user->surname = filled($user->surname) ? $user->surname : $surname;
        $user->password = self::PASSWORD;
        $user->save();

        $user->assignRole($role);
    }
}
