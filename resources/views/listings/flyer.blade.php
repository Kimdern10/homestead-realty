@php
    $site = \App\Models\Setting::values();
    $photos = $listing->photos->take(4);
    $url = route('listings.show', $listing);
    $agent = $listing->agent;
@endphp
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Flyer · {{ $listing->address }} | {{ $site['business_name'] }}</title>
    <style>
        @page { size: letter; margin: 0.45in; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 "Segoe UI", system-ui, -apple-system, sans-serif; color: #1c1f23; background: #ecebe8; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .bar { position: sticky; top: 0; display: flex; gap: 10px; justify-content: center; align-items: center; padding: 12px; background: #1c1f23; color: #fff; font-size: 14px; }
        .bar button, .bar a { border: 0; border-radius: 999px; padding: 9px 18px; font: 700 14px/1 inherit; cursor: pointer; text-decoration: none; }
        .bar button { background: #d92228; color: #fff; }
        .bar a { background: #fff; color: #1c1f23; }
        .sheet { width: 100%; max-width: 8.5in; margin: 20px auto; background: #fff; padding: 0.45in; box-shadow: 0 10px 40px rgba(0,0,0,.15); }
        .top { display: flex; justify-content: space-between; align-items: end; gap: 16px; border-bottom: 4px solid #d92228; padding-bottom: 12px; }
        .brand { font-weight: 800; font-size: 20px; letter-spacing: -.02em; }
        .status { font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #d92228; font-size: 13px; }
        h1 { margin: 14px 0 0; font-size: 26px; line-height: 1.2; }
        .price { font-size: 38px; font-weight: 800; letter-spacing: -.03em; margin-top: 4px; }
        .price small { font-size: 16px; color: #6b6f76; }
        .facts { display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: 17px; margin-top: 6px; }
        .facts b { font-weight: 800; }
        .hero { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 10px; margin-top: 16px; display: block; background: #eee; }
        .thumbs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 8px; }
        .thumbs img { width: 100%; aspect-ratio: 3 / 2; object-fit: cover; border-radius: 8px; display: block; }
        .cols { display: grid; grid-template-columns: 1fr 180px; gap: 24px; margin-top: 18px; }
        .desc { font-size: 14px; color: #33373d; }
        .amenities { columns: 2; font-size: 13px; margin: 10px 0 0; padding-left: 18px; }
        .open { margin-top: 12px; padding: 10px 12px; border-radius: 8px; background: #fbe9e6; font-size: 14px; }
        .qr { text-align: center; font-size: 12px; color: #6b6f76; }
        .qr #qr { width: 160px; height: 160px; margin: 0 auto 6px; }
        .qr #qr img, .qr #qr canvas { width: 160px !important; height: 160px !important; }
        .agent { display: flex; gap: 14px; align-items: center; margin-top: 18px; padding-top: 14px; border-top: 1px solid #e7e2da; }
        .agent img { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; }
        .agent b { font-size: 17px; }
        .small { font-size: 11px; color: #6b6f76; margin-top: 14px; }
        @media print { body { background: #fff; } .bar { display: none; } .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; } }
        @media (max-width: 640px) { .sheet { padding: 18px; margin: 0; } .cols { grid-template-columns: 1fr; } .price { font-size: 30px; } }
    </style>
</head>
<body>
    <div class="bar">
        <span>Print it, or choose “Save as PDF” in the print window.</span>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
        <a href="{{ $url }}">Back to the home</a>
    </div>

    <main class="sheet">
        <div class="top">
            <div class="brand">{{ $site['business_name'] }}</div>
            <div class="status">{{ $listing->status->label() }}</div>
        </div>

        <h1>{{ $listing->address }}, {{ $listing->locality() }}</h1>
        <div class="price">${{ number_format($listing->price) }}@if ($listing->status->isRental())<small>/month</small>@endif</div>
        @if ($facts = $listing->facts())
            <div class="facts">
                @foreach ($facts as $fact)
                    @php [$number, $unit] = array_pad(explode(' ', $fact, 2), 2, ''); @endphp
                    <span><b>{{ $number }}</b> {{ $unit }}</span>
                @endforeach
                <span>{{ $listing->typeLabel() }}</span>
                @if ($listing->year_built)<span>Built {{ $listing->year_built }}</span>@endif
            </div>
        @endif

        <img class="hero" src="{{ $photos->first()?->url() ?? $listing->placeholderUrl() }}" alt="">
        @if ($photos->count() > 1)
            <div class="thumbs">
                @foreach ($photos->skip(1) as $photo)
                    <img src="{{ $photo->url(small: true) }}" alt="">
                @endforeach
            </div>
        @endif

        <div class="cols">
            <div>
                <div class="desc">{{ Str::limit(trim($listing->description), 900) }}</div>
                @if (filled($listing->amenities))
                    <ul class="amenities">
                        @foreach (array_slice($listing->amenities, 0, 10) as $amenity)
                            <li>{{ $amenity }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($listing->openHouses->isNotEmpty())
                    <div class="open"><b>Open house:</b> {{ $listing->openHouses->map(fn ($openHouse) => $openHouse->label())->implode(' · ') }}</div>
                @endif
            </div>
            <div class="qr">
                <div id="qr" data-url="{{ $url }}"></div>
                Scan for photos, map and to book a tour
            </div>
        </div>

        <div class="agent">
            @if ($photo = $agent->photoUrl())
                <img src="{{ $photo }}" alt="">
            @endif
            <div>
                <b>{{ $agent->name }}</b><br>
                {{ $agent->title ?: $site['business_name'] }}<br>
                {{ collect([$agent->phone, $agent->contactEmail()])->filter()->implode(' · ') }}
            </div>
        </div>

        <p class="small">{{ $url }} · Information believed accurate but not guaranteed. Equal Housing Opportunity.@if (filled($site['broker_license'])) Broker license {{ $site['broker_license'] }}.@endif</p>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        (function () {
            var box = document.getElementById('qr');
            if (window.QRCode && box) {
                new QRCode(box, { text: box.dataset.url, width: 320, height: 320, correctLevel: QRCode.CorrectLevel.M });
            } else if (box) {
                box.textContent = box.dataset.url;
            }
        })();
    </script>
</body>
</html>
