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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue(Hash::check('password', $operator->password));

        $panel = Filament::getPanel('admin');
        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($operator->canAccessPanel($panel));

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Offer::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_it_is_idempotent_and_resets_the_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = User::query()->where('email', 'giulia.rossi@leadflow.it')->firstOrFail();
        $operator->update(['password' => 'password-changed-by-user']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, User::query()->count());
        $this->assertSame(2, Role::query()->count());
        $this->assertCount(1, $operator->fresh()->roles);
        $this->assertTrue(Hash::check('password', $operator->fresh()->password));
    }

    public function test_it_keeps_the_other_data_of_an_existing_account(): void
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
        $this->assertTrue(Hash::check('password', $existing->password));
        $this->assertTrue($existing->isAdmin());
    }
}
