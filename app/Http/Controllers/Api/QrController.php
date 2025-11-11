<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="QrCode",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="meta", type="object", additionalProperties=true)
 * )
 */
class QrController extends Controller
{
    use ApiResponse;


    /**
     * @OA\Post(
     *     path="/api/qr/payer",
     *     summary="Payer à l'aide d'un code marchand",
     *     tags={"Paiements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code_marchand"},
     *             @OA\Property(property="code_marchand", type="string", example="ABC123"),
     *             @OA\Property(property="montant", type="number", example=50.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement effectué",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement effectué")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur de paiement",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
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
