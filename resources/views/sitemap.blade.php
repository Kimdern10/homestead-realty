{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
    <url><loc>{{ route('search', ['status' => 'for_sale']) }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
    <url><loc>{{ route('search', ['status' => 'for_rent']) }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
    <url><loc>{{ route('search', ['status' => 'sold']) }}</loc><changefreq>weekly</changefreq><priority>0.5</priority></url>
    <url><loc>{{ route('cities.index') }}</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ route('agents.index') }}</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ route('privacy') }}</loc><changefreq>yearly</changefreq><priority>0.2</priority></url>
@foreach ($cities as $city)
    <url><loc>{{ route('cities.show', $city) }}</loc>@if ($city->updated_at)<lastmod>{{ $city->updated_at->toAtomString() }}</lastmod>@endif<changefreq>weekly</changefreq><priority>0.8</priority></url>
@endforeach
@foreach ($agents as $agent)
    <url><loc>{{ route('agents.show', $agent) }}</loc>@if ($agent->updated_at)<lastmod>{{ $agent->updated_at->toAtomString() }}</lastmod>@endif<changefreq>weekly</changefreq><priority>0.6</priority></url>
@endforeach
@foreach ($listings as $listing)
    <url><loc>{{ route('listings.show', $listing) }}</loc>@if ($listing->updated_at)<lastmod>{{ $listing->updated_at->toAtomString() }}</lastmod>@endif<changefreq>weekly</changefreq><priority>0.8</priority></url>
@endforeach
</urlset>
