<?php

namespace Database\Seeders\Demo;

use App\Models\Offer;
use App\Models\Segment;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Database\Seeder;

/** Local demo: two saved lists and two offers (one exported broadcast, one draft). */
class OffersDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Offer::query()->exists()) {
            return;
        }
        $owner = User::role('owner')->orderBy('id')->first();
        if (! $owner) {
            return;
        }

        Segment::create([
            'name' => 'SUV owners (Creta, Seltos, Scorpio-N, XUV700)',
            'filters' => ['models' => ['Creta', 'Seltos', 'Scorpio-N', 'XUV700']],
            'branch_id' => $owner->branch_id,
            'created_by' => $owner->id,
        ]);
        $lapsed = Segment::create([
            'name' => 'Not visited in 6 months',
            'filters' => ['visit' => 'not_in_days', 'visit_days' => 180],
            'branch_id' => $owner->branch_id,
            'created_by' => $owner->id,
        ]);

        $service = app(OfferService::class);

        $diwali = $service->createDraft([
            'title' => 'Diwali LED headlight offer',
            'message' => "Hi {name}! This Diwali, light up your {car} with DHC Gleam 2.0 LED headlights at ₹4,999 fitted (usual ₹5,800). Offer till 15 Nov.\n\nReply YES to book a slot, or visit us at Kawadipat Toll Naka, Loni Kalbhor.\n\nReply STOP to stop offers.",
            'filters' => ['visit' => 'not_in_days', 'visit_days' => 180],
            'segment_id' => $lapsed->id,
        ], $owner);
        $diwali = $service->launch($diwali, 'broadcast', $owner);
        $service->markExported($diwali);
        $diwali->forceFill(['created_at' => now()->subDays(12), 'exported_at' => now()->subDays(12)])->saveQuietly();

        $service->createDraft([
            'title' => 'Ceramic coating follow-up',
            'message' => "Hi {name}, it's Jay Ganesh Car Accessories. Your {car} is due for a ceramic coating check-up. A free inspection and top-up wash this month, with 10% off a 9H ceramic coating renewal.\n\nReply YES and we'll call you to fix a time.\n\nReply STOP to stop offers.",
            'filters' => ['min_spent' => 10000],
        ], $owner);
    }
}
