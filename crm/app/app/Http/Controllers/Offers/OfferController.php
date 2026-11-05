<?php

namespace App\Http\Controllers\Offers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\OfferRecipient;
use App\Models\Segment;
use App\Services\Offers\AudienceCsv;
use App\Services\Offers\OfferMessage;
use App\Services\Offers\OfferService;
use App\Services\Offers\SegmentBuilder;
use App\Services\Offers\WhatsAppBridge;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfferController extends Controller
{
    public function __construct(
        private readonly SegmentBuilder $segments,
        private readonly OfferService $offers,
        private readonly WhatsAppBridge $whatsapp,
    ) {}

    public function index()
    {
        $offers = Offer::with('creator:id,name')->latest('id')->paginate(20);
        $saved = Segment::query()->orderBy('name')->get()->map(function (Segment $s) {
            $s->count = $this->segments->count($s->filters ?? []);
            $s->summary = $this->segments->describe($s->filters ?? []);

            return $s;
        });

        return view('offers.index', compact('offers', 'saved'));
    }

    public function create(Request $request)
    {
        $segment = $request->integer('segment') ? Segment::find($request->integer('segment')) : null;
        $copy = $request->integer('copy') ? Offer::find($request->integer('copy')) : null;
        $filters = $segment?->filters ?? $copy?->filters ?? [];

        return view('offers.create', [
            'filters' => $filters,
            'segment' => $segment,
            'copy' => $copy,
            'options' => $this->segments->options(),
            'purchases' => SegmentBuilder::supportsPurchases(),
            'count' => $this->segments->count($filters),
            'saved' => Segment::query()->orderBy('name')->get(['id', 'name']),
            'automatic' => $this->whatsapp->automaticAvailable(),
            'templates' => $this->whatsapp->marketingTemplates(),
            'ratePaise' => WhatsAppBridge::ratePaise(),
            'defaultMessage' => $copy?->message ?? "Hi {name}! Jay Ganesh Car Accessories has a special offer for your {car} this week.\n\nReply YES to book a slot, or visit us at Kawadipat Toll Naka, Loni Kalbhor.\n\nReply STOP to stop offers.",
        ]);
    }

    /** htmx: the live count line (and a few sample names) as the filters change. */
    public function count(Request $request)
    {
        $filters = $this->segments->normalize((array) $request->input('filters', []));

        return view('offers.partials.count', [
            'count' => $this->segments->count($filters),
            'sample' => $this->segments->query($filters)->with('cars')->latest('last_visit_at')->limit(5)->get(),
            'ratePaise' => WhatsAppBridge::ratePaise(),
            'summary' => $this->segments->describe($filters),
        ]);
    }

    /** htmx: the message filled in for a real customer from the audience. */
    public function preview(Request $request)
    {
        $filters = $this->segments->normalize((array) $request->input('filters', []));
        $customer = $this->segments->query($filters)->with('cars')->whereHas('cars')->latest('last_visit_at')->first()
            ?? $this->segments->query($filters)->with('cars')->first();
        $text = (string) $request->input('message', '');

        return view('offers.partials.preview', [
            'customer' => $customer,
            'rendered' => OfferMessage::render($text, $customer),
            'length' => OfferMessage::length($text),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'filters' => ['nullable', 'array'],
            'segment_id' => ['nullable', 'integer', 'exists:segments,id'],
            'method' => ['nullable', 'in:draft,broadcast,assisted,automatic'],
            'template_name' => ['nullable', 'string', 'max:80'],
            'scheduled_at' => ['nullable', 'date'],
            'confirm_cost' => ['nullable', 'boolean'],
        ], [
            'message.max' => 'Keep the message under 1,000 characters so it fits a WhatsApp template.',
        ]);

        $offer = $this->offers->createDraft($data, $request->user());
        $method = $request->boolean('save_draft') ? 'draft' : ($data['method'] ?? 'broadcast');
        if ($method !== 'draft') {
            try {
                $offer = $this->offers->launch($offer, $method, $request->user(), $data);
            } catch (ValidationException $e) {
                return redirect()->route('offers.show', $offer)->withErrors($e->errors())
                    ->with('status', 'Offer saved as a draft. '.collect($e->errors())->flatten()->first());
            }
        }

        return $this->afterLaunch($offer);
    }

    public function show(Offer $offer)
    {
        $offer->load('creator:id,name', 'segment:id,name');
        $byStatus = $offer->recipients()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $recipients = $offer->recipients()->with('customer.cars')->orderBy('id')->paginate(50);
        $sampleCustomer = $recipients->first()?->customer
            ?? $this->segments->query($offer->filters ?? [])->with('cars')->first();

        return view('offers.show', [
            'offer' => $offer,
            'byStatus' => $byStatus,
            'recipients' => $recipients,
            'summary' => $this->segments->describe($offer->filters ?? []),
            'liveCount' => $offer->isDraft() && ! $offer->send_method ? $this->segments->count($offer->filters ?? []) : null,
            'sampleText' => OfferMessage::render($offer->message, $sampleCustomer),
            'sampleCustomer' => $sampleCustomer,
            'automatic' => $this->whatsapp->automaticAvailable(),
            'templates' => $this->whatsapp->marketingTemplates(),
            'ratePaise' => WhatsAppBridge::ratePaise(),
            'listLimit' => OfferService::BROADCAST_LIST_LIMIT,
        ]);
    }

    /** Pick the send method for a draft. */
    public function start(Request $request, Offer $offer)
    {
        $data = $request->validate([
            'method' => ['required', 'in:broadcast,assisted,automatic'],
            'template_name' => ['nullable', 'string', 'max:80'],
            'scheduled_at' => ['nullable', 'date'],
            'confirm_cost' => ['nullable', 'boolean'],
        ]);
        $offer = $this->offers->launch($offer, $data['method'], $request->user(), $data);

        return $this->afterLaunch($offer);
    }

    /** CSV of names + numbers for a WhatsApp Business broadcast list (?part=2 for the next 256). */
    public function broadcast(Request $request, Offer $offer): StreamedResponse
    {
        abort_unless($offer->send_method === 'broadcast', 404);
        $limit = OfferService::BROADCAST_LIST_LIMIT;
        $part = max(1, $request->integer('part', 1));
        $parts = max(1, (int) ceil($offer->recipient_count / $limit));

        $rows = $offer->recipients()->with('customer.cars')->orderBy('id')
            ->when($offer->recipient_count > $limit, fn ($q) => $q->skip(($part - 1) * $limit)->take($limit))
            ->get()
            ->map(fn (OfferRecipient $r) => AudienceCsv::broadcastRow(
                $r->name,
                $r->mobile,
                trim('JG '.($r->customer?->cars->first()?->model ?? '').' · '.$offer->title),
            ));

        $this->offers->markExported($offer);
        $name = 'broadcast-'.Str::slug($offer->title).($parts > 1 ? "-part-$part-of-$parts" : '').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            AudienceCsv::write($out, AudienceCsv::BROADCAST_HEADER, $rows, bom: true);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** The one-by-one sender: one customer at a time with a wa.me link and Sent / Skip. */
    public function assist(Offer $offer)
    {
        abort_unless($offer->send_method === 'assisted', 404);

        return view('offers.assist', $this->assistData($offer));
    }

    public function mark(Request $request, Offer $offer, OfferRecipient $recipient)
    {
        abort_unless($offer->send_method === 'assisted', 404);
        $data = $request->validate(['status' => ['required', 'in:sent,skipped,pending']]);
        $this->offers->mark($recipient, $data['status'], $request->user());

        if ($request->header('HX-Request')) {
            return view('offers.partials.assist-panel', $this->assistData($offer->fresh()));
        }

        return redirect()->route('offers.assist', $offer);
    }

    private function assistData(Offer $offer): array
    {
        $current = $this->offers->nextPending($offer);
        $customer = $current ? Customer::with('cars')->find($current->customer_id) : null;
        $text = $current ? OfferMessage::render($offer->message, $customer) : '';
        $done = $offer->recipients()->whereIn('status', ['sent', 'skipped'])->count();

        return [
            'offer' => $offer,
            'current' => $current,
            'customer' => $customer,
            'text' => $text,
            'link' => $current ? $this->whatsapp->manualLink($current->mobile, $text) : null,
            'done' => $done,
            'total' => $offer->recipients()->count(),
            'recent' => $offer->recipients()->whereIn('status', ['sent', 'skipped'])->latest('done_at')->latest('id')->limit(8)->get(),
            'pretty' => fn ($m) => Phone::pretty($m),
        ];
    }

    private function afterLaunch(Offer $offer)
    {
        return match ($offer->send_method) {
            'assisted' => redirect()->route('offers.assist', $offer)->with('status', 'Ready. Send to each customer, then press Sent.'),
            'automatic' => redirect()->route('offers.show', $offer)->with('status', $offer->scheduled_at
                ? 'Scheduled for '.$offer->scheduled_at->timezone('Asia/Kolkata')->format('d M, h:i A').'.'
                : 'Sending has started. Results update here.'),
            'broadcast' => redirect()->route('offers.show', $offer)->with('status', 'Broadcast list ready. Download it and copy the message.'),
            default => redirect()->route('offers.show', $offer)->with('status', 'Saved as a draft.'),
        };
    }
}
