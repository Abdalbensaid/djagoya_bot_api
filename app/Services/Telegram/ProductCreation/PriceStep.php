<?php

namespace App\Services\Telegram\ProductCreation;

use App\Models\ProductSession;
use App\Services\Telegram\Handlers\MessageHandler;

class PriceStep
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(string $text, ProductSession $session, array $data)
    {
        if (!is_numeric($text)) {
            $this->handler->sendMessage("❌ Prix invalide. Entrez un nombre valide (ex : 10000)");
            return;
        }
        
        $data['price'] = $text;
        $session->update([
            'step' => 'description',
            'data' => json_encode($data)
        ]);
        $this->handler->sendMessage("🖊️ Entrez la *description du produit* :", 'Markdown');
    }
}