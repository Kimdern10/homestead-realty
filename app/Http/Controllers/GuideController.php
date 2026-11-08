<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\City;
use App\Models\Listing;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    /**
     * All guides, optionally for one city: /guides?city=austin.
     */
    public function index(Request $request): View
    {
        $city = filled($request->query('city')) ? City::query()->where('slug', (string) $request->query('city'))->first() : null;

        $posts = Post::query()->live()->with('city')
            ->when($city, fn ($query) => $query->where('city_id', $city?->id))
            ->paginate(12)
            ->withQueryString();

        $cities = City::query()->whereHas('posts', fn ($query) => $query->live())->ordered()->get(['id', 'name', 'slug']);

        return view('guides.index', compact('posts', 'city', 'cities'));
    }

    /**
     * Read one guide.
     */
    public function show(Post $post): View
    {
        abort_unless($post->is_published && $post->published_at?->lte(now()), 404);

        $post->load('city');

        $homes = $post->city
            ? Listing::query()->published()->forCards()->where('city_id', $post->city_id)
                ->whereIn('status', [ListingStatus::ForSale, ListingStatus::ForRent])
                ->latest('listed_at')->take(4)->get()
            : collect();

        $more = Post::query()->live()->whereKeyNot($post->id)->with('city')->take(3)->get();

        return view('guides.show', compact('post', 'homes', 'more'));
    }
}
