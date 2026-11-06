<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\CreatesLeadflowData;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use CreatesLeadflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->actingOnPanel($this->admin());
    }

    private function validData(array $overrides = []): array
    {
        return [
            'name' => 'Crema viso',
            'sku' => 'CREMA-01',
            'purchase_price' => '5.50',
            'selling_price' => '29.90',
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_admin_creates_a_product(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->validData())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('29.90', Product::query()->where('sku', 'CREMA-01')->firstOrFail()->selling_price);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $this->product(['sku' => 'CREMA-01']);

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validData())
            ->call('create')
            ->assertHasFormErrors(['sku' => 'unique']);

        $this->assertSame(1, Product::query()->count());
    }

    public function test_invalid_prices_are_rejected(): void
    {
        foreach (['-1', 'abc', '', '100000000'] as $price) {
            Livewire::test(CreateProduct::class)
                ->fillForm($this->validData(['selling_price' => $price]))
                ->call('create')
                ->assertHasFormErrors(['selling_price']);
        }

        $this->assertSame(0, Product::query()->count());
    }

    public function test_table_searches_by_name_and_sku(): void
    {
        $match = $this->product(['name' => 'Siero', 'sku' => 'ABC-1']);
        $other = $this->product(['name' => 'Tonico', 'sku' => 'XYZ-2']);

        Livewire::test(ListProducts::class)
            ->searchTable('ABC-1')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_product_with_offers_cannot_be_deleted(): void
    {
        $product = $this->product();
        $this->offer(product: $product);

        $this->assertTrue(Gate::denies('delete', $product));

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_product_without_offers_can_be_deleted(): void
    {
        $product = $this->product();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($product);
    }
}
