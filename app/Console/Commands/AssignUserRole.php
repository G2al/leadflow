<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class AssignUserRole extends Command
{
    protected $signature = 'leadflow:assign-role {email : Email di un account esistente} {role : admin oppure call_center}';

    protected $description = 'Assegna esplicitamente un ruolo LeadFlow a un account esistente';

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->argument('role'));
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($role === null) {
            $this->error('Ruolo non valido: usa admin oppure call_center.');

            return self::FAILURE;
        }

        if ($user === null) {
            $this->error('Nessun account trovato con questa email.');

            return self::FAILURE;
        }

        $user->syncRoles($role);
        $this->info("Ruolo {$role->value} assegnato a {$user->email}.");

        return self::SUCCESS;
    }
}
