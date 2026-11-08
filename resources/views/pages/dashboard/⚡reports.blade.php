<?php

use App\Enums\EnquiryStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Reports')] class extends Component {
    #[Url(except: 30)]
    public int $days = 30;

    public function updatedDays(): void
    {
        $this->days = in_array($this->days, [7, 30, 90, 365], true) ? $this->days : 30;
    }

    private function since(): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays($this->days - 1);
    }

    /**
     * The numbers at the top.
     *
     * @return array<string, int|string>
     */
    #[Computed]
    public function totals(): array
    {
        $since = $this->since();
        $revenue = (int) Payment::query()->where('status', PaymentStatus::Paid)->where('paid_at', '>=', $since)->sum('amount');

        return [
            'visits' => (int) Visit::query()->where('day', '>=', $since->toDateString())->sum('views'),
            'leads' => Enquiry::query()->where('created_at', '>=', $since)->count(),
            'won' => Enquiry::query()->where('status', EnquiryStatus::Closed)->where('updated_at', '>=', $since)->count(),
            'listed' => Listing::query()->where('created_at', '>=', $since)->count(),
            'sold' => Listing::query()->where('status', ListingStatus::Sold)->where('sold_at', '>=', $since)->count(),
            'rented' => Listing::query()->where('status', ListingStatus::Rented)->where('sold_at', '>=', $since)->count(),
            'revenue' => '$'.number_format($revenue / 100),
        ];
    }

    /**
     * Where visitors came from.
     *
     * @return Collection<int, object{source: string, total: int}>
     */
    #[Computed]
    public function sources(): Collection
    {
        return Visit::query()->where('day', '>=', $this->since()->toDateString())
            ->selectRaw('source, SUM(views) as total')->groupBy('source')->orderByDesc('total')->take(10)
            ->get()->map(fn ($row) => (object) ['source' => (string) $row->source, 'total' => (int) $row->total]);
    }

    /**
     * Leads per agent, and how many were won.
     *
     * @return Collection<int, Agent>
     */
    #[Computed]
    public function agents(): Collection
    {
        $since = $this->since();

        return Agent::query()
            ->withCount([
                'enquiries as leads_count' => fn ($query) => $query->where('created_at', '>=', $since),
                'enquiries as won_count' => fn ($query) => $query->where('status', EnquiryStatus::Closed)->where('updated_at', '>=', $since),
                'listings as sold_count' => fn ($query) => $query->where('status', ListingStatus::Sold)->where('sold_at', '>=', $since),
            ])
            ->orderByDesc('leads_count')->get();
    }

    /**
     * The most viewed homes (all time).
     *
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function topHomes(): Collection
    {
        return Listing::query()->with('agent:id,name')->where('views', '>', 0)
            ->withCount('enquiries')
            ->orderByDesc('views')->take(10)->get(['id', 'slug', 'address', 'zip', 'price', 'status', 'views', 'agent_id']);
    }

    /**
     * Homes sold and money taken, month by month for the last 12 months.
     *
     * @return list<array{label: string, sold: int, revenue: int}>
     */
    #[Computed]
    public function months(): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(11);

        $sold = Listing::query()->where('status', ListingStatus::Sold)->where('sold_at', '>=', $start)->pluck('sold_at')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));
        $revenue = Payment::query()->where('status', PaymentStatus::Paid)->where('paid_at', '>=', $start)->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->format('Y-m'))->map(fn ($payments) => (int) round($payments->sum('amount') / 100));

        return collect(range(0, 11))->map(function (int $offset) use ($start, $sold, $revenue): array {
            $month = $start->addMonths($offset);

            return ['label' => $month->format('M'), 'sold' => (int) ($sold[$month->format('Y-m')] ?? 0), 'revenue' => (int) ($revenue[$month->format('Y-m')] ?? 0)];
        })->all();
    }

    /**
     * Where leads came from (the first website that sent the visitor).
     *
     * @return Collection<string, int>
     */
    #[Computed]
    public function leadSources(): Collection
    {
        return Enquiry::query()->where('created_at', '>=', $this->since())
            ->get(['source'])->countBy(fn (Enquiry $enquiry) => $enquiry->source ?: 'Unknown')->sortDesc();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Reports') }}</flux:heading>
            <flux:text class="mt-1">{{ __('How the website and the team are doing. Visitor counts are simple daily totals: no cookies, no personal data.') }}</flux:text>
        </div>
        <flux:radio.group wire:model.live="days" variant="segmented" size="sm">
            <flux:radio :value="7" :label="__('7 days')" />
            <flux:radio :value="30" :label="__('30 days')" />
            <flux:radio :value="90" :label="__('90 days')" />
            <flux:radio :value="365" :label="__('1 year')" />
        </flux:radio.group>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        @foreach ([
            'visits' => __('Website visits'),
            'leads' => __('New leads'),
            'won' => __('Leads won'),
            'listed' => __('Homes added'),
            'sold' => __('Homes sold'),
            'rented' => __('Homes rented'),
            'revenue' => __('Online payments'),
        ] as $key => $label)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ $label }}</flux:text>
                <div class="mt-1 text-2xl font-semibold tabular-nums" data-test="total-{{ $key }}">{{ is_int($this->totals[$key]) ? number_format($this->totals[$key]) : $this->totals[$key] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading>{{ __('Where visitors come from') }}</flux:heading>
            @php($maxSource = max(1, (int) $this->sources->max('total')))
            <div class="mt-4 flex flex-col gap-3">
                @forelse ($this->sources as $row)
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $row->source }}</span><span class="tabular-nums text-zinc-500">{{ number_format($row->total) }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800"><div class="h-2 rounded-full bg-red-600" style="width: {{ round($row->total / $maxSource * 100) }}%"></div></div>
                    </div>
                @empty
                    <flux:text>{{ __('No visits counted yet. Numbers appear as people visit the website.') }}</flux:text>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading>{{ __('Homes sold per month') }}</flux:heading>
            @php($maxSold = max(1, max(array_column($this->months, 'sold'))))
            <div class="mt-4 flex h-40 items-end gap-2" role="img" aria-label="{{ __('Homes sold in each of the last 12 months') }}">
                @foreach ($this->months as $month)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <span class="text-xs tabular-nums text-zinc-500">{{ $month['sold'] ?: '' }}</span>
                        <div class="w-full rounded-t bg-zinc-800 dark:bg-zinc-200" style="height: {{ max(2, round($month['sold'] / $maxSold * 120)) }}px"></div>
                        <span class="text-xs text-zinc-500">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
            @if (array_sum(array_column($this->months, 'revenue')) > 0)
                <flux:text class="mt-4 text-sm">{{ __('Online payments, last 12 months:') }} <b>${{ number_format(array_sum(array_column($this->months, 'revenue'))) }}</b></flux:text>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading>{{ __('Leads per agent') }}</flux:heading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>{{ __('Agent') }}</flux:table.column>
                <flux:table.column align="end">{{ __('New leads') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Won') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Homes sold') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->agents as $agent)
                    <flux:table.row :key="$agent->id">
                        <flux:table.cell>{{ $agent->name }} @unless ($agent->is_active)<flux:badge size="sm" color="zinc">{{ __('inactive') }}</flux:badge>@endunless</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $agent->leads_count }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $agent->won_count }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $agent->sold_count }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 lg:col-span-2 dark:border-zinc-700">
            <flux:heading>{{ __('Most viewed homes') }}</flux:heading>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>{{ __('Home') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Views') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Leads') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->topHomes as $home)
                        <flux:table.row :key="$home->id">
                            <flux:table.cell class="whitespace-normal">
                                <a class="font-medium hover:underline" href="{{ route('listings.show', $home) }}" target="_blank">{{ $home->address }}</a>
                                <div class="text-xs text-zinc-500">{{ $home->priceLabel() }} · {{ $home->status->label() }} · {{ $home->agent?->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($home->views) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $home->enquiries_count }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="3">{{ __('No views yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading>{{ __('Where leads came from') }}</flux:heading>
            <div class="mt-3 flex flex-col gap-2">
                @forelse ($this->leadSources as $source => $count)
                    <div class="flex justify-between text-sm"><span>{{ $source }}</span><span class="tabular-nums">{{ $count }}</span></div>
                @empty
                    <flux:text>{{ __('No leads in this period.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </div>
</div>
