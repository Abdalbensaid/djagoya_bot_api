<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Commands\HelpCommand;
use App\Services\Telegram\Commands\ProductsCommand;
use App\Services\Telegram\Commands\RegisterCommercantCommand;
use App\Services\Telegram\Commands\AddProductCommand;
use App\Services\Telegram\Commands\MenuCommand;

class TelegramService
{
    public function handleWebhook(array $data)
    {
        if (isset($data['message'])) {
            (new MessageHandler())->handle($data['message']);
        } elseif (isset($data['callback_query'])) {
            $this->handleCallback($data['callback_query']);
        }
    }

   protected function handleCallback(array $callback)
{
    if (!isset($callback['message']['chat']['id'])) {
        \Log::error('Erreur vérification chat_id', ['chat_id' => null, 'callback' => $callback]);
        return;
    }

    $chatId = $callback['message']['chat']['id'];
    $data = $callback['data'];

    $handler = new MessageHandler($chatId); // Assure-toi que le constructeur accepte bien $chatId

    match ($data) {
        'products' => (new ProductsCommand($handler))->execute(),
        'register_commercant' => (new RegisterCommercantCommand($handler))->execute(),
        'add_product' => (new AddProductCommand($handler))->execute(),
        'help' => (new HelpCommand($handler))->execute([
                'from' => $callback['from'] ?? [],
            ]),

        'menu' => (new MenuCommand($handler))->execute(),
        default => $handler->sendMessage("❌ Commande inconnue.")
    };
}

}

