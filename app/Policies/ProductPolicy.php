<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Product $product): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $product->offers()->exists()
            ? Response::deny('Il prodotto è collegato a delle offerte: disattivalo invece di eliminarlo.')
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
