<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesLeadflowData;
use Tests\TestCase;

class EmbedTest extends TestCase
{
    use CreatesLeadflowData, RefreshDatabase;

    public function test_script_is_served_as_javascript_with_the_form_url(): void
    {
        $offer = $this->offer();

        $response = $this->get(route('embed.script', $offer));

        $response->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
        $this->assertStringContainsString("/embed/offers/{$offer->public_token}/form", stripslashes(html_entity_decode($response->getContent())));
        $this->assertStringContainsString('window.location.origin + window.location.pathname', $response->getContent());
    }

    public function test_unknown_token_gets_404_everywhere(): void
    {
        $this->get('/embed/offers/unknown.js')->assertNotFound();
        $this->get('/embed/offers/unknown/form')->assertNotFound();
        $this->postJson('/embed/offers/unknown/orders', $this->validSubmission())->assertNotFound();
    }

    public function test_form_can_be_framed_cross_site_and_shows_the_expected_fields(): void
    {
        $offer = $this->offer();

        $response = $this->get(route('embed.form', [$offer, 'source' => 'https://landing.example.com/promo?utm=1']));

        $response->assertOk()
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeaderMissing('Set-Cookie')
            ->assertSee('Invia richiesta')
            ->assertSee('non un acquisto')
            ->assertSee('value="https://landing.example.com/promo"', false);

        $this->assertStringContainsString('frame-ancestors *', $response->headers->get('Content-Security-Policy'));
    }

    public function test_form_shows_the_configured_privacy_link_or_a_placeholder(): void
    {
        $offer = $this->offer();

        $this->get(route('embed.form', $offer))->assertSee('Informativa privacy in fase di configurazione.');

        config(['leadflow.privacy_url' => 'https://example.com/privacy']);

        $this->get(route('embed.form', $offer))->assertSee('https://example.com/privacy');
    }

    public function test_submission_creates_a_new_order_with_price_snapshot_and_source(): void
    {
        $offer = $this->offer(product: $this->product(['selling_price' => '49.90']));

        $this->postJson($this->submissionUrl($offer), $this->validSubmission(['address' => 'Via Roma 1, Milano']))
            ->assertCreated()
            ->assertExactJson(['message' => 'Richiesta inviata con successo. Sarai ricontattato a breve.']);

        $order = Order::query()->sole();

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertTrue($order->offer->is($offer));
        $this->assertNull($order->quantity);
        $this->assertNull($order->total_price);
        $this->assertNull($order->assigned_to);
        $this->assertSame('49.90', $order->unit_price);
        $this->assertSame('+393331234567', $order->phone);
        $this->assertSame('Via Roma 1, Milano', $order->address);
        $this->assertSame('https://landing.example.com/promo', $order->source_url);
    }

    public function test_later_price_changes_do_not_touch_existing_orders(): void
    {
        $product = $this->product(['selling_price' => '49.90']);
        $offer = $this->offer(product: $product);

        $this->postJson($this->submissionUrl($offer), $this->validSubmission())->assertCreated();

        $product->update(['selling_price' => '99.00']);

        $this->assertSame('49.90', Order::query()->sole()->unit_price);
    }

    public function test_same_script_records_different_landings(): void
    {
        $offer = $this->offer();

        $this->postJson($this->submissionUrl($offer), $this->validSubmission(['source_url' => 'https://uno.example.com/a']));
        $this->postJson($this->submissionUrl($offer), $this->validSubmission(['source_url' => 'https://due.example.com/b']));

        $this->assertSame(
            ['https://uno.example.com/a', 'https://due.example.com/b'],
            Order::query()->orderBy('id')->pluck('source_url')->all(),
        );
    }

    public function test_required_fields_are_enforced_and_address_is_optional(): void
    {
        $offer = $this->offer();

        foreach (['first_name', 'last_name', 'phone'] as $field) {
            $this->postJson($this->submissionUrl($offer), $this->validSubmission([$field => '']))
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }

        $this->assertSame(0, Order::query()->count());

        $this->postJson($this->submissionUrl($offer), $this->validSubmission())->assertCreated();

        $this->assertNull(Order::query()->sole()->address);
    }

    public function test_validation_errors_are_json_and_in_italian_even_without_accept_header(): void
    {
        $response = $this->post($this->submissionUrl($this->offer()), []);

        $response->assertUnprocessable();
        $this->assertSame('Il campo nome è obbligatorio.', $response->json('errors.first_name.0'));
    }

