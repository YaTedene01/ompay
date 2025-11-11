<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop existing column first (in its own statement) to avoid Postgres duplicate/transaction issues
        if (Schema::hasColumn('transactions', 'created_by')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }

        // Then add the corrected column type and FK
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('status');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'created_by')) {
                $table->dropForeign([ 'created_by' ]);
                $table->dropColumn('created_by');
            }
            $table->uuid('created_by')->nullable()->after('status');
        });
    }
};
