<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the home page.
     */
    public function __invoke(): View
    {
        $featured = Listing::query()->published()->forCards()
            ->where('is_featured', true)
            ->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent])
            ->latest('listed_at')->take(6)->get();

        $newest = Listing::query()->published()->forCards()
            ->where('status', ListingStatus::ForSale)
            ->whereNotIn('id', $featured->modelKeys())
            ->latest('listed_at')->take(8)->get();

        $rentals = Listing::query()->published()->forCards()
            ->where('status', ListingStatus::ForRent)
            ->latest('listed_at')->take(4)->get();

        $cities = City::query()->ordered()
            ->withCount(['listings as available_count' => fn ($query) => $query->published()->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent])])
            ->where('is_featured', true)->take(8)->get();

        $agents = Agent::query()->active()->withCount(['listings as active_count' => fn ($query) => $query->published()->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent])])
            ->orderByDesc('active_count')->take(4)->get();

        return view('home', compact('featured', 'newest', 'rentals', 'cities', 'agents'));
    }
}
