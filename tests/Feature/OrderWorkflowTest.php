<?php

namespace Tests\Feature;

use App\Actions\Orders\AssignOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ClaimOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\UpdateOrderDetails;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesLeadflowData;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use CreatesLeadflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function details(array $overrides = []): array
    {
        return [
            'first_name' => 'Mario',
            'last_name' => 'Bianchi',
            'phone' => '333 1234567',
            'address' => 'Via Roma 1',
            'quantity' => 3,
            ...$overrides,
        ];
    }

    public function test_table_can_be_searched_by_phone_and_summarises_the_order_in_each_row(): void
    {
        $operator = $this->operator();
        $match = $this->claimedOrder($operator, ['phone' => '+393471112222']);
        $other = $this->newOrder(attributes: ['phone' => '+393339998888']);
        app(UpdateOrderDetails::class)->handle($match, $this->details(['phone' => '+393471112222']), $operator);
        $this->actingOnPanel($operator);

        Livewire::test(ListOrders::class)
            ->assertSee('Q.tà da definire')
            ->assertSee('149,70')
            ->searchTable('+393471112222')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_every_operator_sees_all_orders(): void
    {
        $operator = $this->operator();
        $colleague = $this->operator();
        $new = $this->newOrder();
        $mine = $this->claimedOrder($operator);
        $theirs = $this->claimedOrder($colleague);
        $this->actingOnPanel($operator);

        Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$new, $mine, $theirs]);
    }

    public function test_first_claim_assigns_the_order_from_the_table(): void
    {
        $operator = $this->operator();
        $order = $this->newOrder();
        $this->actingOnPanel($operator);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('claim')->table($order))
            ->assertNotified('Ordine preso in carico.');

        $order->refresh();
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame($operator->getKey(), $order->assigned_to);
    }

    public function test_second_operator_cannot_claim_or_change_the_order(): void
    {
        $first = $this->operator();
        $second = $this->operator();
        $order = $this->claimedOrder($first);
        $this->actingOnPanel($second);

        Livewire::test(ListOrders::class)
            ->assertActionHidden(TestAction::make('claim')->table($order))
            ->assertActionHidden(TestAction::make('edit')->table($order))
            ->assertActionHidden(TestAction::make('confirm')->table($order))
            ->assertActionHidden(TestAction::make('cancel')->table($order));

        $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();

        foreach ([
            fn () => app(ClaimOrder::class)->handle($order, $second),
            fn () => app(UpdateOrderDetails::class)->handle($order, $this->details(), $second),
            fn () => app(ConfirmOrder::class)->handle($order, $second),
            fn () => app(CancelOrder::class)->handle($order, $second),
        ] as $attempt) {
            $this->assertDenied($attempt);
        }

        $order->refresh();
        $this->assertSame($first->getKey(), $order->assigned_to);
        $this->assertSame(OrderStatus::InProgress, $order->status);
    }

    public function test_a_stale_copy_of_the_order_cannot_be_claimed_twice(): void
    {
        $first = $this->operator();
        $second = $this->operator();
        $order = $this->newOrder();
        $stale = Order::query()->findOrFail($order->getKey());

        app(ClaimOrder::class)->handle($order, $first);

        // $stale still says "nuovo": the check must run on the locked, fresh row.
        $this->assertDenied(fn () => app(ClaimOrder::class)->handle($stale, $second), 'Questo ordine è già stato preso in carico.');
        $this->assertSame($first->getKey(), $order->fresh()->assigned_to);
    }

    public function test_assigned_operator_edits_data_and_total_is_recalculated(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        $this->actingOnPanel($operator);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm($this->details(['first_name' => 'Luigi', 'quantity' => 3]))
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertSame('Luigi', $order->first_name);
        $this->assertSame('3331234567', $order->phone);
        $this->assertSame(3, $order->quantity);
        $this->assertSame('49.90', $order->unit_price);
        $this->assertSame('149.70', $order->total_price);
    }

    public function test_unit_price_cannot_be_changed_by_the_operator(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);

        app(UpdateOrderDetails::class)->handle($order, $this->details(['unit_price' => '0.01', 'total_price' => '0.01', 'status' => 'confermato']), $operator);

        $order->refresh();
        $this->assertSame('49.90', $order->unit_price);
        $this->assertSame('149.70', $order->total_price);
        $this->assertSame(OrderStatus::InProgress, $order->status);

        $this->actingOnPanel($operator);
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldDoesNotExist('unit_price');
    }

    public function test_quantity_is_validated(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        $this->actingOnPanel($operator);

        foreach ([0, -1, 1000, 'abc'] as $quantity) {
            Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
                ->fillForm($this->details(['quantity' => $quantity]))
                ->call('save')
                ->assertHasFormErrors(['quantity']);
        }
    }

    public function test_confirmation_requires_quantity_and_address(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);

        try {
            app(ConfirmOrder::class)->handle($order, $operator);
            $this->fail('Confirmation should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['quantity', 'address'], array_keys($exception->errors()));
        }

        app(UpdateOrderDetails::class)->handle($order, $this->details(['address' => null]), $operator);
        $this->expectException(ValidationException::class);
        app(ConfirmOrder::class)->handle($order, $operator);
    }

    public function test_confirmation_modal_requires_quantity_and_address(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        $this->actingOnPanel($operator);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('confirm')
            ->assertHasFormErrors(['quantity' => 'required', 'address' => 'required']);

        $this->assertSame(OrderStatus::InProgress, $order->fresh()->status);
    }

    public function test_confirmation_modal_rejects_invalid_quantities(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        $this->actingOnPanel($operator);

        foreach ([0, -1, 1000, 'abc'] as $quantity) {
            Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
                ->callAction('confirm', ['quantity' => $quantity, 'address' => 'Via Roma 1'])
                ->assertHasFormErrors(['quantity']);
        }

        $this->assertSame(OrderStatus::InProgress, $order->fresh()->status);
    }

    public function test_operator_completes_quantity_and_address_in_the_confirmation_modal(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        $this->actingOnPanel($operator);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('confirm')->table($order), ['quantity' => 3, 'address' => 'Via Roma 1, Milano'])
            ->assertHasNoFormErrors()
            ->assertNotified('Ordine confermato.');

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(3, $order->quantity);
        $this->assertSame('Via Roma 1, Milano', $order->address);
        $this->assertSame('49.90', $order->unit_price);
        $this->assertSame('149.70', $order->total_price);
    }

    public function test_total_is_exact_for_decimal_prices(): void
    {
        $order = $this->newOrder();

        $this->assertSame('199.60', $order->totalFor(4));
        $this->assertSame('49850.10', $order->totalFor(999));
        $this->assertNull($order->totalFor(null));
    }

    public function test_confirm_details_cannot_touch_other_fields(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);

        app(ConfirmOrder::class)->handle($order, $operator, [
            'quantity' => 2,
            'address' => 'Via Roma 1',
            'unit_price' => '0.01',
            'assigned_to' => null,
        ]);

        $order->refresh();
        $this->assertSame('49.90', $order->unit_price);
        $this->assertSame('99.80', $order->total_price);
        $this->assertSame($operator->getKey(), $order->assigned_to);
    }

    public function test_a_stale_confirmation_cannot_save_details_on_someone_elses_order(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);

        $this->assertDenied(fn () => app(ConfirmOrder::class)->handle($order, $this->operator(), [
            'quantity' => 9,
            'address' => 'Via Altrove 2',
        ]));

        $this->assertNull($order->fresh()->quantity);
        $this->assertNull($order->fresh()->address);
    }

    public function test_assigned_operator_confirms_with_correct_total(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        app(UpdateOrderDetails::class)->handle($order, $this->details(), $operator);
        $this->actingOnPanel($operator);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('confirm')
            ->assertNotified('Ordine confermato.')
            ->assertActionHidden('confirm')
            ->assertActionHidden('cancel');

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('149.70', $order->total_price);
    }

    public function test_cancellation_does_not_require_quantity(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        $this->actingOnPanel($operator);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('cancel');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_a_new_order_cannot_jump_directly_to_a_final_state(): void
    {
        $admin = $this->admin();
        $order = $this->newOrder();

        $this->assertDenied(fn () => app(ConfirmOrder::class)->handle($order, $admin));
        $this->assertDenied(fn () => app(CancelOrder::class)->handle($order, $admin));
    }

    public function test_final_orders_are_read_only_for_everyone(): void
    {
        $operator = $this->operator();
        $admin = $this->admin();
        $confirmed = $this->claimedOrder($operator);
        app(UpdateOrderDetails::class)->handle($confirmed, $this->details(), $operator);
        app(ConfirmOrder::class)->handle($confirmed, $operator);
        $cancelled = $this->claimedOrder($operator);
        app(CancelOrder::class)->handle($cancelled, $operator);

        foreach ([$confirmed, $cancelled] as $order) {
            foreach ([$operator, $admin] as $user) {
                $this->assertDenied(fn () => app(UpdateOrderDetails::class)->handle($order, $this->details(), $user));
                $this->assertDenied(fn () => app(ConfirmOrder::class)->handle($order, $user));
                $this->assertDenied(fn () => app(CancelOrder::class)->handle($order, $user));
                $this->assertDenied(fn () => app(ClaimOrder::class)->handle($order, $user));
            }

            $this->assertDenied(fn () => app(AssignOrder::class)->handle($order, $operator, $admin));

            $this->actingOnPanel($operator);
            $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk();
            $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();
        }
    }

    public function test_assignee_is_hidden_from_operators_and_shown_to_admins(): void
    {
        $colleague = $this->operator(['name' => 'Nomeriservato', 'surname' => 'Cognomeriservato']);
        $order = $this->claimedOrder($colleague);

        $this->actingOnPanel($this->operator());
        $this->get(OrderResource::getUrl())->assertOk()->assertDontSee('Nomeriservato');
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk()->assertDontSee('Nomeriservato');
        Livewire::test(ListOrders::class)->assertDontSee('Nomeriservato');

        $this->actingOnPanel($this->admin());
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertSee('Nomeriservato');
        Livewire::test(ListOrders::class)->assertSee('Nomeriservato');
    }

    public function test_admin_reassigns_and_can_assign_new_orders(): void
    {
        $admin = $this->admin();
        $first = $this->operator();
        $second = $this->operator();
        $order = $this->claimedOrder($first);
        $new = $this->newOrder();
        $this->actingOnPanel($admin);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('assign', ['assigned_to' => $second->getKey()])
            ->assertNotified('Ordine assegnato.');

        $this->assertSame($second->getKey(), $order->fresh()->assigned_to);

        app(AssignOrder::class)->handle($new, $first, $admin);

        $this->assertSame(OrderStatus::InProgress, $new->fresh()->status);
        $this->assertSame($first->getKey(), $new->fresh()->assigned_to);
    }

    public function test_operators_cannot_reassign_and_inactive_users_cannot_receive_orders(): void
    {
        $admin = $this->admin();
        $operator = $this->operator();
        $inactive = $this->operator(['is_active' => false]);
        $order = $this->claimedOrder($operator);

        $this->assertDenied(fn () => app(AssignOrder::class)->handle($order, $operator, $operator));

        $this->expectException(ValidationException::class);
        app(AssignOrder::class)->handle($order, $inactive, $admin);
    }

    public function test_admin_can_work_an_order_assigned_to_someone_else(): void
    {
        $admin = $this->admin();
        $order = $this->claimedOrder($this->operator());

        app(CancelOrder::class)->handle($order, $admin);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_orders_cannot_be_created_from_the_panel(): void
    {
        $this->actingOnPanel($this->admin());

        $this->assertFalse(auth()->user()->can('create', Order::class));
    }

    public function test_admin_deletes_an_order_from_the_table_in_any_state(): void
    {
        $operator = $this->operator();
        $new = $this->newOrder();
        $inProgress = $this->claimedOrder($operator);
        $confirmed = $this->claimedOrder($operator);
        app(ConfirmOrder::class)->handle($confirmed, $operator, ['quantity' => 1, 'address' => 'Via Roma 1']);
        $cancelled = $this->claimedOrder($operator);
        app(CancelOrder::class)->handle($cancelled, $operator);
        $this->actingOnPanel($this->admin());

        foreach ([$new, $inProgress, $confirmed, $cancelled] as $order) {
            Livewire::test(ListOrders::class)
                ->callAction(TestAction::make('delete')->table($order))
                ->assertNotified('Ordine eliminato.');

            $this->assertModelMissing($order);
        }
    }

    public function test_admin_deletes_an_order_from_the_detail_page_and_returns_to_the_list(): void
    {
        $order = $this->newOrder();
        $this->actingOnPanel($this->admin());

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect(OrderResource::getUrl());

        $this->assertModelMissing($order);
    }

    public function test_deleting_an_order_keeps_the_offer(): void
    {
        $offer = $this->offer();
        $order = $this->newOrder($offer);
        $this->actingOnPanel($this->admin());

        Livewire::test(ListOrders::class)->callAction(TestAction::make('delete')->table($order));

        $this->assertModelExists($offer);
    }

    public function test_operators_cannot_delete_orders_even_their_own(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);
        $this->actingOnPanel($operator);

        $this->assertFalse($operator->can('delete', $order));

        Livewire::test(ListOrders::class)->assertActionHidden(TestAction::make('delete')->table($order));
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden('delete');

        $this->assertModelExists($order);
    }

    public function test_panel_explains_when_an_item_no_longer_exists(): void
    {
        $this->assertSame(
            'Elemento non più disponibile',
            Filament::getPanel('admin')->getErrorNotifications()[404]['title'],
        );
    }

    public function test_customer_values_are_escaped_in_the_interface(): void
    {
        $order = $this->newOrder(attributes: ['first_name' => '<script>alert("xss")</script>']);
        $order->source_url = 'https://landing.example.com/promo';
        $order->save();
        $this->actingOnPanel($this->admin());

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);

        $this->get(OrderResource::getUrl())->assertOk()->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_order_without_source_is_labelled_as_unavailable(): void
    {
        $order = $this->newOrder();
        $this->actingOnPanel($this->admin());

        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertSee('Origine non disponibile');
    }

    private function assertDenied(callable $attempt, ?string $message = null): void
    {
        try {
            $attempt();
            $this->fail('The operation should have been denied.');
        } catch (AuthorizationException $exception) {
            if ($message !== null) {
                $this->assertSame($message, $exception->getMessage());
            }

            $this->addToAssertionCount(1);
        }
    }
}
