<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAuthLinkJob;
use App\Models\AuthLink;
use App\Models\User;
use App\Services\SmsService;
use App\Services\UserService;
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

    public UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/send-otp",
     *     summary="Envoyer un code OTP d'authentification",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string", example="771234567"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP envoyé par SMS",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Code OTP envoyé par SMS")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        // Générer un code OTP de 4 chiffres
        $otp = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $expires = Carbon::now()->addMinutes((int) env('OTP_EXPIRES', 5));

        $link = AuthLink::create([
            'phone' => $data['phone'],
            'token' => $otp,
            'data' => ['type' => 'otp'],
            'expires_at' => $expires,
        ]);

        // Envoyer le SMS avec l'OTP
        $smsService = app(SmsService::class);
        $message = "OMPAY - Votre code de connexion : {$otp}\n\nCe code expire dans 5 minutes.";

        $smsSent = $smsService->sendSms($data['phone'], $message);
        if (!$smsSent) {
            return $this->error('Erreur lors de l\'envoi du SMS', 500);
        }

        return $this->success(null, 'Code OTP envoyé par SMS');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/verify-otp",
     *     summary="Vérifier le code OTP et obtenir un token d'accès",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *             @OA\Property(property="phone", type="string", example="771234567"),
     *             @OA\Property(property="otp", type="string", example="1234")
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
     *         description="Code OTP invalide",
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
    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/']
        ]);

        $link = AuthLink::where('phone', $data['phone'])
            ->where('token', $data['otp'])
            ->where('data->type', 'otp')
            ->first();

        if (! $link) {
            return $this->error('Code OTP invalide', 400);
        }
        if ($link->used_at) {
            return $this->error('Ce code OTP a déjà été utilisé', 400);
        }
        if (Carbon::now()->greaterThan($link->expires_at)) {
            return $this->error('Le code OTP a expiré', 400);
        }

        $user = $this->userService->createUserForPhone($link->phone);

        // Marque le téléphone comme vérifié
        $this->userService->verifyPhone($user);

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

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Déconnexion de l'utilisateur",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return $this->success(null, 'Déconnexion réussie');
    }
}
