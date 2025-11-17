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
            $from = env('TWILIO_FROeyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI1IiwianRpIjoiYjc4ZjY5M2QxNmZjZWQ4YTJkM2JjMDY2M2YxMDg2MTVjN2M3YmQxNGNmY2ZmNWY1YjlmYzIxNmJjYTcwZjgyNzhlNjlmODFhMWU4NDNmZmMiLCJpYXQiOjE3NjMzNzA5MjQuNTEwMjEzLCJuYmYiOjE3NjMzNzA5MjQuNTEwMjE3LCJleHAiOjE3OTQ5MDY5MjQuNDIwMjY0LCJzdWIiOiIxNSIsInNjb3BlcyI6W119.K0n0BtVYnElAkakXfYOlouvUkZGp__AKZlPoaRkh4zoY3lZ3vdRt50noTXJ7s_ff-kJlIotgex9iKNt9lGwuUZPJ8jc5S5lP84bs_98S4KBy1WsWVFxhcelijQKLCPXNdpQlGsctMyrZyH88XB_fJaCEvab532l6cMjHna4VprqMWm5aWA-sbSXSParv220PmmR1fh_3x63oiHnWhM8RePklbv4u9gyPq5pO-uytw9Tf7H5IeiHwVQQy3ZaPsE-OrAyTwV_cWz0IFLzA1wOss-HCmsjNkiNe8MSdacuMk-qrQgdNrOe7Dj2Q2pod8OEKKlzdtn_F6j2SMtPndw-DqvylqrEPF9xGB-1qehkinhyjm-rA1xP2ECPWhUY-cACocYughUVSGnnD95vXFtY8CvkTnXbARjKCU1MjBzpQbDIz-2wFRmVRFzyKS84T6zLGKJaQ94WXCSeh5Kz3KyhaGDa2HjDn1UYNUZFZDiMSdPfIBetewGKHd7z0_OBNcivuUZ3GHNLqhsjsVGO6uTy9mpUYimRo3mnSXVyhsnL-fvJVjFV_QoJsWx0J3aTHk7yENuWMhK8yOIGpEWKXke1zym7vMq8HxKaBjbgBT2xJjeqXRb7_0bCesQpRStLYY1NlFFCJso_LH9jqk2xYVZ-SSnl5DEHtgsp8zfJIIPuiI40M');

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
