<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use Illuminate\Contracts\View\View;

class CityController extends Controller
{
    /**
     * List every city we cover.
     */
    public function index(): View
    {
        $cities = City::query()->ordered()
            ->withCount([
                'listings as for_sale_count' => fn ($query) => $query->published()->where('status', ListingStatus::ForSale),
                'listings as for_rent_count' => fn ($query) => $query->published()->where('status', ListingStatus::ForRent),
            ])
            ->get();

        return view('cities.index', compact('cities'));
    }

    /**
     * Show a city with its neighborhoods, homes and agents.
     */
    public function show(City $city): View
    {
        $base = fn () => Listing::query()->published()->where('city_id', $city->id);

        $forSale = $base()->forCards()->where('status', ListingStatus::ForSale)->sortBy('newest')->take(8)->get();
        $forRent = $base()->forCards()->where('status', ListingStatus::ForRent)->sortBy('newest')->take(4)->get();
        $sold = $base()->forCards()->where('status', ListingStatus::Sold)->latest('sold_at')->take(4)->get();

        $salePrices = $base()->where('status', ListingStatus::ForSale)->orderBy('price')->pluck('price');
        $stats = [
            'for_sale' => $salePrices->count(),
            'for_rent' => $base()->where('status', ListingStatus::ForRent)->count(),
            'median_price' => $salePrices->isNotEmpty() ? (int) $salePrices->median() : null,
            'median_rent' => ($rent = $base()->where('status', ListingStatus::ForRent)->pluck('price'))->isNotEmpty() ? (int) $rent->median() : null,
        ];

        $neighborhoods = $base()->whereNotNull('neighborhood')
            ->selectRaw('neighborhood, COUNT(*) as total')
            ->groupBy('neighborhood')->orderByDesc('total')->orderBy('neighborhood')
            ->pluck('total', 'neighborhood');

        $agents = Agent::query()->active()
            ->whereHas('listings', fn ($query) => $query->published()->where('city_id', $city->id))
            ->take(4)->get();

        return view('cities.show', compact('city', 'forSale', 'forRent', 'sold', 'stats', 'neighborhoods', 'agents'));
    }
}