    public function test_phone_validation_is_permissive_but_safe(): void
    {
        $offer = $this->offer();

        foreach (['333 1234567', '+39 (333) 123-4567', '02/1234567'] as $phone) {
            $this->postJson($this->submissionUrl($offer), $this->validSubmission(['phone' => $phone]))->assertCreated();
        }

        foreach (['abc', '123', '<script>alert(1)</script>', str_repeat('1', 31)] as $phone) {
            $this->postJson($this->submissionUrl($offer), $this->validSubmission(['phone' => $phone]))
                ->assertJsonValidationErrors(['phone']);
        }
    }

    public function test_duplicate_submissions_create_separate_orders(): void
    {
        $offer = $this->offer();

        $this->postJson($this->submissionUrl($offer), $this->validSubmission())->assertCreated();
        $this->postJson($this->submissionUrl($offer), $this->validSubmission())->assertCreated();

        $this->assertSame(2, Order::query()->count());
    }

    public function test_inactive_offer_blocks_the_form_and_the_post(): void
    {
        $offer = $this->offer();

        // The iframe was already open when the offer got deactivated.
        $this->get(route('embed.form', $offer))->assertOk();
        $offer->update(['is_active' => false]);

        $this->get(route('embed.form', $offer))
            ->assertStatus(410)
            ->assertDontSee('Invia richiesta')
            ->assertSee('non è disponibile');

        $this->postJson($this->submissionUrl($offer), $this->validSubmission())
            ->assertStatus(410)
            ->assertJson(['message' => 'Questo modulo non è più disponibile.']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_inactive_product_blocks_the_form_and_the_post(): void
    {
        $product = $this->product(['is_active' => false]);
        $offer = $this->offer(product: $product);

        $this->get(route('embed.form', $offer))->assertStatus(410);
        $this->postJson($this->submissionUrl($offer), $this->validSubmission())->assertStatus(410);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_reserved_fields_cannot_be_set_by_the_client(): void
    {
        $operator = $this->seedRolesAndOperator();
        $offer = $this->offer(product: $this->product(['selling_price' => '49.90']));

        $this->postJson($this->submissionUrl($offer), $this->validSubmission([
            'status' => 'confermato',
            'assigned_to' => $operator->getKey(),
            'unit_price' => '0.01',
            'total_price' => '0.01',
            'quantity' => 100,
            'offer_id' => 999,
            'id' => 12345,
        ]))->assertCreated();

        $order = Order::query()->sole();

        $this->assertNotSame(12345, $order->getKey());
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertNull($order->assigned_to);
        $this->assertSame('49.90', $order->unit_price);
        $this->assertNull($order->total_price);
        $this->assertNull($order->quantity);
        $this->assertTrue($order->offer->is($offer));
    }

    public function test_honeypot_answers_success_without_creating_an_order(): void
    {
        $this->postJson($this->submissionUrl($this->offer()), $this->validSubmission(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_unsafe_source_urls_become_null_without_losing_the_request(): void
    {
        $offer = $this->offer();

        foreach (['javascript:alert(1)', 'https://x.example/<script>alert(1)</script>', 'not a url', 'ftp://x.example/a', ''] as $url) {
            $this->postJson($this->submissionUrl($offer), $this->validSubmission(['source_url' => $url]))->assertCreated();
        }

        $this->postJson($this->submissionUrl($offer), $this->validSubmission(['source_url' => ['array']]))->assertCreated();

        $this->assertSame(6, Order::query()->whereNull('source_url')->count());
    }

    public function test_source_url_in_the_form_page_is_never_reflected_raw(): void
    {
        $this->get(route('embed.form', [$this->offer(), 'source' => '"><script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('alert(1)', false);
    }

    public function test_submissions_are_rate_limited_by_ip(): void
    {
        $offer = $this->offer();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson($this->submissionUrl($offer), $this->validSubmission())->assertCreated();
        }

        $this->postJson($this->submissionUrl($offer), $this->validSubmission())
            ->assertTooManyRequests()
            ->assertJson(['message' => 'Troppe richieste. Riprova tra qualche minuto.']);
    }

    public function test_public_routes_do_not_start_a_session(): void
    {
        $offer = $this->offer();

        foreach ([route('embed.script', $offer), route('embed.form', $offer)] as $url) {
            $this->assertEmpty($this->get($url)->headers->getCookies());
        }

        $this->assertEmpty($this->postJson($this->submissionUrl($offer), $this->validSubmission())->headers->getCookies());
    }

    private function seedRolesAndOperator()
    {
        $this->seedRoles();

        return $this->operator();
    }
}
