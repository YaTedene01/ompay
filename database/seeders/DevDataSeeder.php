<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\QrCode;

class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo users with comptes
        $users = [];
        $phones = [
            ['name' => 'Alice', 'phone' => '+221700000002'],
            ['name' => 'Bob', 'phone' => '+221700000003'],
            ['name' => 'Shop', 'phone' => '+221700000004'],
        ];

        foreach ($phones as $p) {
            $email = strtolower(str_replace(' ', '', $p['name'])) . '@example.local';
            $users[] = User::firstOrCreate(
                ['phone' => $p['phone']],
                ['name' => $p['name'], 'email' => $email, 'password' => bcrypt('secret')]
            );
        }

        foreach ($users as $i => $u) {
            // create compte with solde/devise or fallback to balance/currency depending on DB schema
            $montant = 1000 * ($i + 1);
            // Be robust: prefer new French-named columns if they exist
            $compteData = [];
            if (\Illuminate\Support\Facades\Schema::hasColumn('comptes', 'solde')) {
                $compteData['solde'] = $montant;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('comptes', 'devise')) {
                $compteData['devise'] = 'XOF';
            }
            if (empty($compteData)) {
                // fallback to legacy names
                $compteData['balance'] = $montant;
                $compteData['currency'] = 'XOF';
            }
            $u->compte()->create($compteData);
        }

        // create a QR for the Shop with a montant in meta
        $shop = $users[2];
        QrCode::create([
            'user_id' => $shop->id,
            'code' => 'SEED-SHOP-QR-'.now()->timestamp,
            'meta' => ['code_marchand' => 'SHOP01', 'montant' => 250],
        ]);
    }
}
