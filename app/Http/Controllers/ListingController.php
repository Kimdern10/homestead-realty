<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Contracts\View\View;

class ListingController extends Controller
{
    /**
     * Show one home.
     */
    public function show(Listing $listing): View
    {
        abort_unless($listing->is_published && $listing->agent->is_active, 404);

        $listing->load(['city', 'agent', 'photos']);

        // Counting is a cheap single update; skip it for the listing's own agent and the admin.
        if (! request()->user()?->isStaff()) {
            Listing::query()->whereKey($listing->id)->increment('views');
        }

        $similar = Listing::query()->published()->forCards()
            ->whereKeyNot($listing->id)
            ->where('city_id', $listing->city_id)
            ->where('status', $listing->status)
            ->orderByRaw('ABS(CAST(price AS SIGNED) - ?)', [$listing->price])
            ->take(4)->get();

        return view('listings.show', compact('listing', 'similar'));
    }
}
