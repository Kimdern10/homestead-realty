<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status', 20)->index();
            $table->string('property_type', 20)->index();
            $table->unsignedInteger('price')->index();
            $table->unsignedTinyInteger('beds')->nullable();
            $table->decimal('baths', 3, 1)->nullable();
            $table->unsignedInteger('sqft')->nullable();
            $table->decimal('lot_acres', 8, 2)->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->unsignedTinyInteger('garage_spaces')->nullable();
            $table->string('address');
            $table->string('neighborhood')->nullable();
            $table->string('zip', 10);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description');
            $table->json('amenities')->nullable();
            $table->string('video_url')->nullable();
            $table->string('virtual_tour_url')->nullable();
            $table->unsignedInteger('hoa_fee')->nullable();
            $table->unsignedInteger('property_tax')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->date('listed_at');
            $table->date('sold_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'status', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
