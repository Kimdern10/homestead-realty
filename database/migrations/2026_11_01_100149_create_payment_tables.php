<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online payments (Stripe or Paystack) and what they pay for.
     */
    public function up(): void
    {
        Schema::create('rental_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('awaiting_payment')->index();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->date('move_in_date');
            $table->unsignedTinyInteger('occupants')->default(1);
            $table->boolean('has_pets')->default(false);
            $table->string('employer')->nullable();
            $table->string('job_title')->nullable();
            $table->unsignedInteger('monthly_income');
            $table->string('current_address')->nullable();
            $table->text('references')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('address')->nullable();
            $table->date('preferred_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('awaiting_payment')->index();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('gateway', 20);
            $table->string('purpose', 30)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('USD');
            $table->string('description');
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('payable');
            $table->string('payer_name');
            $table->string('payer_email');
            $table->string('payer_phone', 30)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('featured_until')->nullable()->after('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('featured_until');
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('service_bookings');
        Schema::dropIfExists('rental_applications');
    }
};
