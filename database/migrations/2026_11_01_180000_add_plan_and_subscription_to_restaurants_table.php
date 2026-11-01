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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('plan')->default('basic')->after('type'); // 'basic', 'pro', 'max'
            $table->timestamp('subscription_expires_at')->nullable()->after('status');
            
            $table->index('plan');
            $table->index('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['plan']);
            $table->dropIndex(['subscription_expires_at']);
            $table->dropColumn(['plan', 'subscription_expires_at']);
        });
    }
};

