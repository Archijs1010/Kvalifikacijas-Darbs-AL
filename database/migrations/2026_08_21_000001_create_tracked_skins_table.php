<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_skins', function (Blueprint $table) {
            $table->id();
            $table->string('market_hash_name');
            $table->decimal('min_float', 6, 4)->nullable();
            $table->decimal('max_float', 6, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_skins');
    }
};
