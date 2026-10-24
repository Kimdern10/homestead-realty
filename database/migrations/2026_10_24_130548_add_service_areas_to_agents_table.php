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
        Schema::table('agents', function (Blueprint $table) {
            $table->text('zip_codes')->nullable()->after('languages');
            $table->unsignedInteger('homes_sold')->nullable()->after('zip_codes');
        });

        Schema::create('agent_city', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->unique(['agent_id', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_city');

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['zip_codes', 'homes_sold']);
        });
    }
};
