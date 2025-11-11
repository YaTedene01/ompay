<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone')->index();
            $table->string('token')->unique();
            $table->json('data')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_links');
    }
};
