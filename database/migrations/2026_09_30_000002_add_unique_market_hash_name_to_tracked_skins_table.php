<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_skins', function (Blueprint $table) {
            $table->unique('market_hash_name');
        });
    }

    public function down(): void
    {
        Schema::table('tracked_skins', function (Blueprint $table) {
            $table->dropUnique(['market_hash_name']);
        });
    }
};
