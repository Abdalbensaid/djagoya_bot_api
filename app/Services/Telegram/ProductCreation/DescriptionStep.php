<?php

namespace App\Services\Telegram\ProductCreation;

use App\Models\ProductSession;
use App\Services\Telegram\Handlers\MessageHandler;

class DescriptionStep
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(string $text, ProductSession $session, array $data)
    {
        $data['description'] = $text;
        $session->update([
            'step' => 'image',
            'data' => json_encode($data)
        ]);
        $this->handler->sendMessage("📸 Envoyez maintenant une *photo* du produit :", 'Markdown');
    }
}