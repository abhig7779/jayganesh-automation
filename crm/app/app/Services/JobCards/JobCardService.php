<?php

namespace App\Services\JobCards;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\JobCard;
use App\Models\JobCardEvent;
use App\Models\JobCardItem;
use App\Models\JobCardPhoto;
use App\Models\Product;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Support\Phone;
use App\Support\RegNo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Every job card write goes through here: numbering, the status machine, ticks, photos and the timeline. */
class JobCardService
{
    /** The status machine. Anything not listed is blocked. */
    public const TRANSITIONS = [
        JobCard::WAITING => [JobCard::WORKING, JobCard::CANCELLED],
        JobCard::WORKING => [JobCard::QC, JobCard::WAITING, JobCard::CANCELLED],
        JobCard::QC => [JobCard::READY, JobCard::WORKING, JobCard::CANCELLED],
        JobCard::READY => [JobCard::DELIVERED, JobCard::WORKING],
        JobCard::DELIVERED => [],
        JobCard::CANCELLED => [JobCard::WAITING],
    ];

    public const PHOTO_MAX_PX = 1600;

    public const THUMB_MAX_PX = 480;

    public function __construct(private readonly PhotoResizer $resizer) {}

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Financial-year code for a date in IST: 5 Nov 2026 → "2627", 10 Mar 2026 → "2526". */
    public static function financialYear(?Carbon $at = null): string
    {
        $d = ($at ?? now())->copy()->timezone('Asia/Kolkata');
        $start = $d->month >= 4 ? $d->year : $d->year - 1;

        return substr((string) $start, -2).substr((string) ($start + 1), -2);
    }

