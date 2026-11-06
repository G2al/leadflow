<?php

namespace Tests\Feature;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ClaimOrder;
use App\Events\OrderChanged;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use Tests\Support\CreatesLeadflowData;
use Tests\TestCase;

class OrderLiveUpdatesTest extends TestCase
{
    use CreatesLeadflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_a_public_submission_is_announced(): void
    {
        Event::fake([OrderChanged::class]);

        $this->postJson($this->submissionUrl($this->offer()), $this->validSubmission())->assertCreated();

        Event::assertDispatched(OrderChanged::class, fn (OrderChanged $event): bool => $event->orderId === Order::query()->sole()->getKey());
    }

    public function test_every_status_change_is_announced(): void
    {
        $operator = $this->operator();
        $order = $this->newOrder();
        Event::fake([OrderChanged::class]);

        app(ClaimOrder::class)->handle($order, $operator);
        app(CancelOrder::class)->handle($order, $operator);

        Event::assertDispatchedTimes(OrderChanged::class, 2);
    }

    public function test_a_denied_operation_announces_nothing(): void
    {
        $order = $this->claimedOrder($this->operator());
        $second = $this->operator();
        Event::fake([OrderChanged::class]);

        try {
            app(ClaimOrder::class)->handle($order, $second);
        } catch (AuthorizationException) {
            //
        }

        Event::assertNotDispatched(OrderChanged::class);
    }

    public function test_deleting_an_order_is_announced(): void
    {
        $order = $this->newOrder();
        $this->actingOnPanel($this->admin());
        Event::fake([OrderChanged::class]);

        Livewire::test(ListOrders::class)->callAction(TestAction::make('delete')->table($order));

        Event::assertDispatched(OrderChanged::class, fn (OrderChanged $event): bool => $event->orderId === $order->getKey());
    }

    public function test_a_websocket_outage_never_fails_an_order(): void
    {
        Exceptions::fake();
        $operator = $this->operator();
        $order = $this->newOrder();
        Broadcast::shouldReceive('queue')->andThrow(new BroadcastException('Reverb is down'));

        app(ClaimOrder::class)->handle($order, $operator);

        $this->assertSame($operator->getKey(), $order->fresh()->assigned_to);
        Exceptions::assertReported(BroadcastException::class);
    }

    public function test_the_event_uses_a_private_channel_and_carries_only_the_id(): void
    {
        $event = new OrderChanged(12);

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn()[0]);
        $this->assertSame('private-orders', $event->broadcastOn()[0]->name);
        $this->assertSame(['id' => 12], $event->broadcastWith());
    }

    public function test_only_active_staff_can_join_the_channel(): void
    {
        $authorize = Broadcast::driver()->getChannels()->get('orders');

        $this->assertTrue($authorize($this->admin()));
        $this->assertTrue($authorize($this->operator()));
        $this->assertFalse($authorize($this->operator(['is_active' => false])));
        $this->assertFalse($authorize(User::factory()->create()));
    }

    public function test_open_pages_show_the_change_when_notified(): void
    {
        $operator = $this->operator();
        $order = $this->newOrder();
        $this->actingOnPanel($this->admin());

        $list = Livewire::test(ListOrders::class)->assertSee('Prendi in carico');
        $detail = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertSee('Nuovo');

        app(ClaimOrder::class)->handle($order, $operator);

        $list->dispatch('orders-changed')->assertDontSee('Prendi in carico');
        $detail->dispatch('orders-changed')->assertSee('In lavorazione');
    }

    public function test_panel_subscribes_only_for_logged_users_when_configured(): void
    {
        config(['filament.broadcasting.echo' => ['broadcaster' => 'reverb', 'key' => 'test-key']]);

        $subscription = "Echo.private('orders')";

        $this->get('/admin/login')->assertDontSee($subscription, false);

        $this->actingOnPanel($this->operator());
        $this->get(OrderResource::getUrl())->assertSee($subscription, false);

        config(['filament.broadcasting.echo' => null]);
        $this->get(OrderResource::getUrl())->assertDontSee($subscription, false);
    }

    public function test_users_can_join_only_their_own_personal_channel(): void
    {
        $authorize = Broadcast::driver()->getChannels()->get('App.Models.User.{id}');
        $user = $this->operator();

        $this->assertTrue($authorize($user, (string) $user->getKey()));
        $this->assertFalse($authorize($user, (string) ($user->getKey() + 1)));
    }
}
