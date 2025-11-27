<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
    }

    /**
     * Envoyer un SMS
     *
     * @param string $to Numéro de téléphone destinataire (format international)
     * @param string $message Contenu du message
     * @return bool
     */
    public function sendSms(string $to, string $message): bool
    {
        try {
            // Vérifier la configuration Twilio
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = config('services.twilio.from');

            Log::info('Configuration Twilio', [
                'sid_exists' => !empty($sid),
                'token_exists' => !empty($token),
                'from_exists' => !empty($from),
                'sid_prefix' => substr($sid, 0, 5) . '...',
                'from' => $from
            ]);

            if (!$sid || !$token || !$from) {
                Log::error('Configuration Twilio manquante', [
                    'sid' => $sid ? 'présent' : 'manquant',
                    'token' => $token ? 'présent' : 'manquant',
                    'from' => $from ? 'présent' : 'manquant'
                ]);
                return false;
            }

            // S'assurer que le numéro commence par +
            if (!str_starts_with($to, '+')) {
                $to = '+' . $to;
            }

            Log::info('Tentative d\'envoi SMS', [
                'to' => $to,
                'message_length' => strlen($message)
            ]);

            $result = $this->twilio->messages->create(
                $to,
                [
                    'from' => $from,
                    'body' => $message
                ]
            );

            Log::info('SMS envoyé avec succès', [
                'to' => $to,
                'message_id' => $result->sid ?? 'unknown',
                'status' => $result->status ?? 'unknown'
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du SMS', [
                'to' => $to,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Envoyer un SMS d'authentification
     *
     * @param string $phone Numéro de téléphone
     * @param string $token Token d'authentification
     * @return bool
     */
    public function sendAuthSms(string $phone, string $token): bool
    {
        $message = "OMPAY - Votre code d'authentification : {$token}\n\n" .
                  "Ce code expire dans 10 minutes.\n\n" .
                  "Ne partagez jamais ce code.";

        return $this->sendSms($phone, $message);
    }
}