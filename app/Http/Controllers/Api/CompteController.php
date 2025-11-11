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

    /**
     * @OA\Get(
     *     path="/api/compte",
     *     summary="Consulter les informations du compte bancaire",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations du compte retournées",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="compte", ref="#/components/schemas/Compte")
     *             )
     *         )
     *     )
     * )
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $compte = $this->service->getOrCreateForUser($user);
        return $this->success($compte);
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
