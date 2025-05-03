<?php

namespace App\Services\Telegram\Handlers;

use Illuminate\Support\Facades\Http;

class MessageHandler
{
    protected $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
    }

    public function handle(array $message)
    {
        $chatId = $message['chat']['id'];
        $text   = $message['text'] ?? '';

        $reply = "Bonjour ! 👋 Vous avez dit : \"$text\"";

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $reply
        ]);
    }
}
