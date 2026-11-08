<?php

namespace App\Http\Controllers;

use App\Models\OpenHouse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class OpenHouseController extends Controller
{
    /**
     * Upcoming open houses, grouped by day.
     */
    public function __invoke(): View
    {
        $days = OpenHouse::query()->upcoming()
            ->whereHas('listing', fn (Builder $listing) => $listing->published())
            ->with(['listing' => fn ($listing) => $listing->forCards()])
            ->take(60)
            ->get()
            ->groupBy(fn (OpenHouse $openHouse) => $openHouse->starts_at->toDateString());

        return view('open-houses', compact('days'));
    }
}
