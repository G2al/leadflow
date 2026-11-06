<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveUser
{
    /**
     * Create or update an account together with its single role.
     *
     * @param  array<string, mixed>  $data  Includes "role" as a UserRole
     *
     * @throws ValidationException when admins would lock themselves out
     */
    public function handle(array $data, User $actor, ?User $user = null): User
    {
        /** @var UserRole $role */
        $role = $data['role'];
        unset($data['role']);

        if ($user?->is($actor) && ($role !== UserRole::Admin || ! ($data['is_active'] ?? true))) {
            throw ValidationException::withMessages([
                'role' => 'Non puoi disattivare il tuo account o rimuovere il tuo ruolo di amministratore.',
            ]);
        }

        return DB::transaction(function () use ($data, $role, $user): User {
            $user ??= new User;
            $user->fill($data)->save();
            $user->syncRoles($role);

            return $user;
        });
    }
}
