<?php

namespace App\Services\Finance;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A range of whole shop days in India time. Timestamps in the database are UTC, so queries on
 * created_at use utcStart()/utcEnd(); date columns (expenses.date) use fromDate()/toDate().
 */
final class Period
{
    public const TZ = 'Asia/Kolkata';

    public const PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'custom' => 'Custom',
    ];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
    ) {}

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay();
    }

    public static function day(CarbonImmutable|string $day): self
    {
        $d = is_string($day) ? CarbonImmutable::parse($day, self::TZ)->startOfDay() : $day->setTimezone(self::TZ)->startOfDay();

        return new self($d, $d, 'custom');
    }

    public static function between(string $from, string $to): self
    {
        $f = CarbonImmutable::parse($from, self::TZ)->startOfDay();
        $t = CarbonImmutable::parse($to, self::TZ)->startOfDay();
        if ($t->lt($f)) {
            [$f, $t] = [$t, $f];
        }

        return new self($f, $t, 'custom');
    }

    public static function preset(string $preset, ?string $from = null, ?string $to = null): self
    {
        $today = self::today();

        return match ($preset) {
            'yesterday' => new self($today->subDay(), $today->subDay(), 'yesterday'),
            'this_week' => new self($today->startOfWeek(CarbonImmutable::MONDAY), $today, 'this_week'),
            'this_month' => new self($today->startOfMonth(), $today, 'this_month'),
            'last_month' => new self($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay(), 'last_month'),
            'custom' => self::safeCustom($from, $to, $today),
            default => new self($today, $today, 'today'),
        };
    }

    public static function fromRequest(Request $request, string $default = 'today'): self
    {
        $preset = (string) $request->query('preset', $default);
        if (! array_key_exists($preset, self::PRESETS)) {
            $preset = $default;
        }

        return self::preset($preset, $request->query('from'), $request->query('to'));
    }

    private static function safeCustom(?string $from, ?string $to, CarbonImmutable $today): self
    {
        $valid = fn ($s) => is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
        if (! $valid($from)) {
            return new self($today, $today, 'today');
        }
        $p = self::between($from, $valid($to) ? $to : $from);
        // Keep custom ranges to about 13 months so a typo can't scan ten years of bills.
        $f = $p->from->lt($p->to->subMonths(13)) ? $p->to->subMonths(13) : $p->from;

        return new self($f, $p->to, 'custom');
    }

    public function utcStart(): CarbonImmutable
    {
        return $this->from->startOfDay()->utc();
    }

    /** Exclusive end: the first moment after the last day. */
    public function utcEnd(): CarbonImmutable
    {
        return $this->to->addDay()->startOfDay()->utc();
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    public function isSingleDay(): bool
    {
        return $this->from->equalTo($this->to);
    }

    /** @return list<string> Y-m-d for every day in the range */
    public function days(): array
    {
        $out = [];
        for ($d = $this->from; $d->lte($this->to); $d = $d->addDay()) {
            $out[] = $d->toDateString();
        }

        return $out;
    }

    /** @return list<string> Y-m for every month touched by the range */
    public function months(): array
    {
        $out = [];
        for ($d = $this->from->startOfMonth(); $d->lte($this->to); $d = $d->addMonthNoOverflow()) {
            $out[] = $d->format('Y-m');
        }

        return $out;
    }

    public function label(): string
    {
        if ($this->isSingleDay()) {
            return $this->from->format('D, j M Y');
        }
        if ($this->from->year === $this->to->year) {
            return $this->from->format('j M').' – '.$this->to->format('j M Y');
        }

        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /** Query string that reproduces this period (for links and exports). */
    public function query(): array
    {
        return $this->preset === 'custom'
            ? ['preset' => 'custom', 'from' => $this->fromDate(), 'to' => $this->toDate()]
            : ['preset' => $this->preset];
    }

    public function fileStamp(): string
    {
        return $this->isSingleDay() ? $this->fromDate() : $this->fromDate().'_to_'.$this->toDate();
    }
}
