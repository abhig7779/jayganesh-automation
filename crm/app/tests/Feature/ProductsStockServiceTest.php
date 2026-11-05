<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMove;
use App\Services\Stock\StockService;
use App\Support\Dashboard\LowStock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->stock = app(StockService::class);
});

function stockProduct(array $attrs = []): Product
{
    static $n = 0;
    $n++;

    return Product::create(array_merge(['name' => "Test item $n", 'price_paise' => 49_900], $attrs));
}

it('writes a ledger row and updates on_hand', function () {
    $p = stockProduct();
    $by = staff('admin');

    $move = $this->stock->move($p, 10, 'opening', null, $by, 30_000, 'Opening stock');
    $this->stock->move($p, -3, 'sale', null, $by);

    expect($move->exists)->toBeTrue()
        ->and($move->qty)->toBe(10)
        ->and($move->unit_cost_paise)->toBe(30_000)
        ->and($move->created_by)->toBe($by->id)
        ->and($move->branch_id)->toBe($this->branch->id)
        ->and($p->on_hand)->toBe(7)
        ->and($p->fresh()->on_hand)->toBe(7)
        ->and(StockMove::where('product_id', $p->id)->sum('qty'))->toEqual(7);
});

it('links the move to what caused it and logs activity', function () {
    $p = stockProduct();
    $ref = stockProduct(); // any model works as a reference
    $move = $this->stock->move($p, -1, 'sale', $ref, staff('sales'));

    expect($move->reference_type)->toBe(Product::class)
        ->and($move->reference_id)->toBe($ref->id)
        ->and(DB::table('activity_log')->where('log_name', 'stock')->where('subject_id', $p->id)->exists())->toBeTrue();
});

it('lets stock go negative so a sale is never blocked', function () {
    $p = stockProduct();
    $this->stock->move($p, 1, 'opening');
    $this->stock->move($p, -3, 'sale');

    expect($p->fresh()->on_hand)->toBe(-2)
        ->and($p->fresh()->stockState())->toBe('out');
});

it('never records moves for services', function () {
    $fit = stockProduct(['name' => 'Fitting charge', 'type' => Product::TYPE_SERVICE]);
    $move = $this->stock->move($fit, -1, 'sale');

    expect($move->exists)->toBeFalse()
        ->and(StockMove::count())->toBe(0)
        ->and($fit->fresh()->on_hand)->toBe(0)
        ->and($fit->fresh()->track_stock)->toBeFalse()
        ->and($this->stock->adjust($fit, 5, 'Count', staff('admin')))->toBeNull();
});

it('rejects an unknown move type', function () {
    $this->stock->move(stockProduct(), 1, 'gift');
})->throws(InvalidArgumentException::class);

it('adds to the stored count, not to a stale copy of the product', function () {
    $p = stockProduct();
    $this->stock->move($p, 5, 'opening');
    $staleA = Product::find($p->id);
    $staleB = Product::find($p->id);

    // Two counters selling at once with copies loaded before either sale.
    $this->stock->move($staleA, -1, 'sale');
    $this->stock->move($staleB, -1, 'sale');
    $staleB->update(['name' => 'Renamed']); // a later save must not write the old on_hand back

    expect($p->fresh()->on_hand)->toBe(3);
});

it('rolls back the ledger row and the count together', function () {
    $p = stockProduct();
    $this->stock->move($p, 4, 'opening');

    try {
        DB::transaction(function () use ($p) {
            $this->stock->move($p, -1, 'sale');
            throw new RuntimeException('bill failed to save');
        });
    } catch (RuntimeException) {
    }

    expect($p->fresh()->on_hand)->toBe(4)
        ->and(StockMove::where('product_id', $p->id)->count())->toBe(1);
});

it('adjusts to a counted number with one move for the difference', function () {
    $p = stockProduct();
    $by = staff('admin');
    $this->stock->move($p, 10, 'opening');

    $move = $this->stock->adjust($p, 7, 'Damaged', $by);

    expect($move->qty)->toBe(-3)
        ->and($move->type)->toBe('adjustment')
        ->and($move->note)->toBe('Damaged')
        ->and($p->fresh()->on_hand)->toBe(7)
        ->and($this->stock->adjust($p, 7, 'Count correction', $by))->toBeNull()
        ->and(StockMove::count())->toBe(2);
});

it('rebuilds on_hand from the ledger', function () {
    $p = stockProduct();
    $this->stock->move($p, 6, 'opening');
    $this->stock->move($p, -2, 'sale');
    Product::whereKey($p->id)->update(['on_hand' => 99]); // cache got out of step

    expect($this->stock->recalculate($p))->toBe(4)
        ->and($p->fresh()->on_hand)->toBe(4);
});

it('counts and lists low stock for the home page', function () {
    $low = stockProduct(['name' => 'Low one', 'reorder_level' => 3]);
    $out = stockProduct(['name' => 'Empty one', 'reorder_level' => 1]);
    $fine = stockProduct(['name' => 'Plenty', 'reorder_level' => 2]);
    stockProduct(['name' => 'A service', 'type' => Product::TYPE_SERVICE]);
    stockProduct(['name' => 'Hidden', 'is_active' => false]);
    $this->stock->move($low, 2, 'opening');
    $this->stock->move($fine, 20, 'opening');

    expect(LowStock::count())->toBe(2)
        ->and(LowStock::items(5)->pluck('name')->all())->toBe(['Empty one', 'Low one'])
        ->and(LowStock::items(1))->toHaveCount(1);
});
