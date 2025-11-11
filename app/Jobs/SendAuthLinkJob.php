<?php

namespace App\Jobs;

use App\Models\AuthLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SendAuthLinkJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public AuthLink $link;

    public function __construct(AuthLink $link)
    {
        $this->link = $link;
    }

    public function handle()
    {
        // Construire le lien que l'utilisateur utilisera
        $front = $this->link->data['redirect'] ?? env('APP_URL');
        $url = rtrim($front, '/') . '/auth/verify?temp_token=' . $this->link->token;

        // Message SMS avec le token directement
        $message = "OMPAY - Votre code d'authentification : {$this->link->token}\n\n" .
                  "Ce code expire dans 10 minutes.\n\n" .
                  "Ne partagez jamais ce code.";

        // Choix du provider via .env (par ex. SMS_PROVIDER=twilio)
        $provider = env('SMS_PROVIDER', 'log');

        if ($provider === 'twilio') {
            // Vars attendues: TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM
            $sid = env('TWILIO_SID');
            $token = env('TWILIO_TOKEN');
            $from = env('TWILIO_FROM');

            if (! $sid || ! $token || ! $from) {
                Log::warning("[SendAuthLinkJob] Twilio configuré mais variables manquantes. Fallback to log. Phone: {$this->link->phone}");
                Log::info("[SendAuthLinkJob] Link: $url");
                return;
            }

            $to = $this->link->phone;
            if (!str_starts_with($to, '+')) {
                $to = '+' . $to;
            }

            try {
                $response = Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from,
                        'To' => $to,
                        'Body' => $message,
                    ]);

                if ($response->successful()) {
                    Log::info("[SendAuthLinkJob] SMS envoyé via Twilio à {$to}");
                } else {
                    Log::error("[SendAuthLinkJob] Erreur Twilio: " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error("[SendAuthLinkJob] Exception lors de l'envoi Twilio: " . $e->getMessage());
            }

            return;
        }

        // Par défaut on logge (utile en dev)
        Log::info("[SendAuthLinkJob] Send link for phone {$this->link->phone}: $url");
    }
}
