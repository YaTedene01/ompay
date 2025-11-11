<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RenameFieldsToFrench extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Comptes: add solde/devise, copy values then drop old columns
        if (Schema::hasTable('comptes')) {
            Schema::table('comptes', function (Blueprint $table) {
                if (! Schema::hasColumn('comptes', 'solde')) {
                    $table->decimal('solde', 15, 2)->default(0)->after('user_id');
                }
                if (! Schema::hasColumn('comptes', 'devise')) {
                    $table->string('devise')->default('XOF')->after('solde');
                }
            });

            // copy data from legacy columns only if they exist to avoid SQL errors
            try {
                if (Schema::hasColumn('comptes', 'balance') && Schema::hasColumn('comptes', 'solde')) {
                    DB::table('comptes')->update([
                        'solde' => DB::raw('balance'),
                    ]);
                }
                if (Schema::hasColumn('comptes', 'currency') && Schema::hasColumn('comptes', 'devise')) {
                    DB::table('comptes')->update([
                        'devise' => DB::raw('currency'),
                    ]);
                }
            } catch (\Throwable $e) {
                // ignore copy errors (db engine differences)
            }

            Schema::table('comptes', function (Blueprint $table) {
                if (Schema::hasColumn('comptes', 'balance')) {
                    $table->dropColumn('balance');
                }
                if (Schema::hasColumn('comptes', 'currency')) {
                    $table->dropColumn('currency');
                }
            });
        }

        // Transactions: add montant, copy, drop amount
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('transactions', 'montant')) {
                    $table->decimal('montant', 15, 2)->default(0)->after('type');
                }
            });

            try {
                if (Schema::hasColumn('transactions', 'amount') && Schema::hasColumn('transactions', 'montant')) {
                    DB::table('transactions')->update([
                        'montant' => DB::raw('amount'),
                    ]);
                }
            } catch (\Throwable $e) {
                // ignore
            }

            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'amount')) {
                    $table->dropColumn('amount');
                }
            });
        }

        // QR JSON meta migration for Postgres JSONB (best-effort)
        if (Schema::hasTable('qr_codes')) {
            try {
                // Only run Postgres JSONB operations when using pgsql driver
                if (DB::getDriverName() === 'pgsql') {
                    // copy merchant_code -> code_marchand when present
                    DB::statement("UPDATE qr_codes SET meta = meta || jsonb_build_object('code_marchand', meta->'merchant_code') WHERE meta ? 'merchant_code'");
                    // copy amount -> montant when present
                    DB::statement("UPDATE qr_codes SET meta = meta || jsonb_build_object('montant', meta->'amount') WHERE meta ? 'amount'");
                    // remove old keys
                    DB::statement("UPDATE qr_codes SET meta = meta - 'merchant_code' WHERE meta ? 'merchant_code'");
                    DB::statement("UPDATE qr_codes SET meta = meta - 'amount' WHERE meta ? 'amount'");
                }
            } catch (\Throwable $e) {
                // ignore failures
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Recreate old columns and move data back (best-effort)
        if (Schema::hasTable('comptes')) {
            Schema::table('comptes', function (Blueprint $table) {
                if (! Schema::hasColumn('comptes', 'balance')) {
                    $table->decimal('balance', 15, 2)->default(0)->after('user_id');
                }
                if (! Schema::hasColumn('comptes', 'currency')) {
                    $table->string('currency')->default('XOF')->after('balance');
                }
            });

            try {
                DB::table('comptes')->update([
                    'balance' => DB::raw('solde'),
                    'currency' => DB::raw('devise'),
                ]);
            } catch (\Throwable $e) {
            }

            Schema::table('comptes', function (Blueprint $table) {
                if (Schema::hasColumn('comptes', 'solde')) {
                    $table->dropColumn('solde');
                }
                if (Schema::hasColumn('comptes', 'devise')) {
                    $table->dropColumn('devise');
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('transactions', 'amount')) {
                    $table->decimal('amount', 15, 2)->default(0)->after('type');
                }
            });

            try {
                DB::table('transactions')->update([
                    'amount' => DB::raw('montant'),
                ]);
            } catch (\Throwable $e) {
            }

            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'montant')) {
                    $table->dropColumn('montant');
                }
            });
        }

        if (Schema::hasTable('qr_codes')) {
            try {
                DB::statement("UPDATE qr_codes SET meta = meta || jsonb_build_object('merchant_code', meta->'code_marchand') WHERE meta ? 'code_marchand'");
                DB::statement("UPDATE qr_codes SET meta = meta || jsonb_build_object('amount', meta->'montant') WHERE meta ? 'montant'");
                DB::statement("UPDATE qr_codes SET meta = meta - 'code_marchand' WHERE meta ? 'code_marchand'");
                DB::statement("UPDATE qr_codes SET meta = meta - 'montant' WHERE meta ? 'montant'");
            } catch (\Throwable $e) {
            }
        }
    }
}
