<?php

/*
 * Audit H4: Marketing has `reports.view` for product trends only. It must not open or download
 * the sales/expense registers, GST, CA or shop-wide daily sales.
 */

use App\Models\Branch;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FinanceFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 18:00', 'Asia/Kolkata'));
    FinanceFixture::build($this->branch->id);
});

it('blocks Marketing from the sales and expense registers', function (string $type) {
    $this->actingAs(staff('marketing'))->get(route('reports.register', ['type' => $type]))->assertForbidden();
})->with(['sales', 'expenses']);

it('blocks Marketing from the GST, CA and daily sales reports and their exports', function (string $report) {
    $m = staff('marketing');
    $this->actingAs($m)->get(route('reports.index', ['report' => $report]))->assertForbidden();
    $this->actingAs($m)->get(route('reports.export', ['report' => $report]))->assertForbidden();
})->with(['gst', 'ca', 'daily', 'staff', 'pnl']);

it('opens Top products for Marketing by default and lists nothing else', function () {
    $this->actingAs(staff('marketing'))->get(route('reports.index'))->assertOk()
        ->assertSee('Top products')
        ->assertDontSee('GST summary')->assertDontSee('CA / Tally export')->assertDontSee('Daily sales')
        ->assertDontSee('register');
    $this->actingAs(staff('marketing'))->get(route('reports.export', ['report' => 'products']))->assertOk();
});

it('still gives Finance, Admin and Owner the registers and tax reports', function (string $role) {
    $u = staff($role);
    $this->actingAs($u)->get(route('reports.register', ['type' => 'sales']))->assertOk();
    $this->actingAs($u)->get(route('reports.register', ['type' => 'expenses']))->assertOk();
    $this->actingAs($u)->get(route('reports.index', ['report' => 'gst']))->assertOk();
    $this->actingAs($u)->get(route('reports.index', ['report' => 'daily']))->assertOk();
})->with(['finance', 'admin', 'owner']);
