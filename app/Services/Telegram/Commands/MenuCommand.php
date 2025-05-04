<?php

namespace App\Services\Telegram\Commands;

use App\Services\Telegram\Handlers\MessageHandler;

class MenuCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute()
    {
        $text = "📋 *Menu principal*\n\nChoisissez une option ci-dessous :";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🛍️ Voir les produits', 'callback_data' => 'products'],
                    ['text' => '📝 Devenir commerçant', 'callback_data' => 'register_commercant']
                ],
                [
                    ['text' => '➕ Ajouter un produit', 'callback_data' => 'add_product']
                ],
                [
                    ['text' => 'ℹ️ Aide', 'callback_data' => 'help']
                ],
            ]
        ];


        $this->handler->sendMessage($text, 'MarkdownV2', $keyboard);
    }
}