    /**
     * Takes the next number for the branch and year. Must run inside the caller's transaction:
     * the sequence row stays locked until it commits, so numbers are gap-free and never repeat
     * (a rolled-back card gives its number back).
     */
    public function nextNumber(int $branchId, ?Carbon $at = null): string
    {
        $fy = self::financialYear($at);

        $row = DB::table('job_card_sequences')->where(['branch_id' => $branchId, 'fy' => $fy])->lockForUpdate()->first();
        if (! $row) {
            try {
                DB::table('job_card_sequences')->insert(['branch_id' => $branchId, 'fy' => $fy, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // Another counter created the row first; lock theirs.
            }
            $row = DB::table('job_card_sequences')->where(['branch_id' => $branchId, 'fy' => $fy])->lockForUpdate()->first();
        }

        $n = $row->last_number + 1;
        DB::table('job_card_sequences')->where('id', $row->id)->update(['last_number' => $n, 'updated_at' => now()]);

        return sprintf('JC-%s-%04d', $fy, $n);
    }

    /**
     * Car in. $data: customer_id (or mobile + name for a new customer), customer_car_id (or
     * car_model + car_reg_no for a new car), odometer, fuel_level, promised_at, technician_id,
     * bay, notes, items: [{product_id?, description?, qty, price_paise?}].
     */
    public function create(array $data, User $by): JobCard
    {
        return DB::transaction(function () use ($data, $by) {
            $customer = $this->resolveCustomer($data, $by);
            $car = $this->resolveCar($customer, $data, $by);
            $branchId = $by->branch_id ?? $customer->branch_id ?? Branch::query()->value('id');

            $card = new JobCard(Arr::only($data, ['odometer', 'fuel_level', 'promised_at', 'technician_id', 'bay', 'notes']) + [
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'customer_car_id' => $car?->id,
                'created_by' => $by->id,
            ]);
            $card->number = $this->nextNumber($branchId);
            $card->status = JobCard::WAITING;
            $card->save();

            foreach (array_values($data['items'] ?? []) as $i => $item) {
                $this->addItem($card, $item, $by, $i, log: false);
            }

            $this->event($card, 'created', $by, to: JobCard::WAITING);
            if ($card->technician_id) {
                $this->event($card, 'assigned', $by, note: 'Assigned to '.$card->technician?->name);
            }
            $customer->forceFill(['last_visit_at' => now()])->saveQuietly();

            return $card->load('items');
        });
    }

    public function addItem(JobCard $card, array $item, User $by, ?int $position = null, bool $log = true): JobCardItem
    {
        $product = ! empty($item['product_id']) ? Product::query()->find($item['product_id']) : null;
        $description = trim((string) ($item['description'] ?? '')) ?: $product?->name;
        if ($description === null || $description === '') {
            throw ValidationException::withMessages(['items' => 'Each work item needs a product or a description.']);
        }

        $line = $card->items()->create([
            'product_id' => $product?->id,
            'description' => Str::limit($description, 200, ''),
            'qty' => max(1, (int) ($item['qty'] ?? 1)),
            'price_paise' => array_key_exists('price_paise', $item) && $item['price_paise'] !== null
                ? max(0, (int) $item['price_paise'])
                : (int) ($product?->price_paise ?? 0),
            'position' => $position ?? ((int) $card->items()->max('position') + 1),
        ]);
        if ($log) {
            $this->event($card, 'item_added', $by, note: 'Added work: '.$line->description);
        }

        return $line;
    }

    public function assign(JobCard $card, ?User $technician, User $by): JobCard
    {
        if ($technician && ! $technician->hasRole('technician') && ! $technician->can('jobcards.manage')) {
            throw ValidationException::withMessages(['technician_id' => 'Pick a technician.']);
        }
        if ($card->technician_id === $technician?->id) {
            return $card;
        }
        $card->technician_id = $technician?->id;
        $card->save();
        $this->event($card, 'assigned', $by, note: $technician ? 'Assigned to '.$technician->name : 'Technician removed');

        return $card;
    }

    /** Moves a card to a new status, stamps the time and writes the timeline. */
    public function transition(JobCard $card, string $to, User $by, ?string $note = null): JobCard
    {
        return DB::transaction(function () use ($card, $to, $by, $note) {
            /** @var JobCard $fresh */
            $fresh = JobCard::query()->lockForUpdate()->findOrFail($card->id);
            $from = $fresh->status;

            if ($from === $to) {
                return $fresh; // a double-tap or a repeated drag: nothing to do
            }
            if (! in_array($to, JobCard::STATUSES, true) || ! self::canMove($from, $to)) {
                throw ValidationException::withMessages([
                    'status' => 'A car in '.JobCard::LABELS[$from].' can’t move to '.(JobCard::LABELS[$to] ?? $to).'.',
                ]);
            }
            if ($to === JobCard::CANCELLED && blank($note)) {
                throw ValidationException::withMessages(['note' => 'Say why the job is cancelled.']);
            }

            $fresh->status = $to;
            if ($stamp = JobCard::STAMPS[$to] ?? null) {
                $fresh->{$stamp} = now();
            }
            // Back to waiting after a cancel: clear the cancel stamp.
            if ($to === JobCard::WAITING && $from === JobCard::CANCELLED) {
                $fresh->cancelled_at = null;
            }
            $fresh->save();

            $this->event($fresh, 'status', $by, from: $from, to: $to, note: $note);

            if ($to === JobCard::DELIVERED && $fresh->photos()->where('stage', 'after')->doesntExist()) {
                // Not a block: the counter can still hand over the car. Just noted on the timeline.
                $this->event($fresh, 'note', $by, note: 'Delivered without after photos');
            }

            $card->setRawAttributes($fresh->getAttributes(), true);

            return $card;
        });
    }

    public function setItemDone(JobCardItem $item, bool $done, User $by): JobCardItem
    {
        if ($item->done === $done) {
            return $item;
        }
        $item->forceFill([
            'done' => $done,
            'done_by' => $done ? $by->id : null,
            'done_at' => $done ? now() : null,
        ])->save();

        $this->event($item->jobCard, $done ? 'item_done' : 'item_undone', $by,
            note: ($done ? 'Done: ' : 'Not done: ').$item->description);

        return $item;
    }

    /**
     * Stores photos on the private disk, resized to 1600 px with a 480 px thumbnail.
     * $clientIds[i] is a UUID the phone made for file i; sending the same file again (a retry on
     * a bad network) returns the photo already saved instead of making a copy.
     *
     * @param  UploadedFile[]  $files
     * @return JobCardPhoto[]
     */
    public function addPhotos(JobCard $card, array $files, string $stage, User $by, array $clientIds = []): array
    {
        if (! in_array($stage, JobCardPhoto::STAGES, true)) {
            $stage = 'other';
        }
        $saved = [];
        $new = 0;

        foreach (array_values($files) as $i => $file) {
            $uuid = $clientIds[$i] ?? null;
            $uuid = is_string($uuid) && Str::isUuid($uuid) ? strtolower($uuid) : null;

            if ($uuid && ($existing = $card->photos()->where('client_uuid', $uuid)->first())) {
                $saved[] = $existing;

                continue;
            }

            try {
                [$jpeg, $w, $h] = $this->resizer->toJpeg($file->getRealPath(), self::PHOTO_MAX_PX);
                [$thumb] = $this->resizer->toJpeg($file->getRealPath(), self::THUMB_MAX_PX, 75);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['photos' => 'One of the files is not a photo we can open. Take it again or pick a JPG/PNG.']);
            }

            $base = 'jobcards/'.$card->id.'/'.$stage.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8));
            Storage::disk(JobCardPhoto::DISK)->put($base.'.jpg', $jpeg);
            Storage::disk(JobCardPhoto::DISK)->put($base.'-thumb.jpg', $thumb);

            try {
                $saved[] = $card->photos()->create([
                    'stage' => $stage,
                    'path' => $base.'.jpg',
                    'thumb_path' => $base.'-thumb.jpg',
                    'width' => $w,
                    'height' => $h,
                    'client_uuid' => $uuid,
                    'uploaded_by' => $by->id,
                ]);
                $new++;
            } catch (UniqueConstraintViolationException) {
                // The same retry arrived twice at once: keep the first, drop our files.
                Storage::disk(JobCardPhoto::DISK)->delete([$base.'.jpg', $base.'-thumb.jpg']);
                $saved[] = $card->photos()->where('client_uuid', $uuid)->firstOrFail();
            }
        }

