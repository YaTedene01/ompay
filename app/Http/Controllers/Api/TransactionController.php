<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $compte = $user->compte;
        if (! $compte) {
            return $this->success(['data' => [], 'meta' => ['total' => 0]]);
        }

        $perPage = (int) $request->query('per_page', 15);
        $q = Transaction::where('compte_id', $compte->id)
            ->whereIn('type', ['transfert_debit', 'transfert_credit', 'transfert', 'paiement_debit', 'paiement_credit', 'paiement', 'depot', 'retrait'])
            ->orderBy('created_at', 'desc');

        if ($type = $request->query('type')) {
            $allowedTypes = ['transfert_debit', 'transfert_credit', 'transfert', 'paiement_debit', 'paiement_credit', 'paiement', 'depot', 'retrait'];
            if (in_array($type, $allowedTypes)) {
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
