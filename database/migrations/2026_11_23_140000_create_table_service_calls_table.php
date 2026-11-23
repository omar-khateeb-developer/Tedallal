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
        if (!Schema::hasTable('table_service_calls')) {
            Schema::create('table_service_calls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('table_id')->constrained('restaurant_tables')->cascadeOnDelete();
                $table->string('type', 32); // 'waiter_call' or 'bill_request'
                $table->string('status', 32)->default('pending'); // 'pending', 'attended', 'cancelled'
                $table->string('payment_method', 32)->nullable(); // 'cash', 'card', 'online', null
                $table->string('reason', 128)->nullable(); // 'general', 'water', 'cutlery', 'cleaning', 'inquiry', etc.
                $table->text('notes')->nullable();
                $table->timestamp('attended_at')->nullable();
                $table->foreignId('attended_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['restaurant_id', 'status', 'created_at']);
                $table->index(['table_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_service_calls');
    }
};
