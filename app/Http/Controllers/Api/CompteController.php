<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compte;
use App\Traits\ApiResponse;
use App\Services\CompteService;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Compte",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="solde", type="number", format="float"),
 *     @OA\Property(property="devise", type="string", example="XOF"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CompteController extends Controller
{
    use ApiResponse;

    public CompteService $service;

    public function __construct(CompteService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $compte = $this->service->getOrCreateForUser($user);
        return $this->success($compte);
    }

    /**
     * @OA\Get(
     *     path="/api/compte/{numeroCompte}/solde",
     *     summary="Consulter le solde d'un compte",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="numeroCompte",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         description="Numéro du compte (UUID)"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Solde du compte",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="compte_id", type="string", example="uuid-here"),
     *                 @OA\Property(property="solde", type="number", example=150.50),
     *                 @OA\Property(property="devise", type="string", example="XOF"),
     *                 @OA\Property(property="dernier_maj", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Compte introuvable",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès non autorisé à ce compte",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function solde(Request $request, string $numeroCompte)
    {
        $user = $request->user();

        // Vérifier que l'utilisateur a accès à ce compte
        $compte = $this->service->repo->find($numeroCompte);
        if (!$compte) {
            return $this->error('Compte introuvable', 404);
        }

        // Vérifier que c'est bien le compte de l'utilisateur connecté
        if ($compte->user_id !== $user->id) {
            return $this->error('Accès non autorisé à ce compte', 403);
        }

        return $this->success([
            'compte_id' => $compte->id,
            'solde' => $compte->solde,
            'devise' => $compte->devise,
            'dernier_maj' => $compte->updated_at
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/compte/dashboard",
     *     summary="Obtenir le tableau de bord du compte utilisateur",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Données du tableau de bord",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="is_phone_verified", type="boolean"),
     *                     @OA\Property(property="compte", ref="#/components/schemas/Compte"),
     *                     @OA\Property(property="qr_code", ref="#/components/schemas/QrCode"),
     *                     @OA\Property(property="recent_transactions", type="array", @OA\Items(ref="#/components/schemas/Transaction"))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Ensure compte exists
        $user->load('compte');
        if (!$user->compte) {
            $user->compte()->create(['solde' => 500]);
            $user->load('compte');
        }

        // Generate QR code if not exists
        $qrCode = $user->qrCodes()->first();
        if (!$qrCode) {
            $qrCode = $user->qrCodes()->create([
                'code' => \Illuminate\Support\Str::random(40),
                'meta' => [
                    'code_marchand' => 'USER_' . $user->id,
                    'type' => 'user_qr',
                    'generated_at' => \Illuminate\Support\Carbon::now()->toISOString()
                ]
            ]);
        }

        // Get recent transactions
        $transactions = $user->compte->transactions()
            ->whereIn('type', ['transfert_debit', 'transfert_credit', 'transfert', 'paiement_debit', 'paiement_credit', 'paiement', 'depot', 'retrait'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $this->success([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'is_phone_verified' => $user->is_phone_verified,
                'compte' => $user->compte,
                'qr_code' => $qrCode,
                'recent_transactions' => $transactions
            ]
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/compte/transfert",
     *     summary="Effectuer un transfert d'argent vers un autre utilisateur",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"montant"},
     *             @OA\Property(property="montant", type="number", example=25.00),
     *             @OA\Property(property="to_compte_id", type="string", format="uuid", example="uuid-here"),
     *             @OA\Property(property="to_phone", type="string", example="771234567")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfert effectué avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfert effectué")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur lors du transfert",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function transfert(Request $request)
    {
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'to_phone' => ['nullable', 'string'],
            'to_compte_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        try {
            $to = [];
            if (! empty($data['to_phone'])) $to['phone'] = $data['to_phone'];
            if (! empty($data['to_compte_id'])) $to['compte_id'] = $data['to_compte_id'];

            $result = $this->service->transfert($user, $to, (float) $data['montant']);
            return $this->success($result, 'Transfert effectué');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
