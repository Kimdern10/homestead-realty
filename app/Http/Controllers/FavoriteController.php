<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Save a home to the visitor's favorites, or remove it if already saved.
     */
    public function toggle(Request $request, Listing $listing): JsonResponse|RedirectResponse
    {
        abort_unless($listing->is_published, 404);

        $result = $request->user()->favorites()->toggle($listing->id);
        $saved = $result['attached'] !== [];

        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved]);
        }

        return back()->with('status', $saved ? 'Home saved.' : 'Home removed from your saved homes.');
    }
}
