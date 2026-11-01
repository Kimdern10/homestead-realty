<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Home types, amenities and paid services, all managed by the admin.
     * The usual home types and amenities are added as a starting point.
     */
    public function up(): void
    {
        Schema::create('home_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind', 20)->default('house');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('home_types')->insert(collect([
            ['Single-family home', 'house', 'house'],
            ['Condo', 'condo', 'condo'],
            ['Townhouse', 'townhouse', 'townhouse'],
            ['Apartment', 'apartment', 'apartment'],
            ['Multi-family', 'multi_family', 'multi_family'],
            ['Land', 'land', 'land'],
        ])->map(fn (array $type, int $index) => [
            'name' => $type[0], 'slug' => $type[1], 'kind' => $type[2], 'sort_order' => $index, 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        DB::table('amenities')->insert(collect([
            'Air conditioning', 'Central heating', 'Fireplace', 'Hardwood floors', 'Updated kitchen', 'Walk-in closet',
            'In-unit laundry', 'Swimming pool', 'Hot tub', 'Patio or deck', 'Fenced yard', 'Garden',
            'Garage', 'EV charger', 'Solar panels', 'Smart home', 'Gym', 'Doorman', 'Elevator', 'Rooftop terrace',
            'Pet friendly', 'Waterfront', 'City view', 'Basement', 'Home office', 'Gated community',
        ])->map(fn (string $name, int $index) => [
            'name' => $name, 'sort_order' => $index, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('home_types');
    }
};
