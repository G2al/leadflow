<?php

namespace Tests\Feature;

use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Models\Offer;
use App\Services\OfferEmbedCode;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesLeadflowData;
use Tests\TestCase;

class OfferResourceTest extends TestCase
{
    use CreatesLeadflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->actingOnPanel($this->admin());
    }

    public function test_offer_without_custom_price_uses_the_product_price(): void
    {
        $offer = $this->offer(product: $this->product(['selling_price' => '49.90']));

        $this->assertSame('49.90', $offer->effectivePrice());
    }

    public function test_offer_with_custom_price_uses_it(): void
    {
        $offer = $this->offer(['custom_price' => '39.00'], $this->product(['selling_price' => '49.90']));

        $this->assertSame('39.00', $offer->effectivePrice());
    }

    public function test_admin_creates_an_offer_and_the_token_is_generated_by_the_server(): void
    {
        $product = $this->product();

        Livewire::test(CreateOffer::class)
            ->fillForm(['name' => 'Promo', 'product_id' => $product->getKey(), 'custom_price' => null, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::query()->firstOrFail();

        $this->assertNull($offer->custom_price);
        $this->assertSame(Offer::TOKEN_LENGTH, strlen($offer->public_token));
    }

    public function test_public_token_cannot_be_mass_assigned_and_is_unique(): void
    {
        $product = $this->product();
        $first = Offer::query()->create(['name' => 'A', 'product_id' => $product->getKey(), 'public_token' => 'chosen-by-client']);
        $second = $this->offer(product: $product);

        $this->assertNotSame('chosen-by-client', $first->public_token);
        $this->assertNotSame($first->public_token, $second->public_token);
    }

    public function test_detail_page_shows_the_escaped_copyable_snippet(): void
    {
        $offer = $this->offer();
        $snippet = app(OfferEmbedCode::class)->snippet($offer);

        $this->assertStringContainsString(rtrim(config('app.url'), '/')."/embed/offers/{$offer->public_token}.js", $snippet);

        $this->get(OfferResource::getUrl('view', ['record' => $offer]))
            ->assertOk()
            ->assertSee($snippet)
            ->assertSee('Copia script');
    }

    public function test_detail_page_has_code_and_preview_tabs(): void
    {
        $offer = $this->offer();

        $this->get(OfferResource::getUrl('view', ['record' => $offer]))
            ->assertOk()
            ->assertSeeInOrder(['Codice', 'Anteprima'])
            ->assertSee(route('embed.form', ['offer' => $offer, 'preview' => 1]));
    }

    public function test_preview_flag_is_set_only_on_request(): void
    {
        $offer = $this->offer();

        $this->get(route('embed.form', [$offer, 'preview' => 1]))->assertOk()->assertSee('var IS_PREVIEW = true;', false);
        $this->get(route('embed.form', $offer))->assertOk()->assertSee('var IS_PREVIEW = false;', false);
    }

    public function test_detail_page_links_to_the_test_landing(): void
    {
        $offer = $this->offer();

        $this->get(OfferResource::getUrl('view', ['record' => $offer]))
            ->assertSee('Lancia script')
            ->assertSee(route('filament.admin.offers.test-landing', $offer));
    }

    public function test_test_landing_runs_the_real_snippet_for_admins(): void
    {
        $offer = $this->offer(['name' => '<b>Promo</b>']);

        $this->get(route('filament.admin.offers.test-landing', $offer))
            ->assertOk()
            ->assertSee(app(OfferEmbedCode::class)->snippet($offer), false)
            ->assertSee('ogni invio crea un ordine reale')
            ->assertDontSee('<b>Promo</b>', false)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_test_landing_is_reserved_to_active_admins(): void
    {
        $offer = $this->offer();
        $url = route('filament.admin.offers.test-landing', $offer);

        $this->actingOnPanel($this->operator());
        $this->get($url)->assertForbidden();

        $this->actingOnPanel($this->admin(['is_active' => false]));
        $this->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect('/admin/login');
    }

    public function test_cards_show_price_and_availability_and_can_be_searched_and_filtered(): void
    {
        $active = $this->offer(['name' => 'Promo attiva', 'custom_price' => '39.00']);
        $inactive = $this->offer(['name' => 'Promo spenta', 'is_active' => false]);
        $orphan = $this->offer(['name' => 'Promo orfana'], $this->product(['is_active' => false]));

        Livewire::test(ListOffers::class)
            ->assertCanSeeTableRecords([$active, $inactive, $orphan])
            ->assertSee('39,00')
            ->assertSee('Prezzo personalizzato')
            ->assertSee('Disattivata')
            ->assertSee('Prodotto disattivato')
            ->searchTable('Promo attiva')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive, $orphan])
            ->searchTable('')
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_offer_with_orders_cannot_be_deleted(): void
    {
        $offer = $this->offer();
        $this->newOrder($offer);

        Livewire::test(EditOffer::class, ['record' => $offer->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }
}
