<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Handlers\MessageHandler;

class TelegramService
{
    public function handleWebhook(array $data)
    {
        // Vérifie si le message existe
        if (isset($data['message'])) {
            // Délègue le traitement du message au gestionnaire
            (new MessageHandler())->handle($data['message']);
        }
    }
}
