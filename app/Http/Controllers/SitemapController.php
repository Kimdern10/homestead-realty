<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use App\Models\Post;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * List every public page for search engines.
     */
    public function __invoke(): Response
    {
        $listings = Listing::query()->published()->select(['id', 'slug', 'updated_at'])->latest('updated_at')->get();
        $cities = City::query()->select(['id', 'slug', 'updated_at'])->get();
        $agents = Agent::query()->active()->select(['id', 'slug', 'updated_at'])->get();
        $posts = Post::query()->live()->select(['id', 'slug', 'updated_at'])->get();

        return response()
            ->view('sitemap', compact('listings', 'cities', 'agents', 'posts'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Tell search engines what to crawl and where the sitemap is.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /dashboard',
            'Disallow: /saved',
            'Disallow: /compare',
            'Disallow: /homes/cards',
            'Disallow: /settings',
            'Allow: /',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
