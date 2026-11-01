<?php

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Payments')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $purpose = '';

    #[Url(except: 'paid')]
    public string $status = 'paid';

    /**
     * Start again from page one when a filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        return Payment::query()
            ->visibleTo(auth()->user())
            ->with('listing.city')
            ->when(PaymentPurpose::tryFrom($this->purpose), fn (Builder $query, PaymentPurpose $purpose) => $query->where('purpose', $purpose))
            ->when(PaymentStatus::tryFrom($this->status), fn (Builder $query, PaymentStatus $status) => $query->where('status', $status))
            ->latest()
            ->paginate(25);
    }

    /**
     * Money received, per currency, for the cards at the top.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function totals(): array
    {
        return Payment::query()->visibleTo(auth()->user())->where('status', PaymentStatus::Paid)
            ->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency')
            ->map(fn ($total): int => (int) $total)->all();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Payments') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Application fees, holding deposits, featured listings and service bookings paid online.') }}</flux:text>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        @forelse ($this->totals as $currency => $total)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Received in :currency', ['currency' => $currency]) }}</flux:text>
                <div class="mt-1 text-2xl font-bold">{{ ($currency === 'NGN' ? '₦' : '$').number_format($total / 100, 2) }}</div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Received') }}</flux:text>
                <div class="mt-1 text-2xl font-bold">$0.00</div>
            </div>
        @endforelse
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:max-w-xl">
        <flux:select wire:model.live="purpose" :aria-label="__('Type')">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (\App\Enums\PaymentPurpose::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" :aria-label="__('Status')">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (\App\Enums\PaymentStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->payments->isEmpty())
        <flux:callout icon="credit-card">{{ __('No payments yet.') }}</flux:callout>
    @else
        <div class="grid gap-2">
            @foreach ($this->payments as $payment)
                <div wire:key="payment-{{ $payment->id }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $payment->amountLabel() }}</span>
                            <flux:badge size="sm" :color="$payment->purpose->color()">{{ $payment->purpose->label() }}</flux:badge>
                            <flux:badge size="sm" :color="$payment->status->color()">{{ $payment->status->label() }}</flux:badge>
                        </div>
                        <div class="truncate text-sm">{{ $payment->payer_name }} · {{ $payment->payer_email }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $payment->description }} · {{ ucfirst($payment->gateway) }} · {{ $payment->reference }}</div>
                    </div>
                    <flux:text class="text-sm">{{ ($payment->paid_at ?? $payment->created_at)->format('M j, g:ia') }}</flux:text>
                </div>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->payments" />
    @endif
</div>
