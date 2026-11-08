<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price drops, open houses, WhatsApp, owner submissions, alerts, reviews,
     * guides, the lead pipeline and visitor sources.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->unsignedInteger('previous_price')->nullable()->after('price');
            $table->timestamp('price_changed_at')->nullable()->after('previous_price');
            $table->string('owner_name')->nullable()->after('listing_office');
            $table->string('owner_email')->nullable()->after('owner_name');
            $table->string('owner_phone', 30)->nullable()->after('owner_email');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->string('whatsapp', 30)->nullable()->after('phone');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->unsignedInteger('notified_price')->nullable()->after('listing_id');
        });

        Schema::table('saved_searches', function (Blueprint $table) {
            $table->boolean('alerts_enabled')->default(true)->after('filters');
            $table->timestamp('last_alerted_at')->nullable()->after('alerts_enabled');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->date('follow_up_on')->nullable()->after('status');
            $table->string('source', 120)->nullable()->after('ip_address');
        });

        Schema::create('open_houses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 300)->nullable();
            $table->text('body');
            $table->string('cover_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('enquiry_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('source', 80);
            $table->unsignedInteger('views')->default(0);
            $table->unique(['day', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::dropIfExists('enquiry_notes');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('open_houses');

        Schema::table('enquiries', fn (Blueprint $table) => $table->dropColumn(['follow_up_on', 'source']));
        Schema::table('saved_searches', fn (Blueprint $table) => $table->dropColumn(['alerts_enabled', 'last_alerted_at']));
        Schema::table('favorites', fn (Blueprint $table) => $table->dropColumn('notified_price'));
        Schema::table('agents', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
        Schema::table('listings', fn (Blueprint $table) => $table->dropColumn(['previous_price', 'price_changed_at', 'owner_name', 'owner_email', 'owner_phone']));
    }
};
