<?php

namespace App\Services\Telegram\Commands;

use App\Models\User;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;

class HelpCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(array $message)
    {
        $telegramId = $message['from']['id'] ?? $this->handler->getChatId() ?? null;


        if (!$telegramId) {
            $this->handler->sendDefaultResponse("Impossible de déterminer votre identifiant Telegram.");
            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();

        $text = TelegramHelper::escapeMarkdownV2(
            "ℹ️ *Aide* : Commandes disponibles"
        );

        $buttons = [
            [['text' => '🚀 Créer un compte', 'callback_data' => 'start']],
            [['text' => '🛍️ Voir les produits', 'callback_data' => 'products']],
            [['text' => '📝 Devenir commerçant', 'callback_data' => 'register_commercant']],
            [['text' => '➕ Ajouter un produit', 'callback_data' => 'add_product']],
        ];

        if ($user) {
            if ($user->role === 'commercant') {
                $buttons = array_filter($buttons, fn($btn) => $btn[0]['callback_data'] !== 'register_commercant');
            } else {
                $buttons = array_filter($buttons, fn($btn) => $btn[0]['callback_data'] !== 'add_product');
            }
        }

        $keyboard = ['inline_keyboard' => array_values($buttons)];

        $this->handler->sendMessage($text, 'MarkdownV2', $keyboard);
    }

}
