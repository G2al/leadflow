<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real race on real row locks: several OS processes claim the same order at the
 * same instant. It needs MySQL, so it runs only when a dedicated database is named:
 *
 *   LEADFLOW_CONCURRENCY_DB=leadflow_test php artisan test --filter=OrderConcurrencyTest
 *
 * The database is created if missing and only ever receives additive migrations.
 */
class OrderConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    private const ROUNDS = 5;

    public function test_only_one_of_many_simultaneous_claims_wins(): void
    {
        $database = getenv('LEADFLOW_CONCURRENCY_DB');

        if (! $database) {
            $this->markTestSkipped('Imposta LEADFLOW_CONCURRENCY_DB per eseguire il test su MySQL.');
        }

        $this->connectTo($database);

        $operators = collect(range(1, self::WORKERS))->map(fn (int $index): User => $this->operator($index));

        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $order = $this->newOrder();
            $outcomes = $this->race($order, $operators->all(), $database);

            $order->refresh();
            $this->assertSame(1, $outcomes->filter(fn (string $outcome): bool => $outcome === 'claimed')->count(), $outcomes->implode(', '));
            $this->assertSame(self::WORKERS - 1, $outcomes->filter(fn (string $outcome): bool => $outcome === 'rejected')->count(), $outcomes->implode(', '));
            $this->assertSame(OrderStatus::InProgress, $order->status);
            $this->assertNotNull($order->assigned_to);
        }
    }

    private function connectTo(string $database): void
    {
        $applicationDatabase = Dotenv::parse((string) file_get_contents(base_path('.env')))['DB_DATABASE'] ?? null;
        $this->assertNotSame($applicationDatabase, $database, 'Usa un database dedicato ai test.');

        config(['database.default' => 'mysql', 'database.connections.mysql.database' => null]);
        DB::purge('mysql');
        DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$database}`");

        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');

        Artisan::call('migrate', ['--force' => true]);
        $this->seed(RoleSeeder::class);
    }

    /**
     * @param  list<User>  $operators
     * @return Collection<int, string>
     */
    private function race(Order $order, array $operators, string $database): Collection
    {
        $startAt = microtime(true) + 3;

        $processes = collect($operators)->map(function (User $operator) use ($order, $startAt, $database): Process {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/claim-order-worker.php'), $order->getKey(), $operator->getKey(), (string) $startAt],
                base_path(),
                ['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'APP_ENV' => 'testing'],
            );
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): string {
            $process->wait();

            return trim($process->getOutput()) ?: 'failed: '.$process->getErrorOutput();
        });
    }

    private function operator(int $index): User
    {
        return User::factory()
            ->create(['email' => uniqid("worker{$index}.", true).'@leadflow.test'])
            ->assignRole(UserRole::CallCenter);
    }

    private function newOrder(): Order
    {
        $product = Product::query()->create([
            'name' => 'Concurrency',
            'sku' => uniqid('CONC-', true),
            'purchase_price' => 1,
            'selling_price' => 2,
        ]);
        $offer = Offer::query()->create(['name' => 'Concurrency', 'product_id' => $product->getKey()]);

        $order = new Order(['first_name' => 'Race', 'last_name' => 'Test', 'phone' => '3331234567']);
        $order->offer()->associate($offer);
        $order->unit_price = $offer->effectivePrice();
        $order->save();

        return $order;
    }
}
