<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

class TestTwilioConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-twilio-config {phone?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester la configuration Twilio et envoyer un SMS de test';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Test de la configuration Twilio...');

        // Vérifier les variables d'environnement
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        $this->line('📋 Variables d\'environnement :');
        $this->line('  TWILIO_SID: ' . ($sid ? '✅ Présent (' . substr($sid, 0, 8) . '...)' : '❌ Manquant'));
        $this->line('  TWILIO_TOKEN: ' . ($token ? '✅ Présent (' . substr($token, 0, 8) . '...)' : '❌ Manquant'));
        $this->line('  TWILIO_FROM: ' . ($from ? '✅ Présent (' . $from . ')' : '❌ Manquant'));

        if (!$sid || !$token || !$from) {
            $this->error('❌ Configuration Twilio incomplète !');
            $this->line('Vérifiez votre fichier .env ou les variables d\'environnement dans votre déploiement.');
            return 1;
        }

        // Tester la connexion Twilio
        $this->line('🔗 Test de connexion à Twilio...');
        try {
            $smsService = app(SmsService::class);
            $this->info('✅ Connexion Twilio réussie !');
        } catch (\Exception $e) {
            $this->error('❌ Erreur de connexion Twilio: ' . $e->getMessage());
            return 1;
        }

        // Tester l'envoi d'un SMS si un numéro est fourni
        $phone = $this->argument('phone');
        if ($phone) {
            $this->line("📱 Test d'envoi SMS vers: {$phone}");
            $testMessage = "OMPAY - Test de configuration Twilio - " . now()->format('H:i:s');

            $result = $smsService->sendSms($phone, $testMessage);

            if ($result) {
                $this->info('✅ SMS de test envoyé avec succès !');
            } else {
                $this->error('❌ Échec de l\'envoi du SMS de test');
                $this->line('Consultez les logs Laravel pour plus de détails.');
            }
        } else {
            $this->warn('⚠️  Aucun numéro de téléphone fourni. Utilisez: php artisan app:test-twilio-config +221XXXXXXXXX');
        }

        $this->info('🎉 Test terminé !');
        return 0;
    }
}
