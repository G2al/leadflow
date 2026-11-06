<?php

namespace Tests\Feature;

use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesLeadflowData;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use CreatesLeadflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_admin_reaches_every_resource(): void
    {
        $this->actingOnPanel($this->admin());
        $offer = $this->offer();

        foreach ([
            '/admin/dashboard',
            UserResource::getUrl(),
            UserResource::getUrl('create'),
            ProductResource::getUrl(),
            ProductResource::getUrl('create'),
            OfferResource::getUrl(),
            OfferResource::getUrl('create'),
            OfferResource::getUrl('view', ['record' => $offer]),
            OrderResource::getUrl(),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_call_center_reaches_only_orders(): void
    {
        $product = $this->product();
        $offer = $this->offer(product: $product);
        $order = $this->newOrder($offer);
        $this->actingOnPanel($this->operator());

        $this->get(OrderResource::getUrl())->assertOk();
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk();

        foreach ([
            '/admin/dashboard',
            '/admin/users',
            '/admin/users/create',
            "/admin/users/{$order->getKey()}/edit",
            '/admin/products',
            '/admin/products/create',
            "/admin/products/{$product->getKey()}/edit",
            '/admin/offers',
            '/admin/offers/create',
            "/admin/offers/{$offer->getKey()}",
            "/admin/offers/{$offer->getKey()}/edit",
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_panel_root_sends_operators_to_orders_and_hides_other_menu_items(): void
    {
        $this->actingOnPanel($this->operator());

        $this->get('/admin')->assertRedirect(OrderResource::getUrl());

        $this->get(OrderResource::getUrl())
            ->assertOk()
            ->assertDontSee('/admin/products', false)
            ->assertDontSee('/admin/offers', false)
            ->assertDontSee('/admin/users', false);
    }

    public function test_admin_menu_is_grouped_in_order(): void
    {
        $this->actingOnPanel($this->admin());

        $this->get(OrderResource::getUrl())
            ->assertOk()
            ->assertSeeInOrder(['Vendite', 'Ordini', 'Catalogo', 'Prodotti', 'Offerte', 'Amministrazione', 'Utenti']);
    }

    public function test_operator_menu_shows_only_their_section(): void
    {
        $this->actingOnPanel($this->operator());

        $this->get(OrderResource::getUrl())
            ->assertOk()
            ->assertSee('Vendite')
            ->assertDontSee('Catalogo')
            ->assertDontSee('Amministrazione');
    }

    public function test_user_without_role_cannot_enter(): void
    {
        $this->actingOnPanel(User::factory()->create());

        $this->get(OrderResource::getUrl())->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->operator(['is_active' => false, 'password' => 'correct-password']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'correct-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_user_deactivated_during_a_session_loses_access(): void
    {
        $user = $this->operator();
        $this->actingOnPanel($user);
        $this->get(OrderResource::getUrl())->assertOk();

        $user->update(['is_active' => false]);

        $this->get(OrderResource::getUrl())->assertForbidden();
    }

    public function test_active_user_logs_in_and_last_login_is_recorded(): void
    {
        $user = $this->operator(['password' => 'correct-password']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'correct-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_panel_pages_cannot_be_framed(): void
    {
        $this->get('/admin/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
