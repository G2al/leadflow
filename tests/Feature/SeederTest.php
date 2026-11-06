<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'leadflow.demo.admin_password' => 'admin-secret-for-test',
            'leadflow.demo.call_center_password' => 'operator-secret-for-test',
        ]);
    }

    public function test_it_creates_only_roles_and_the_two_demo_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['admin', 'call_center'], Role::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(2, User::query()->count());

        $admin = User::query()->where('email', 'admin@leadflow.it')->firstOrFail();
        $operator = User::query()->where('email', 'giulia.rossi@leadflow.it')->firstOrFail();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertTrue($operator->isCallCenter());
        $this->assertTrue($operator->is_active);
        $this->assertTrue(Hash::check('operator-secret-for-test', $operator->password));

        $panel = Filament::getPanel('admin');
        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($operator->canAccessPanel($panel));

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Offer::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_it_is_idempotent_and_keeps_changed_passwords(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = User::query()->where('email', 'giulia.rossi@leadflow.it')->firstOrFail();
        $operator->update(['password' => 'password-changed-by-user']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, User::query()->count());
        $this->assertSame(2, Role::query()->count());
        $this->assertCount(1, $operator->fresh()->roles);
        $this->assertTrue(Hash::check('password-changed-by-user', $operator->fresh()->password));
    }

    public function test_it_preserves_a_preexisting_admin_account(): void
    {
        $existing = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@leadflow.it',
            'password' => 'original-password',
        ]);

        $this->seed(DatabaseSeeder::class);

        $existing->refresh();
        $this->assertSame('admin', $existing->name);
        $this->assertSame('Admin', $existing->surname);
        $this->assertTrue(Hash::check('original-password', $existing->password));
        $this->assertTrue($existing->isAdmin());
    }

    public function test_it_fails_clearly_when_passwords_are_missing(): void
    {
        config(['leadflow.demo.call_center_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LEADFLOW_DEMO_CALL_CENTER_PASSWORD');

        $this->seed(DatabaseSeeder::class);
    }
}
