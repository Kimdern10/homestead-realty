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
        Schema::table('listings', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('id')->index();
            $table->string('external_id')->nullable()->unique()->after('source');
            $table->string('mls_name', 100)->nullable()->after('virtual_tour_url');
            $table->string('mls_number', 50)->nullable()->after('mls_name');
            $table->string('listing_office')->nullable()->after('mls_number');
            $table->timestamp('synced_at')->nullable()->after('views');
            $table->boolean('edited_by_admin')->default(false)->after('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['source', 'external_id', 'mls_name', 'mls_number', 'listing_office', 'synced_at', 'edited_by_admin']);
        });
    }
};
