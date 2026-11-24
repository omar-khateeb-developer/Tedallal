<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('table_type', 20)
                ->default('dine_in')
                ->after('zone');

            $table->index(['restaurant_id', 'table_type'], 'rt_restaurant_table_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropIndex('rt_restaurant_table_type_idx');
            $table->dropColumn('table_type');
        });
    }
};
