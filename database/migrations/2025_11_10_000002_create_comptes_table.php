<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comptes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // users.id is big integer (default Laravel), use unsignedBigInteger for FK
            $table->unsignedBigInteger('user_id')->index()->nullable();
            $table->decimal('solde', 15, 2)->default(0);
            $table->string('devise', 10)->default('XOF');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes');
    }
};
