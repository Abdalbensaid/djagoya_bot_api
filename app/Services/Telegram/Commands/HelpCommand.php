<?php

namespace App\Services\Telegram\Commands;

use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;

class HelpCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute()
    {
        $helpText = TelegramHelper::escapeMarkdownV2(
            "ℹ️ *Aide* : Commandes disponibles\n\n" .
            "/start - Créer un compte\n" .
            "/products - Voir les produits\n" .
            "/register_commercant - Devenir commerçant\n" .
            "/add_product - Ajouter un produit (commerçants)\n" .
            "/help - Afficher ce message"
        );

        $this->handler->sendMessage($helpText, 'MarkdownV2');
    }
}
