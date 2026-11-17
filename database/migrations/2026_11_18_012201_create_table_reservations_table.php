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
        Schema::create('table_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('restaurant_tables')->cascadeOnDelete();
            
            $table->string('guest_name', 120);
            $table->string('guest_phone', 30)->index();
            $table->string('guest_email', 120)->nullable();
            
            $table->date('reservation_date')->index();
            $table->string('time_slot', 10)->index(); // e.g. 19:00, 20:30
            $table->unsignedTinyInteger('guests_count')->default(2);
            $table->text('special_requests')->nullable();
            
            // Status: pending_verification, confirmed, seated, cancelled, completed, no_show
            $table->string('status', 30)->default('pending_verification')->index();
            
            // Anti-Fraud & OTP Verification
            $table->string('otp_code', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            
            // Financial / Deposit
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->string('deposit_status', 20)->default('none'); // none, pending, paid, refunded
            
            // Staff Management
            $table->foreignId('confirmed_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('staff_notes')->nullable();
            
            // Audit & Rate Limiting
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();

            // Indexes for fast querying & concurrency checks
            $table->index(['restaurant_id', 'reservation_date', 'status'], 'idx_restaurant_date_status');
            $table->index(['table_id', 'reservation_date', 'time_slot'], 'idx_table_date_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_reservations');
    }
};
