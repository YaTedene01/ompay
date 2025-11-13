<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="compte_id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string", enum={"transfert", "paiement"}, example="transfert"),
 *     @OA\Property(property="montant", type="number", format="float"),
 *     @OA\Property(property="status", type="string", example="completed"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class TransactionController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/transactions",
     *     summary="Lister les transactions de l'utilisateur",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         @OA\Schema(type="integer", default=15),
     *         description="Nombre d'éléments par page"
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         @OA\Schema(type="string", enum={"transfert", "paiement"}),
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
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $compte = $user->compte;
        if (! $compte) {
            return $this->success(['data' => [], 'meta' => ['total' => 0]]);
        }

        $perPage = (int) $request->query('per_page', 15);
        $q = Transaction::where('compte_id', $compte->id)
            ->whereIn('type', ['transfert', 'paiement'])
            ->orderBy('created_at', 'desc');

        if ($type = $request->query('type')) {
            if (in_array($type, ['transfert', 'paiement'])) {
                $q->where('type', $type);
            }
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $page->items(),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
            ],
        ]);
    }
}
