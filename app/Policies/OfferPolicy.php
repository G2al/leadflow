<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Offer $offer): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Offer $offer): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Offer $offer): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $offer->orders()->exists()
            ? Response::deny("L'offerta ha ricevuto degli ordini: disattivala invece di eliminarla.")
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
