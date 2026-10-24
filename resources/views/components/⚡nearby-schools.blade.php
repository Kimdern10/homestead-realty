<?php

use App\Services\SchoolFinder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Lazy] class extends Component {
    #[Locked]
    public float $lat;

    #[Locked]
    public float $lng;

    #[Locked]
    public int $limit = 6;

    /**
     * @return list<array{name: string, level: string|null, kind: string|null, miles: float, lat: float, lng: float}>
     */
    #[Computed]
    public function schools(): array
    {
        return app(SchoolFinder::class)->near($this->lat, $this->lng, $this->limit);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="schools schools--loading" aria-busy="true">
                <div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>
            </div>
            HTML;
    }
}; ?>

<div class="schools">
    @if ($this->schools === [])
        <p class="muted">We couldn't find schools within two miles right now. Ask the agent about the school district.</p>
    @else
        <ul class="school-list">
            @foreach ($this->schools as $school)
                <li class="school" wire:key="school-{{ $loop->index }}">
                    <span class="school__icon" aria-hidden="true"><x-icon name="building" /></span>
                    <span class="school__body">
                        <strong>{{ $school['name'] }}</strong>
                        <span>{{ collect([$school['level'], $school['kind']])->filter()->implode(' · ') ?: 'School' }}</span>
                    </span>
                    <span class="school__distance">{{ $school['miles'] < 0.1 ? '< 0.1' : $school['miles'] }} mi</span>
                </li>
            @endforeach
        </ul>
        <p class="school-note">Distances are in a straight line. School locations from the U.S. Department of Education (NCES). Check school zones with the district before you buy or rent.</p>
    @endif
</div>
