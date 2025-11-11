<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ensure French columns have values copied from legacy columns before dropping
        try {
            if (Schema::hasTable('comptes')) {
                if (Schema::hasColumn('comptes', 'balance') && Schema::hasColumn('comptes', 'solde')) {
                    DB::statement("UPDATE comptes SET solde = balance WHERE solde IS NULL OR solde = 0");
                }
                if (Schema::hasColumn('comptes', 'currency') && Schema::hasColumn('comptes', 'devise')) {
                    DB::statement("UPDATE comptes SET devise = currency WHERE (devise IS NULL OR devise = '')");
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
        } catch (\Throwable $e) {
            // If something fails, rethrow to make the migration fail loudly
            throw $e;
        }

        try {
            if (Schema::hasTable('transactions')) {
                if (Schema::hasColumn('transactions', 'amount') && Schema::hasColumn('transactions', 'montant')) {
                    DB::statement("UPDATE transactions SET montant = amount WHERE montant IS NULL OR montant = 0");
                }
                Schema::table('transactions', function (Blueprint $table) {
                    if (Schema::hasColumn('transactions', 'amount')) {
                        $table->dropColumn('amount');
                    }
                });
            }
        } catch (\Throwable $e) {
            throw $e;
        }

        // Remove legacy keys in qr_codes.meta where possible (Postgres jsonb)
        try {
            if (Schema::hasTable('qr_codes') && DB::getDriverName() === 'pgsql') {
                DB::statement("UPDATE qr_codes SET meta = meta - 'merchant_code' WHERE meta ? 'merchant_code'");
                DB::statement("UPDATE qr_codes SET meta = meta - 'amount' WHERE meta ? 'amount'");
            }
        } catch (\Throwable $e) {
            // ignore non-fatal
        }
    }

    public function down(): void
    {
        // Recreate legacy columns and copy back from French columns
        Schema::table('comptes', function (Blueprint $table) {
            if (! Schema::hasColumn('comptes', 'balance')) {
                $table->decimal('balance', 15, 2)->default(0)->after('user_id');
            }
            if (! Schema::hasColumn('comptes', 'currency')) {
                $table->string('currency', 10)->default('XOF')->after('balance');
            }
        });
        try {
            if (Schema::hasColumn('comptes', 'solde') && Schema::hasColumn('comptes', 'balance')) {
                DB::statement("UPDATE comptes SET balance = solde WHERE balance IS NULL OR balance = 0");
            }
            if (Schema::hasColumn('comptes', 'devise') && Schema::hasColumn('comptes', 'currency')) {
                DB::statement("UPDATE comptes SET currency = devise WHERE (currency IS NULL OR currency = '')");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'amount')) {
                $table->decimal('amount', 15, 2)->default(0)->after('type');
            }
        });
        try {
            if (Schema::hasColumn('transactions', 'montant') && Schema::hasColumn('transactions', 'amount')) {
                DB::statement("UPDATE transactions SET amount = montant WHERE amount IS NULL OR amount = 0");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Cannot reliably restore JSON keys
    }
};
