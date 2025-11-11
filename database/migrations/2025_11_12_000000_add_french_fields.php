<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Comptes: add solde/devise if missing and copy values from balance/currency when present
        Schema::table('comptes', function (Blueprint $table) {
            if (! Schema::hasColumn('comptes', 'solde')) {
                $table->decimal('solde', 15, 2)->default(0)->after('user_id');
            }
            if (! Schema::hasColumn('comptes', 'devise')) {
                $table->string('devise', 10)->default('XOF')->after('solde');
            }
        });

        // copy values from legacy columns if they exist
        try {
            if (Schema::hasColumn('comptes', 'balance')) {
                DB::statement("UPDATE comptes SET solde = balance WHERE solde IS NULL OR solde = 0");
            }
            if (Schema::hasColumn('comptes', 'currency')) {
                DB::statement("UPDATE comptes SET devise = currency WHERE (devise IS NULL OR devise = '')");
            }
        } catch (\Throwable $e) {
            // ignore copy errors (db engine differences)
        }

        // Transactions: add montant if missing and copy from amount
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'montant')) {
                $table->decimal('montant', 15, 2)->default(0)->after('type');
            }
        });
        try {
            if (Schema::hasColumn('transactions', 'amount')) {
                DB::statement("UPDATE transactions SET montant = amount WHERE montant IS NULL OR montant = 0");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // QR meta transformations (Postgres jsonb best-effort)
        try {
            // merchant_code -> code_marchand
            DB::statement("UPDATE qr_codes SET meta = meta || jsonb_build_object('code_marchand', meta->'merchant_code') WHERE meta ? 'merchant_code'");
            DB::statement("UPDATE qr_codes SET meta = meta - 'merchant_code' WHERE meta ? 'merchant_code'");
            // amount -> montant in meta
            DB::statement("UPDATE qr_codes SET meta = meta || jsonb_build_object('montant', meta->'amount') WHERE meta ? 'amount'");
            DB::statement("UPDATE qr_codes SET meta = meta - 'amount' WHERE meta ? 'amount'");
        } catch (\Throwable $e) {
            // ignore if not postgres or jsonb unsupported
        }
    }

    public function down(): void
    {
        // Revert: do not drop legacy columns; only drop the french columns if present
        Schema::table('qr_codes', function (Blueprint $table) {
            // cannot reliably reverse JSON changes
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'montant')) {
                $table->dropColumn('montant');
            }
        });

        Schema::table('comptes', function (Blueprint $table) {
            if (Schema::hasColumn('comptes', 'devise')) {
                $table->dropColumn('devise');
            }
            if (Schema::hasColumn('comptes', 'solde')) {
                $table->dropColumn('solde');
            }
        });
    }
};
