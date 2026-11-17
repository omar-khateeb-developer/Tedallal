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
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->float('pos_x')->default(50)->after('status');
            $table->float('pos_y')->default(50)->after('pos_x');
            $table->string('shape', 32)->default('circle')->after('pos_y');
            $table->integer('rotation')->default(0)->after('shape');
            $table->unsignedSmallInteger('capacity')->default(4)->after('rotation');
            $table->string('zone', 64)->default('main')->after('capacity');
            $table->float('width')->default(90)->after('zone');
            $table->float('height')->default(90)->after('width');
            $table->string('iot_device_id', 64)->nullable()->after('height');

            $table->index(['restaurant_id', 'zone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'zone']);
            $table->dropColumn([
                'pos_x',
                'pos_y',
                'shape',
                'rotation',
                'capacity',
                'zone',
                'width',
                'height',
                'iot_device_id',
            ]);
        });
    }
};
