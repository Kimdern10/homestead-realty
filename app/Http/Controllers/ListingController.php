<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ListingController extends Controller
{
    /**
     * Most homes that can be compared side by side.
     */
    public const int COMPARE_LIMIT = 4;

    /**
     * Show one home.
     */
    public function show(Listing $listing): View
    {
        $this->ensureVisible($listing);

        $listing->load(['city', 'agent', 'photos', 'openHouses' => fn ($query) => $query->upcoming()]);

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

    /**
     * A printable one-page flyer with a QR code that links back to the home.
     */
    public function flyer(Listing $listing): View
    {
        $this->ensureVisible($listing);

        $listing->load(['city', 'agent', 'photos', 'openHouses' => fn ($query) => $query->upcoming()]);

        return view('listings.flyer', compact('listing'));
    }

    /**
     * Compare up to four homes side by side: /compare?ids=4,9,12.
     */
    public function compare(Request $request): View
    {
        $ids = $this->idsFrom($request, self::COMPARE_LIMIT);

        $homes = $ids === [] ? new Collection : Listing::query()->published()->forCards()
            ->with('agent')
            ->whereKey($ids)
            ->get()
            ->sortBy(fn (Listing $listing) => array_search($listing->id, $ids, true))
            ->values();

        return view('listings.compare', compact('homes'));
    }

    /**
     * Listing cards for a list of ids, used for "Recently viewed" (kept in the visitor's browser).
     */
    public function cards(Request $request): Response
    {
        $ids = $this->idsFrom($request, 12);

        $homes = $ids === [] ? new Collection : Listing::query()->published()->forCards()
            ->whereKey($ids)
            ->get()
            ->sortBy(fn (Listing $listing) => array_search($listing->id, $ids, true));

        return response()->view('listings.cards', compact('homes'))
            ->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * Only published homes from active agents are public.
     */
    private function ensureVisible(Listing $listing): void
    {
        abort_unless($listing->is_published && $listing->agent->is_active, 404);
    }

    /**
     * Read "ids=1,2,3" into a short list of whole numbers.
     *
     * @return list<int>
     */
    private function idsFrom(Request $request, int $limit): array
    {
        $ids = array_map('intval', explode(',', (string) $request->query('ids', '')));

        return array_slice(array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0))), 0, $limit);
    }
}
