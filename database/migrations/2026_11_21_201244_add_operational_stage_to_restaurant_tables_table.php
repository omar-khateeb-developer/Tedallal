<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('operational_stage', 32)->default('available')->after('status');
            $table->timestamp('stage_updated_at')->nullable()->after('operational_stage');
            $table->timestamp('session_started_at')->nullable()->after('stage_updated_at');
            $table->timestamp('bill_requested_at')->nullable()->after('session_started_at');
            $table->string('current_guest_name', 128)->nullable()->after('bill_requested_at');

            $table->index(['restaurant_id', 'operational_stage']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'operational_stage']);
            $table->dropColumn([
                'operational_stage',
                'stage_updated_at',
                'session_started_at',
                'bill_requested_at',
                'current_guest_name',
            ]);
        });
    }
};
