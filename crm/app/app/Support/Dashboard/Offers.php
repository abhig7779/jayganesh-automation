<?php

namespace App\Support\Dashboard;

use App\Models\Offer;
use Illuminate\Support\Facades\Schema;

/** Owner home tile: the latest offers with their progress. Safe to call before migrations run. */
final class Offers
{
    /**
     * @return list<array{id:int, title:string, status:string, status_label:string, method:string,
     *                    recipients:int, sent:int, skipped:int, failed:int, created_at:string, url:string}>
     */
    public static function recent(int $limit = 3): array
    {
        if (! Schema::hasTable('offers')) {
            return [];
        }

        return Offer::query()->latest('id')->limit($limit)->get()->map(fn (Offer $o) => [
            'id' => $o->id,
            'title' => $o->title,
            'status' => $o->status,
            'status_label' => $o->statusLabel(),
            'method' => $o->methodLabel(),
            'recipients' => $o->recipient_count,
            'sent' => $o->sent_count,
            'skipped' => $o->skipped_count,
            'failed' => $o->failed_count,
            'created_at' => $o->created_at?->timezone('Asia/Kolkata')->format('d M'),
            'url' => route('offers.show', $o),
        ])->all();
    }
}
