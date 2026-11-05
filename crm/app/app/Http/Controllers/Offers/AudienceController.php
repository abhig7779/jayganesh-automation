<?php

namespace App\Http\Controllers\Offers;

use App\Http\Controllers\Controller;
use App\Models\AudienceExport;
use App\Models\Customer;
use App\Models\Segment;
use App\Services\Offers\AudienceCsv;
use App\Services\Offers\SegmentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Meta Custom Audience customer-list export (CRM-PLAN.md §3.11), logged for privacy audits. */
class AudienceController extends Controller
{
    public function __construct(private readonly SegmentBuilder $segments) {}

    public function index()
    {
        $choices = collect([['id' => 0, 'name' => 'All customers', 'count' => $this->segments->count([])]])
            ->concat(Segment::query()->orderBy('name')->get()->map(fn (Segment $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'count' => $this->segments->count($s->filters ?? []),
            ]));

        return view('offers.audiences', [
            'choices' => $choices,
            'exports' => AudienceExport::with('user:id,name')->latest('id')->limit(15)->get(),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'segment_id' => ['required', 'integer'],
            'format' => ['required', 'in:plain,sha256'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['in:fn,ln,ct,st,country'],
        ]);

        $segment = $data['segment_id'] ? Segment::findOrFail($data['segment_id']) : null;
        $filters = $segment?->filters ?? [];
        $header = AudienceCsv::metaHeader($data['fields'] ?? []);
        $hashed = $data['format'] === 'sha256';

        $query = $this->segments->query($filters);
        $count = (clone $query)->count();

        AudienceExport::create([
            'user_id' => $request->user()->id,
            'segment_id' => $segment?->id,
            'segment_name' => $segment?->name ?? 'All customers',
            'filters' => $filters,
            'format' => $data['format'],
            'fields' => $header,
            'row_count' => $count,
            'ip' => $request->ip(),
        ]);

        $name = 'meta-audience-'.Str::slug($segment?->name ?? 'all-customers').'-'.now('Asia/Kolkata')->format('Y-m-d').($hashed ? '-sha256' : '').'.csv';

        return response()->streamDownload(function () use ($query, $header, $hashed) {
            $out = fopen('php://output', 'w');
            $rows = (function () use ($query, $header, $hashed) {
                foreach ($query->select(['id', 'name', 'mobile'])->lazyById(500) as $c) {
                    /** @var Customer $c */
                    yield AudienceCsv::metaRow($c, $header, $hashed);
                }
            })();
            AudienceCsv::write($out, $header, $rows);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
