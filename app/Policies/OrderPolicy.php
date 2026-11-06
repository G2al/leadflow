<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Single source of truth for who can do what on an order. The Actions re-evaluate
 * these abilities on the row locked inside their transaction.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->isStaff($user);
    }

    /** Orders are born only from the public form. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): Response
    {
        return $this->work($user, $order);
    }

    public function confirm(User $user, Order $order): Response
    {
        return $this->work($user, $order);
    }

    public function cancel(User $user, Order $order): Response
    {
        return $this->work($user, $order);
    }

    public function claim(User $user, Order $order): Response
    {
        if (! $this->isStaff($user)) {
            return Response::deny();
        }

        return $order->status === OrderStatus::New && $order->assigned_to === null
            ? Response::allow()
            : Response::deny('Questo ordine è già stato preso in carico.');
    }

    public function assign(User $user, Order $order): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return in_array($order->status, [OrderStatus::New, OrderStatus::InProgress], true)
            ? Response::allow()
            : Response::deny('Un ordine concluso non può essere riassegnato.');
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function work(User $user, Order $order): Response
    {
        if ($order->status !== OrderStatus::InProgress) {
            return Response::deny('Solo gli ordini in lavorazione possono essere modificati.');
        }

        return $user->isAdmin() || ($user->isCallCenter() && $order->isAssignedTo($user))
            ? Response::allow()
            : Response::deny("Questo ordine è assegnato a un'altra operatrice.");
    }

    private function isStaff(User $user): bool
    {
        return $user->hasAnyRole(UserRole::cases());
    }
}
