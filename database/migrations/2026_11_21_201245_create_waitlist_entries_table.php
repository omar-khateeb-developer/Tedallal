<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_phone', 32);
            $table->unsignedSmallInteger('party_size')->default(2);
            $table->string('seating_preference', 32)->default('any');
            $table->string('status', 32)->default('waiting'); // waiting, notified, seated, cancelled, no_show
            $table->unsignedInteger('queue_number')->default(1);
            $table->unsignedSmallInteger('estimated_wait_minutes')->default(15);
            $table->text('notes')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
