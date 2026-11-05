<?php

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 18:00', 'Asia/Kolkata'));
    $this->cat = ExpenseCategory::query()->pluck('id', 'name');
});

it('seeds the nine expense categories', function () {
    expect(ExpenseCategory::query()->active()->pluck('name')->all())
        ->toBe(['Rent', 'Electricity', 'Salaries', 'Tea & snacks', 'Transport', 'Supplies', 'Marketing', 'Repairs', 'Other']);
});

it('lets finance add an expense with a private receipt photo', function () {
    Storage::fake('local');
    $u = staff('finance');

    $this->actingAs($u)->post(route('finance.expenses.store'), [
        'date' => '2026-11-05', 'expense_category_id' => $this->cat['Tea & snacks'], 'amount' => '1,250.50',
        'mode' => 'cash', 'paid_to' => 'Shree Tea Stall', 'note' => 'Tea for the week',
        'receipt' => UploadedFile::fake()->image('bill.jpg'),
    ])->assertRedirect(route('finance.expenses.index', ['preset' => 'this_month']))->assertSessionHas('status');

    $e = Expense::query()->sole();
    expect($e->amount_paise)->toBe(125050)
        ->and($e->date->toDateString())->toBe('2026-11-05')
        ->and($e->getRawOriginal('date'))->toBe('2026-11-05')
        ->and($e->mode)->toBe('cash')
        ->and($e->created_by)->toBe($u->id)
        ->and($e->branch_id)->toBe($this->branch->id);
    Storage::disk('local')->assertExists($e->receipt_path);
    expect($e->receipt_path)->toStartWith('expenses/');

    $this->actingAs($u)->get(route('finance.expenses.receipt', $e))->assertOk();
});

it('rejects bad amounts, future dates and a missing category', function () {
    $u = staff('finance');
    $this->actingAs($u)->post(route('finance.expenses.store'), [
        'date' => '2026-11-06', 'amount' => 'abc', 'mode' => 'cash',
    ])->assertSessionHasErrors(['date', 'amount', 'expense_category_id']);

    $this->actingAs($u)->post(route('finance.expenses.store'), [
        'date' => '2026-11-05', 'amount' => '0', 'mode' => 'cash', 'expense_category_id' => $this->cat['Rent'],
    ])->assertSessionHasErrors('amount');

    expect(Expense::count())->toBe(0);
});

it('edits an expense and cancels it instead of deleting', function () {
    $u = staff('finance');
    $this->actingAs($u)->post(route('finance.expenses.store'), [
        'date' => '2026-11-04', 'expense_category_id' => $this->cat['Transport'], 'amount' => '400', 'mode' => 'upi',
    ]);
    $e = Expense::query()->sole();

    $this->actingAs($u)->put(route('finance.expenses.update', $e), [
        'date' => '2026-11-04', 'expense_category_id' => $this->cat['Transport'], 'amount' => '450', 'mode' => 'cash',
    ])->assertRedirect();
    expect($e->fresh()->amount_paise)->toBe(45000)->and($e->fresh()->mode)->toBe('cash');

    $this->actingAs($u)->post(route('finance.expenses.cancel', $e))->assertRedirect();
    expect($e->fresh()->cancelled_at)->not->toBeNull()
        ->and(Expense::query()->count())->toBe(1)
        ->and(Expense::query()->live()->count())->toBe(0);

    // A cancelled expense can't be edited any more.
    $this->actingAs($u)->get(route('finance.expenses.edit', $e))->assertNotFound();
});

it('lists expenses with filters and totals that skip cancelled rows', function () {
    $u = staff('finance');
    $mk = fn ($date, $cat, $paise, $mode, $paidTo = null) => Expense::create([
        'branch_id' => $this->branch->id, 'date' => $date, 'expense_category_id' => $this->cat[$cat],
        'amount_paise' => $paise, 'mode' => $mode, 'paid_to' => $paidTo, 'created_by' => $u->id,
    ]);
    $mk('2026-11-05', 'Tea & snacks', 12000, 'cash', 'Shree Tea Stall');
    $mk('2026-11-03', 'Electricity', 487000, 'upi', 'MSEDCL');
    $mk('2026-11-01', 'Rent', 3500000, 'bank');
    $mk('2026-10-28', 'Rent', 3500000, 'bank'); // last month, outside "This month"
    $mk('2026-11-02', 'Supplies', 99900, 'cash')->forceFill(['cancelled_at' => now()])->save();

    $res = $this->actingAs($u)->get(route('finance.expenses.index', ['preset' => 'this_month']))->assertOk();
    expect($res->viewData('totals')['total_paise'])->toBe(12000 + 487000 + 3500000)
        ->and($res->viewData('totals')['count'])->toBe(3)
        ->and($res->viewData('totals')['modes'])->toEqual(['bank' => 3500000, 'cash' => 12000, 'upi' => 487000]);
    $res->assertSee('₹39,990')->assertSee('MSEDCL');

    $res = $this->actingAs($u)->get(route('finance.expenses.index', ['preset' => 'this_month', 'category' => $this->cat['Rent']]));
    expect($res->viewData('totals')['total_paise'])->toBe(3500000);

    $res = $this->actingAs($u)->get(route('finance.expenses.index', ['preset' => 'this_month', 'mode' => 'cash', 'q' => 'tea']));
    expect($res->viewData('expenses')->total())->toBe(1);

    $res = $this->actingAs($u)->get(route('finance.expenses.index', ['preset' => 'custom', 'from' => '2026-10-01', 'to' => '2026-11-05']));
    expect($res->viewData('totals')['total_paise'])->toBe(12000 + 487000 + 3500000 + 3500000);
});

it('shows a helpful empty state', function () {
    $this->actingAs(staff('finance'))->get(route('finance.expenses.index'))
        ->assertOk()->assertSee('No expenses in this period.');
});

it('lets the admin look but not add, and keeps sales and technicians out', function () {
    $admin = staff('admin');
    $this->actingAs($admin)->get(route('finance.expenses.index'))->assertOk()->assertDontSee('Quick add');
    $this->actingAs($admin)->get(route('finance.expenses.create'))->assertForbidden();
    $this->actingAs($admin)->post(route('finance.expenses.store'), [
        'date' => '2026-11-05', 'expense_category_id' => $this->cat['Rent'], 'amount' => '100', 'mode' => 'cash',
    ])->assertForbidden();

    foreach (['sales', 'technician'] as $role) {
        $u = staff($role);
        $this->actingAs($u)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($u)->get(route('finance.expenses.index'))->assertForbidden();
        $this->actingAs($u)->get(route('finance.cash-close'))->assertForbidden();
        $this->actingAs($u)->get(route('finance.dues'))->assertForbidden();
        $this->actingAs($u)->get(route('reports.index'))->assertForbidden();
    }
    expect(Expense::count())->toBe(0);
});

it('renders the finance pages for finance and owner', function () {
    foreach (['finance', 'owner'] as $role) {
        $u = staff($role);
        $this->actingAs($u)->get(route('finance.index'))->assertOk()->assertSee('Money in today')->assertSee('Bills module not installed yet');
        $this->actingAs($u)->get(route('finance.expenses.create'))->assertOk()->assertSee('Save expense');
        $this->actingAs($u)->get(route('finance.cash-close'))->assertOk()->assertSee('Save cash close');
        $this->actingAs($u)->get(route('finance.cash-closes.index'))->assertOk();
        $this->actingAs($u)->get(route('finance.dues'))->assertOk()->assertSee('Bills module not installed yet');
    }
});