        if ($new > 0) {
            $this->event($card, 'photo', $by, note: $new.' '.$stage.' photo'.($new > 1 ? 's' : '').' added');
        }

        return $saved;
    }

    /** English "vehicle ready" message, through the WhatsApp module when it is merged. */
    public function readyWhatsAppLink(JobCard $card): ?string
    {
        $card->loadMissing('customer', 'car');
        $mobile = $card->customer?->mobile;
        if (! $mobile) {
            return null;
        }
        $first = Str::before(trim((string) $card->customer->name), ' ') ?: 'there';
        $car = $card->car ? ($card->car->model ?: $card->car->label()) : 'car';
        $plate = $card->car?->reg_no ? RegNo::pretty($card->car->reg_no) : null;
        $text = "Hi {$first}, your {$car}".($plate ? " ({$plate})" : '').' is ready for pickup at Jay Ganesh Car Accessories.';

        $class = 'App\\Services\\WhatsApp\\WhatsApp';
        if (class_exists($class) && method_exists($class, 'manualLink')) {
            return app($class)->manualLink($mobile, $text);
        }

        return 'https://wa.me/'.preg_replace('/\D+/', '', Phone::normalize($mobile) ?? $mobile).'?text='.rawurlencode($text);
    }

    public function logReadyMessage(JobCard $card, User $by): void
    {
        $this->event($card, 'whatsapp', $by, note: 'Ready message opened in WhatsApp');
    }

    /**
     * The bill prefill in the CONTRACTS.md §3 shape. Only lines with a product go on the bill
     * (billing needs products for GST/HSN); free-text lines are listed back to the counter.
     *
     * @return array{customer_id:int, customer_car_id:?int, quotation_id:null, job_card_id:int, items:array<int, array{product_id:int, qty:int, unit_price_paise:int}>}
     */
    public function billPrefill(JobCard $card): array
    {
        $card->loadMissing('items');

        return [
            'customer_id' => (int) $card->customer_id,
            'customer_car_id' => $card->customer_car_id ? (int) $card->customer_car_id : null,
            'quotation_id' => null,
            'job_card_id' => (int) $card->id,
            'items' => $card->items->whereNotNull('product_id')->map(fn (JobCardItem $i) => [
                'product_id' => (int) $i->product_id,
                'qty' => (int) $i->qty,
                'unit_price_paise' => (int) $i->price_paise,
            ])->values()->all(),
        ];
    }

    /** Called by the billing listener once a bill for this job card is saved. */
    public function linkInvoice(JobCard $card, int $invoiceId, ?User $by = null): void
    {
        if ($card->invoice_id === $invoiceId) {
            return;
        }
        $card->forceFill(['invoice_id' => $invoiceId])->save();
        $this->event($card, 'billed', $by, note: 'Bill made');
    }

    public function event(JobCard $card, string $type, ?User $by, ?string $from = null, ?string $to = null, ?string $note = null): JobCardEvent
    {
        return $card->events()->create([
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note !== null ? Str::limit($note, 300, '') : null,
            'user_id' => $by?->id,
        ]);
    }

    private function resolveCustomer(array $data, User $by): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->findOrFail($data['customer_id']);
        }
        if (empty($data['mobile'])) {
            throw ValidationException::withMessages(['mobile' => 'Enter the customer’s mobile number.']);
        }

        return app(CustomerService::class)
            ->findOrCreateByMobile((string) $data['mobile'], ['name' => $data['name'] ?? null], $by);
    }

    private function resolveCar(Customer $customer, array $data, User $by): ?CustomerCar
    {
        if (! empty($data['customer_car_id'])) {
            $car = CustomerCar::query()->whereKey($data['customer_car_id'])->where('customer_id', $customer->id)->first();
            if (! $car) {
                throw ValidationException::withMessages(['customer_car_id' => 'That car belongs to another customer.']);
            }

            return $car;
        }
        if (blank($data['car_model'] ?? null)) {
            return null;
        }

        // "Hyundai Creta" → make Hyundai, model Creta; "Innova Hycross" stays the model.
        $parts = preg_split('/\s+/', trim((string) $data['car_model']), 2);
        $makes = ['hyundai', 'toyota', 'mahindra', 'maruti', 'tata', 'kia', 'honda', 'mg', 'skoda', 'volkswagen', 'vw', 'renault', 'nissan', 'ford', 'jeep', 'bmw', 'mercedes', 'audi', 'citroen', 'byd'];
        [$make, $model] = count($parts) === 2 && in_array(strtolower($parts[0]), $makes, true)
            ? [ucfirst($parts[0]), $parts[1]]
            : ['', trim((string) $data['car_model'])];

        return app(CustomerService::class)->addCar($customer, [
            'make' => $make,
            'model' => $model,
            'reg_no' => $data['car_reg_no'] ?? null,
        ], $by);
    }
}
