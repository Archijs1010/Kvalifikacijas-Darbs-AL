<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_id')->unique();
            $table->string('market_hash_name');
            $table->decimal('price', 10, 2);
            $table->decimal('float_value', 8, 6);
            $table->timestamp('sold_at');
            $table->integer('paint_index')->nullable();
            $table->json('raw_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
