<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\CreatesLeadflowData;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use CreatesLeadflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_admin_creates_an_operator_with_hashed_password(): void
    {
        $this->actingOnPanel($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Anna',
                'surname' => 'Verdi',
                'email' => 'anna.verdi@leadflow.it',
                'role' => 'call_center',
                'password' => 'una-password-robusta',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'anna.verdi@leadflow.it')->firstOrFail();

        $this->assertTrue($user->isCallCenter());
        $this->assertTrue(Hash::check('una-password-robusta', $user->password));
    }

    public function test_admin_changes_role_and_deactivates_without_touching_the_password(): void
    {
        $operator = $this->operator(['password' => 'password-originale']);
        $this->actingOnPanel($this->admin());

        Livewire::test(EditUser::class, ['record' => $operator->getRouteKey()])
            ->assertSchemaStateSet(['role' => UserRole::CallCenter])
            ->fillForm(['role' => 'admin', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $operator->refresh();
        $this->assertTrue($operator->isAdmin());
        $this->assertFalse($operator->isCallCenter());
        $this->assertFalse($operator->is_active);
        $this->assertTrue(Hash::check('password-originale', $operator->password));
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->admin();
        $this->actingOnPanel($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertNotified();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivating_a_user_keeps_their_orders(): void
    {
        $operator = $this->operator();
        $order = $this->claimedOrder($operator);

        $operator->update(['is_active' => false]);

        $this->assertSame($operator->getKey(), $order->fresh()->assigned_to);
    }

    public function test_role_command_assigns_a_role_explicitly(): void
    {
        $user = User::factory()->create(['email' => 'legacy@leadflow.it']);

        $this->artisan('leadflow:assign-role', ['email' => 'legacy@leadflow.it', 'role' => 'admin'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());

        $this->artisan('leadflow:assign-role', ['email' => 'legacy@leadflow.it', 'role' => 'nope'])->assertFailed();
        $this->artisan('leadflow:assign-role', ['email' => 'unknown@leadflow.it', 'role' => 'admin'])->assertFailed();
    }
}
