<?php

namespace App\Services\Telegram\ProductCreation;

use App\Models\ProductSession;
use App\Services\Telegram\Handlers\MessageHandler;

class NameStep
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(string $text, ProductSession $session, array $data)
    {
        $data['name'] = $text;
        $session->update([
            'step' => 'price',
            'data' => json_encode($data)
        ]);
        $this->handler->sendMessage("💰 Entrez le *prix du produit* (en FCFA) :", 'Markdown');
    }
}