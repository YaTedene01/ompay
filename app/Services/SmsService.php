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
            // S'assurer que le numéro commence par +
            if (!str_starts_with($to, '+')) {
                $to = '+' . $to;
            }

            $this->twilio->messages->create(
                $to,
                [
                    'from' => config('services.twilio.from'),
                    'body' => $message
                ]
            );

            Log::info('SMS envoyé avec succès', [
                'to' => $to,
                'message' => substr($message, 0, 50) . '...'
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du SMS', [
                'to' => $to,
                'error' => $e->getMessage()
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