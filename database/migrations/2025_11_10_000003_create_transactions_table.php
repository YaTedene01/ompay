<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('compte_id')->nullable()->index();
            $table->string('type');
            $table->decimal('montant', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('counterparty')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('compte_id')->references('id')->on('comptes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
