<?php

namespace App\Services\Telegram\Commands;

use App\Models\User;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;

class StartCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(array $message)
    {
        $telegramId = $message['from']['id'] ?? null;
        $user = User::where('telegram_id', $telegramId)->first();

        $name = TelegramHelper::escapeMarkdownV2(
            trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? ''))
        );

        $text = "👋 *Bienvenue sur notre bot de e-commerce, {$name} !*\n\n" .
                "Voici ce que vous pouvez faire :";

        $buttons = [
            [['text' => '🛍️ Voir les produits', 'callback_data' => 'products']],
            [['text' => 'ℹ️ Aide', 'callback_data' => 'help']],
        ];

        if ($user) {
            if ($user->role === 'commercant') {
                $buttons[] = [['text' => '➕ Ajouter un produit', 'callback_data' => 'add_product']];
            } else {
                $buttons[] = [['text' => '📝 Devenir commerçant', 'callback_data' => 'register_commercant']];
            }
        } else {
            $buttons[] = [['text' => '🚀 Créer un compte', 'callback_data' => 'register']];
        }

        $keyboard = ['inline_keyboard' => $buttons];

        $this->handler->sendMessage($text, 'MarkdownV2', $keyboard);
    }
}
