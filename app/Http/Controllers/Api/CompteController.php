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
 *
 * @OA\Schema(
 *     schema="QrCode",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="meta", type="object", additionalProperties=true)
 * )
 *
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="compte_id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string", enum={"transfert_debit", "transfert_credit", "transfert", "paiement_debit", "paiement_credit", "paiement", "depot", "retrait"}, example="transfert_debit"),
 *     @OA\Property(property="montant", type="number", format="float"),
 *     @OA\Property(property="status", type="string", example="completed"),
 *     @OA\Property(property="counterparty", type="string", format="uuid", description="ID du compte contrepartie"),
 *     @OA\Property(property="metadata", type="object", description="Données supplémentaires (QR code, etc.)"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
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
     * @OA\Post(
     *     path="/api/compte/paiement",
     *     summary="Effectuer un paiement à l'aide d'un code marchand",
     *     tags={"Comptes"},
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
    public function paiement(Request $request)
    {
        $data = $request->validate([
            'code_marchand' => ['required', 'string'],
            'montant' => ['required', 'numeric', 'min:0.01'],
        ]);

        $user = $request->user();

        // Chercher le QR code par code_marchand dans les métadonnées
        $qr = \App\Models\QrCode::whereJsonContains('meta->code_marchand', $data['code_marchand'])->first();
        if (! $qr) {
            return $this->error('Code marchand invalide', 404);
        }

        try {
            $montant = (float) $data['montant'];
            $result = $this->service->payerParQr($user, $qr, $montant);
            return $this->success($result, 'Paiement effectué');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/compte/{numeroCompte}/transactions",
     *     summary="Lister les transactions d'un compte",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="numeroCompte",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         description="Numéro du compte (UUID)"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         @OA\Schema(type="integer", default=15),
     *         description="Nombre d'éléments par page"
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         @OA\Schema(type="string", enum={"transfert_debit", "transfert_credit", "transfert", "paiement_debit", "paiement_credit", "paiement", "depot", "retrait"}),
     *         description="Filtrer par type de transaction"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Transaction")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer")
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
    public function transactions(Request $request, string $numeroCompte)
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

        $perPage = (int) $request->query('per_page', 15);
        $q = \App\Models\Transaction::where('compte_id', $compte->id)
            ->whereIn('type', ['transfert_debit', 'transfert_credit', 'transfert', 'paiement_debit', 'paiement_credit', 'paiement', 'depot', 'retrait'])
            ->orderBy('created_at', 'desc');

        if ($type = $request->query('type')) {
            $allowedTypes = ['transfert_debit', 'transfert_credit', 'transfert', 'paiement_debit', 'paiement_credit', 'paiement', 'depot', 'retrait'];
            if (in_array($type, $allowedTypes)) {
                $q->where('type', $type);
            }
        }

        $page = $q->paginate($perPage);

        // Formater les données des transactions
        $formattedTransactions = collect($page->items())->map(function ($transaction) {
            $data = [
                'id' => $transaction->id,
                'reference' => $transaction->id, // Utiliser l'ID comme référence
                'type' => $transaction->type,
                'montant' => $transaction->montant,
                'date' => $transaction->created_at->toISOString(),
                'status' => $transaction->status,
            ];

            // Déterminer l'expéditeur et le destinataire selon le type de transaction
            switch ($transaction->type) {
                case 'transfert_debit':
                    $data['expediteur'] = 'Vous';
                    $data['destinataire'] = $this->getUserInfoByAccountId($transaction->counterparty);
                    $data['description'] = 'Transfert envoyé';
                    break;

                case 'transfert_credit':
                    $data['expediteur'] = $this->getUserInfoByAccountId($transaction->counterparty);
                    $data['destinataire'] = 'Vous';
                    $data['description'] = 'Transfert reçu';
                    break;

                case 'paiement_debit':
                    $data['expediteur'] = 'Vous';
                    $data['destinataire'] = $this->getUserInfoByAccountId($transaction->counterparty);
                    $data['description'] = 'Paiement QR';
                    break;

                case 'paiement_credit':
                    $data['expediteur'] = $this->getUserInfoByAccountId($transaction->counterparty);
                    $data['destinataire'] = 'Vous';
                    $data['description'] = 'Paiement QR reçu';
                    break;

                case 'depot':
                    $data['expediteur'] = 'Système';
                    $data['destinataire'] = 'Vous';
                    $data['description'] = 'Dépôt';
                    break;

                case 'retrait':
                    $data['expediteur'] = 'Vous';
                    $data['destinataire'] = 'Système';
                    $data['description'] = 'Retrait';
                    break;

                default:
                    $data['expediteur'] = 'Inconnu';
                    $data['destinataire'] = 'Inconnu';
                    $data['description'] = ucfirst(str_replace('_', ' ', $transaction->type));
            }

            return $data;
        });

        return response()->json([
            'status' => true,
            'data' => $formattedTransactions,
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
            ],
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

    /**
     * Helper method to get user info by account ID
     */
    private function getUserInfoByAccountId(string $accountId): string
    {
        $compte = $this->service->repo->find($accountId);
        if ($compte && $compte->user) {
            return $compte->user->name . ' (' . $compte->user->phone . ')';
        }
        return 'Utilisateur inconnu';
    }
}
