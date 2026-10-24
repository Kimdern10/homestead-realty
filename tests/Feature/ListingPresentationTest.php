<?php

use App\Enums\PropertyType;
use App\Models\Listing;

test('prices are shown in full and in short form', function (int $price, bool $rental, string $full, string $short) {
    $listing = $rental ? Listing::factory()->forRent()->make(['price' => $price]) : Listing::factory()->make(['price' => $price]);

    expect($listing->priceLabel())->toBe($full)
        ->and($listing->shortPrice())->toBe($short);
})->with([
    [1250000, false, '$1,250,000', '$1.25M'],
    [2000000, false, '$2,000,000', '$2M'],
    [685000, false, '$685,000', '$685K'],
    [3200, true, '$3,200/mo', '$3.2K/mo'],
]);

test('facts read naturally for homes, studios and land', function () {
    expect(Listing::factory()->make(['beds' => 3, 'baths' => 2.5, 'sqft' => 1850])->facts())->toBe(['3 bd', '2.5 ba', '1,850 sqft'])
        ->and(Listing::factory()->make(['beds' => 0, 'baths' => 1.0, 'sqft' => null])->facts())->toBe(['Studio', '1 ba'])
        ->and(Listing::factory()->make(['property_type' => PropertyType::Land, 'beds' => null, 'baths' => null, 'sqft' => null, 'lot_acres' => 2.5])->facts())->toBe(['2.5 acre lot']);
});

test('each listing gets a unique web address', function () {
    Listing::factory()->create(['slug' => '1-main-st-austin-tx-78701']);

    expect(Listing::uniqueSlug('1 Main St Austin TX 78701'))->toBe('1-main-st-austin-tx-78701-2');
});
