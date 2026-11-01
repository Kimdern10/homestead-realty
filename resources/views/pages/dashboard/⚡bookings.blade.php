<?php

use App\Enums\ApplicationStatus;
use App\Models\ServiceBooking;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Service bookings')] class extends Component {
    use WithPagination;

    public bool $showUnpaid = false;

    /**
     * @return LengthAwarePaginator<int, ServiceBooking>
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        return ServiceBooking::query()
            ->with('payment')
            ->when(! $this->showUnpaid, fn ($query) => $query->where('status', '!=', ApplicationStatus::AwaitingPayment))
            ->latest()
            ->paginate(20);
    }

    /**
     * Confirm or close a booking.
     */
    public function setStatus(int $id, string $status): void
    {
        $booking = ServiceBooking::query()->findOrFail($id);
        $booking->update(['status' => ApplicationStatus::from($status)]);

        Flux::toast(__('Booking updated.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Service bookings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Services visitors booked and paid for online.') }}</flux:text>
        </div>
        <flux:switch wire:model.live="showUnpaid" :label="__('Show unpaid')" />
    </div>

    @if ($this->bookings->isEmpty())
        <flux:callout icon="calendar-days">{{ __('No bookings yet. Add services under Office › Services.') }}</flux:callout>
    @else
        <div class="grid gap-2">
            @foreach ($this->bookings as $booking)
                <div wire:key="booking-{{ $booking->id }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $booking->service_name }}</span>
                            <flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status === \App\Enums\ApplicationStatus::Approved ? __('Confirmed') : $booking->status->label() }}</flux:badge>
                            @if ($booking->payment)<span class="text-sm text-zinc-500">{{ $booking->payment->amountLabel() }}</span>@endif
                        </div>
                        <div class="text-sm">{{ $booking->name }} · <a class="underline" href="mailto:{{ $booking->email }}">{{ $booking->email }}</a> · {{ $booking->phone }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ $booking->preferred_date ? __('Preferred :date', ['date' => $booking->preferred_date->format('M j, Y')]) : __('No date given') }}
                            @if ($booking->address) · {{ $booking->address }} @endif
                            @if ($booking->notes) · “{{ Str::limit($booking->notes, 80) }}” @endif
                        </div>
                    </div>
                    <div class="flex gap-2">
                        @if ($booking->status === \App\Enums\ApplicationStatus::New)
                            <flux:button size="sm" icon="check" wire:click="setStatus({{ $booking->id }}, 'approved')">{{ __('Confirm') }}</flux:button>
                        @endif
                        @if ($booking->status !== \App\Enums\ApplicationStatus::Declined)
                            <flux:button size="sm" variant="ghost" wire:click="setStatus({{ $booking->id }}, 'declined')">{{ __('Close') }}</flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->bookings" />
    @endif
</div>
