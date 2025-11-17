<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAuthLinkJob;
use App\Models\AuthLink;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * @OA\Info(
 *     title="OMPAY API",
 *     version="1.0.0",
 *     description="Documentation OpenAPI pour l'API OMPAY. Application de paiement mobile avec authentification par SMS, transferts d'argent et paiements QR."
 * )
 *
 * @OA\Server(
 *     url="https://ompay-wex1.onrender.com",
 *     description="Production"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean"),
 *     @OA\Property(property="message", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="AuthToken",
 *     type="object",
 *     @OA\Property(property="access_token", type="string"),
 *     @OA\Property(property="token_type", type="string"),
 *     @OA\Property(property="expires_in", type="integer")
 * )
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/auth/envoyer-lien",
     *     summary="Envoyer un lien d'authentification",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string", example="771234567"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token généré pour les tests",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="abc123def456..."),
     *                 @OA\Property(property="link", type="string", example="https://ompay-wex1.onrender.com/auth/verify?temp_token=abc123..."),
     *                 @OA\Property(property="expires_in", type="integer", example=600),
     *                 @OA\Property(property="message", type="string", example="Utilisez ce token pour vous connecter")
     *             ),
     *             @OA\Property(property="message", type="string", example="Token généré pour les tests.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function sendLink(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $token = Str::random(48);
        $expires = Carbon::now()->addMinutes((int) env('AUTH_LINK_EXPIRES', 10));

        $link = AuthLink::create([
            'phone' => $data['phone'],
            'token' => $token,
            'data' => ['redirect' => env('APP_URL')],
            'expires_at' => $expires,
        ]);

        // Temporairement désactivé l'envoi SMS pour les tests
        // dispatch(new SendAuthLinkJob($link));

        $linkUrl = env('APP_URL') . '/auth/verify?temp_token=' . $token;

        return $this->success([
            'token' => $token,
            'link' => $linkUrl,
            'expires_in' => (int) env('AUTH_LINK_EXPIRES', 10) * 60, // en secondes
            'message' => 'Utilisez ce token pour vous connecter'
        ], 'Token généré pour les tests.');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/echange",
     *     summary="Échanger un code d'authentification contre un token d'accès",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"temp_token"},
     *             @OA\Property(property="temp_token", type="string", example="abc123...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Authentification réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             ),
     *             @OA\Property(property="message", type="string", example="Authentification réussie")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Code invalide",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function echange(Request $request)
    {
        $data = $request->validate(['temp_token' => ['required', 'string']]);

    $link = AuthLink::where('token', $data['temp_token'])->first();
        if (! $link) {
            return $this->error('Token invalide', 404);
        }
        if ($link->used_at) {
            return $this->error('Ce lien a déjà été utilisé', 400);
        }
        if (Carbon::now()->greaterThan($link->expires_at)) {
            return $this->error('Le lien a expiré', 400);
        }

        $user = User::firstWhere('phone', $link->phone);
        if (! $user) {
            // Crée un utilisateur minimal requis par la table users
            $user = User::create([
                'phone' => $link->phone,
                'name' => 'User '.$link->phone,
                'email' => Str::lower(Str::slug($link->phone)).'@example.local',
                'password' => bcrypt(Str::random(12)),
            ]);
        }

        // Marque le téléphone comme vérifié
        $user->is_phone_verified = true;
        $user->phone_verified_at = $user->phone_verified_at ?? Carbon::now();
        $user->save();

        // Ensure compte exists 
        $user->load('compte');
        if (! $user->compte) {
            $user->compte()->create(['solde' => 500]);
            $user->load('compte');
        }

        $link->used_at = Carbon::now();
        $link->save();

        // Générer automatiquement un QR code pour l'utilisateur
        $qrCode = $user->qrCodes()->create([
            'code' => Str::random(40),
            'meta' => [
                'code_marchand' => 'USER_' . $user->id,
                'type' => 'user_qr',
                'generated_at' => Carbon::now()->toISOString()
            ]
        ]);

        // Récupérer les dernières transactions
        $transactions = $user->compte->transactions()
            ->whereIn('type', ['transfert', 'paiement'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $tokenResult = $user->createToken('mobile');
        $accessToken = $tokenResult->accessToken;

        return $this->success([
            'access_token' => $accessToken,
            'token_type' => 'Bearer'
        ], 'Authentification réussie');
    }
}
