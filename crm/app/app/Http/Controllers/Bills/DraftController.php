<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Controller;
use App\Models\BillDraft;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Server copy of the half-made bill (one per staff member), synced by the New bill screen. */
class DraftController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);
        $draft = BillDraft::query()->where('user_id', $request->user()->id)->first();

        return response()->json(['draft' => $draft ? [
            'draft_key' => $draft->draft_key,
            'payload' => $draft->payload,
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ] : null]);
    }

    public function save(Request $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);
        $data = $request->validate([
            'draft_key' => ['required', 'string', 'max:64'],
            'payload' => ['required', 'array'],
            'item_count' => ['nullable', 'integer', 'min:0'],
            'total_paise' => ['nullable', 'integer', 'min:0'],
        ]);
        if (strlen(json_encode($data['payload'])) > 60_000) {
            return response()->json(['message' => 'Draft too large.'], 413);
        }
        // A draft that was already saved as a bill must not come back.
        if (Invoice::query()->where('draft_key', $data['draft_key'])->exists()) {
            return response()->json(['saved' => false, 'billed' => true]);
        }

        $draft = BillDraft::query()->updateOrCreate(['user_id' => $request->user()->id], [
            'draft_key' => $data['draft_key'],
            'payload' => $data['payload'],
            'item_count' => $data['item_count'] ?? 0,
            'total_paise' => $data['total_paise'] ?? 0,
        ]);

        return response()->json(['saved' => true, 'at' => $draft->updated_at?->timezone('Asia/Kolkata')->format('H:i')]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);
        BillDraft::query()->where('user_id', $request->user()->id)->delete();

        return response()->json(['deleted' => true]);
    }
}
