<?php

namespace App\Services\Telegram\Handlers;

use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Product;
use App\Models\Message;

class MessageHandler
{
    protected $token;
    protected $chatId;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
    }

    public function handle(array $message)
    {
        if (!isset($message['chat']['id'])) {
            \Log::error('Message invalide: structure incorrecte', $message);
            return;
        }

        $this->chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        switch ($text) {
            case '/start':
                $this->handleStartCommand($message);
                break;
            case 'produits':
            case '/products':
                $this->sendProductList();
                break;
            default:
                $this->sendDefaultResponse();
        }
    }

    protected function handleStartCommand(array $message)
    {
        $telegramId = $message['from']['id'];
        $userData = [
            'name' => trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? '')),
            'username' => $message['from']['username'] ?? null,
            'role' => 'client'
        ];

        // Solution définitive pour éviter les doublons
        $user = User::firstOrNew(['telegram_id' => $telegramId]);
        
        if (!$user->exists) {
            $user->fill(array_merge($userData, [
                'email' => $this->generateUniqueEmail($telegramId),
                'password' => bcrypt(uniqid())
            ]));
            $user->save();
        } else {
            $user->update($userData);
        }

        // Enregistrement des messages
        Message::create([
            'user_id' => $user->id,
            'message' => $message['text'] ?? '',
            'is_from_bot' => false
        ]);

        $welcomeMessage = "👋 Bonjour *{$user->name}*, bienvenue dans notre boutique Telegram !\n\n"
            . "Commandes disponibles :\n"
            . "/products - Voir nos produits\n"
            . "/help - Aide";

        Message::create([
            'user_id' => $user->id,
            'message' => $welcomeMessage,
            'is_from_bot' => true
        ]);

        $this->sendMessage($welcomeMessage, 'Markdown');
    }

    protected function generateUniqueEmail($telegramId): string
    {
        $base = "tg_{$telegramId}";
        $email = "{$base}@telegram.example";
        $count = 1;

        while (User::where('email', $email)->exists()) {
            $email = "{$base}_{$count}@telegram.example";
            $count++;
        }

        return $email;
    }

    protected function sendProductList()
    {
        $products = Product::take(5)->get();

        if ($products->isEmpty()) {
            $this->sendMessage("Aucun produit disponible pour le moment.");
            return;
        }

        $keyboard = $products->map(function ($product) {
            return [
                [
                    'text' => "{$product->name} - {$product->price} FCFA",
                    'callback_data' => "product_{$product->id}"
                ]
            ];
        })->toArray();

        $this->sendMessage(
            "🛒 Sélectionnez un produit :",
            'Markdown',
            ['inline_keyboard' => $keyboard]
        );
    }

    protected function sendDefaultResponse()
    {
        $this->sendMessage(
            "Commande non reconnue. Essayez /products pour voir nos articles.",
            'Markdown'
        );
    }

    protected function sendMessage(string $text, string $parseMode = null, array $replyMarkup = null)
    {
        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }
}