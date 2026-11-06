<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = $this->password('admin_password', 'LEADFLOW_DEMO_ADMIN_PASSWORD');
        $callCenterPassword = $this->password('call_center_password', 'LEADFLOW_DEMO_CALL_CENTER_PASSWORD');

        $this->seedUser('admin@leadflow.it', 'LeadFlow', 'Admin', $adminPassword, UserRole::Admin);
        $this->seedUser('giulia.rossi@leadflow.it', 'Giulia', 'Rossi', $callCenterPassword, UserRole::CallCenter);
    }

    private function password(string $key, string $envName): string
    {
        $password = config("leadflow.demo.{$key}");

        if (blank($password)) {
            throw new RuntimeException("Valorizza {$envName} nel file .env locale prima di eseguire il seed.");
        }

        return $password;
    }

    /** Existing accounts keep their password and data; only a missing surname is filled in. */
    private function seedUser(string $email, string $name, string $surname, string $password, UserRole $role): void
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'surname' => $surname,
            'password' => $password,
            'is_active' => true,
        ]);

        if (blank($user->surname)) {
            $user->update(['surname' => $surname]);
        }

        $user->assignRole($role);
    }
}
