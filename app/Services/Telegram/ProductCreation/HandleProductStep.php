<?php

namespace App\Services\Telegram\ProductCreation;

use App\Models\User;
use App\Models\ProductSession;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\ProductCreation\NameStep;
use App\Services\Telegram\ProductCreation\PriceStep;
use App\Services\Telegram\ProductCreation\DescriptionStep;
use App\Services\Telegram\ProductCreation\ImageStep;

class HandleProductStep
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(array $message)
    {
        $telegramId = $message['from']['id'];
        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user || $user->role !== 'commercant') {
            $this->handler->sendDefaultResponse();
            return;
        }

        $session = ProductSession::where('user_id', $user->id)->first();

        if (!$session) {
            $this->handler->sendDefaultResponse();
            return;
        }

        $data = json_decode($session->data, true) ?? [];
        $text = $message['text'] ?? '';

        switch ($session->step) {
            case 'name':
                (new NameStep($this->handler))->execute($text, $session, $data);
                break;
            case 'price':
                (new PriceStep($this->handler))->execute($text, $session, $data);
                break;
            case 'description':
                (new DescriptionStep($this->handler))->execute($text, $session, $data);
                break;
            case 'image':
                (new ImageStep($this->handler))->execute($message, $user, $session, $data);
                break;
            default:
                $this->handler->sendDefaultResponse();
        }
    }
}