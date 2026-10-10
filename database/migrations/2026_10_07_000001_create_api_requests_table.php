<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('market_hash_name');
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('rate_limit')->nullable();
            $table->unsignedInteger('rate_remaining')->nullable();
            $table->timestamp('requested_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
