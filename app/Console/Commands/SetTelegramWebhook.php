<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook 
                            {--url= :https://excited-clearly-killdeer.ngrok-free.app/api/telegram/webhook}';

    protected $description = 'Définir l’URL de webhook pour le bot Telegram';

    public function handle()
    {
        $botToken = config('services.telegram.bot_token');

        $webhookUrl = $this->option('url');

        if (!$webhookUrl) {
            $this->error('❌ Veuillez spécifier l\'URL via --url');
            return;
        }

        $response = Http::post("https://api.telegram.org/bot{$botToken}/setWebhook", [
            'url' => $webhookUrl
        ]);

        if ($response->ok()) {
            $this->info('✅ Webhook Telegram défini avec succès :');
            $this->info($webhookUrl);
        } else {
            $this->error('❌ Erreur lors de la définition du webhook :');
            $this->error($response->body());
        }
    }
}
