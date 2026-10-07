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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('phase')->nullable()->after('paint_seed');
        });

        Schema::table('tracked_skins', function (Blueprint $table) {
            $table->string('phase')->nullable()->after('max_float');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('phase');
        });

        Schema::table('tracked_skins', function (Blueprint $table) {
            $table->dropColumn('phase');
        });
    }
};
