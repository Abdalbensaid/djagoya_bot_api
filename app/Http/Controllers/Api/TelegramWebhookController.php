<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected $telegramToken;

    public function __construct()
    {
        // Récupération du token depuis la config services
        $this->telegramToken = config('services.telegram.bot_token');
    }

    public function handleWebhook(Request $request)
    {
        Log::info('Telegram webhook reçu : ', $request->all());

        $message = $request->input('message.text');
        $chat_id = $request->input('message.chat.id');

        // Utilisation du token dans l'URL
        $response = Http::post("https://api.telegram.org/bot{$this->telegramToken}/sendMessage", [
            'chat_id' => $chat_id,
            'text' => "Tu as dit : $message",
        ]);

        return response()->json(['status' => 'ok']);
    }
}