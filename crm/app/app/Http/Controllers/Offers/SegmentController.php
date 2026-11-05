<?php

namespace App\Http\Controllers\Offers;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Services\Offers\SegmentBuilder;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    public function store(Request $request, SegmentBuilder $segments)
    {
        $data = $request->validate([
            'segment_name' => ['required', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
        ], ['segment_name.required' => 'Give the saved list a name, e.g. "Creta owners".']);

        $segment = Segment::updateOrCreate(
            ['name' => trim($data['segment_name'])],
            [
                'filters' => $segments->normalize($data['filters'] ?? []),
                'branch_id' => $request->user()->branch_id,
                'created_by' => $request->user()->id,
            ],
        );

        if ($request->header('HX-Request')) {
            return response('<span class="text-sm font-medium text-green-700">Saved as “'.e($segment->name).'”.</span>');
        }

        return back()->with('status', "Saved “{$segment->name}”.");
    }

    public function destroy(Segment $segment)
    {
        // Only the saved filter is removed; offers keep their own snapshot of who got them.
        $segment->delete();

        return redirect()->route('offers.index')->with('status', "Removed “{$segment->name}”.");
    }
}
