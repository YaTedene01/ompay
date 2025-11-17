<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrController extends Controller
{
    use ApiResponse;


    public function payer(Request $request)
    {
        $data = $request->validate([
            'code_marchand' => ['required', 'string'],
            'montant' => ['required', 'numeric', 'min:0.01'],
        ]);

        $user = $request->user();

        // Chercher le QR code par code_marchand dans les métadonnées
        $qr = QrCode::whereJsonContains('meta->code_marchand', $data['code_marchand'])->first();
        if (! $qr) {
            return $this->error('Code marchand invalide', 404);
        }

        try {
            $montant = (float) $data['montant'];
            $result = app(\App\Services\CompteService::class)->payerParQr($user, $qr, $montant);
            return $this->success($result, 'Paiement effectué');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
