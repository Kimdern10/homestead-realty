<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\Listing;
use Illuminate\Contracts\View\View;

class AgentController extends Controller
{
    /**
     * List the agents.
     */
    public function index(): View
    {
        $agents = Agent::query()->active()
            ->withCount([
                'listings as active_count' => fn ($query) => $query->published()->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent, ListingStatus::Pending]),
                'listings as sold_count' => fn ($query) => $query->published()->where('status', ListingStatus::Sold),
            ])
            ->orderBy('name')->get();

        return view('agents.index', compact('agents'));
    }

    /**
     * Show an agent's profile and homes.
     */
    public function show(Agent $agent): View
    {
        abort_unless($agent->is_active, 404);

        $listings = Listing::query()->published()->forCards()->where('agent_id', $agent->id)
            ->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent, ListingStatus::Pending])
            ->sortBy('newest')->get();

        $sold = Listing::query()->published()->forCards()->where('agent_id', $agent->id)
            ->where('status', ListingStatus::Sold)->latest('sold_at')->take(8)->get();

        $cities = $listings->concat($sold)->pluck('city')->unique('id')->values();

        return view('agents.show', compact('agent', 'listings', 'sold', 'cities'));
    }
}
