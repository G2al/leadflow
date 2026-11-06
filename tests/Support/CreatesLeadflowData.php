<?php

namespace Tests\Support;

use App\Actions\Orders\ClaimOrder;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

/**
 * Plain helpers instead of model factories: products, offers and orders
 * deliberately have no factories in this project.
 */
trait CreatesLeadflowData
{
    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    protected function admin(array $attributes = []): User
    {
        return $this->userWithRole(UserRole::Admin, $attributes);
    }

    protected function operator(array $attributes = []): User
    {
        return $this->userWithRole(UserRole::CallCenter, $attributes);
    }

    protected function userWithRole(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(['surname' => 'Test', ...$attributes])->assignRole($role);
    }

    protected function actingOnPanel(User $user): static
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $this;
    }

    protected function product(array $attributes = []): Product
    {
        return Product::query()->create([
            'name' => 'Prodotto test',
            'sku' => 'SKU-'.uniqid(),
            'purchase_price' => '10.00',
            'selling_price' => '49.90',
            ...$attributes,
        ]);
    }

    protected function offer(array $attributes = [], ?Product $product = null): Offer
    {
        return Offer::query()->create([
            'name' => 'Offerta test',
            'product_id' => ($product ?? $this->product())->getKey(),
            ...$attributes,
        ]);
    }

    protected function newOrder(?Offer $offer = null, array $attributes = []): Order
    {
        $order = new Order([
            'first_name' => 'Mario',
            'last_name' => 'Bianchi',
            'phone' => '+393331234567',
            ...$attributes,
        ]);

        $offer ??= $this->offer();
        $order->offer()->associate($offer);
        $order->unit_price = $offer->effectivePrice();
        $order->status = OrderStatus::New;
        $order->save();

        return $order;
    }

    protected function claimedOrder(User $operator, array $attributes = []): Order
    {
        return app(ClaimOrder::class)->handle($this->newOrder(attributes: $attributes), $operator);
    }

    protected function submissionUrl(Offer $offer): string
    {
        return route('embed.orders.store', $offer);
    }

    protected function validSubmission(array $overrides = []): array
    {
        return [
            'first_name' => 'Mario',
            'last_name' => 'Bianchi',
            'phone' => '+39 333 123 4567',
            'source_url' => 'https://landing.example.com/promo',
            ...$overrides,
        ];
    }
}
