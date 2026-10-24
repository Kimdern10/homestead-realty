<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ListingPhoto>
 */
class ListingPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'path' => 'listings/'.Str::lower((string) Str::ulid()).'.webp',
            'width' => 1600,
            'height' => 1067,
            'sort_order' => 0,
        ];
    }
}
