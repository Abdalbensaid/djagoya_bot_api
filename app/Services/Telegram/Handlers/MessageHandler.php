<?php

namespace App\Services\Telegram\Handlers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\Telegram\Commands\StartCommand;
use App\Services\Telegram\Commands\ProductsCommand;
use App\Services\Telegram\Commands\RegisterCommercantCommand;
use App\Services\Telegram\Commands\AddProductCommand;
use App\Services\Telegram\Commands\HelpCommand;
use App\Services\Telegram\ProductCreation\HandleProductStep;
use App\Services\Telegram\Helpers\TelegramHelper;

class MessageHandler
{
    protected $token;
    protected $chatId;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        Log::debug('Token Telegram chargé', ['token' => substr($this->token, 0, 5) . '...']);
    }

    public function handle(array $payload)
    {
        
            Log::debug('Payload reçu', ['payload' => $payload]);

            if (isset($payload['callback_query'])) {
                return $this->handleCallbackQuery($payload['callback_query']);
            }

            // À ce stade, $payload est déjà le message
            $message = $payload;

            if (!isset($message['chat']['id'])) {
                Log::error('Message invalide : chat ID manquant', $message);
                return;
            }

            $this->chatId = $message['chat']['id'];
            Log::debug('Chat ID défini', ['chat_id' => $this->chatId]);

            $text = $message['text'] ?? ($message['caption'] ?? '');
            Log::debug('Texte extrait', ['text' => $text]);

            $command = explode(' ', $text)[0];
            Log::debug('Commande détectée', ['command' => $command]);

            switch ($command) {
                case '/start':
                    (new StartCommand($this))->execute($message);
                    break;
                    case '/products':
                        (new ProductsCommand($this))->execute();
                        break;
                    case '/register_commercant':
                        (new RegisterCommercantCommand($this))->execute($message);
                        break;
                    case '/add_product':
                        (new AddProductCommand($this))->execute($message);
                        break;
                    case '/help':
                        (new HelpCommand($this))->execute();
                        break;
                    case '/stop':
                        $this->sendMessage("Merci d'avoir utilisé notre service. À bientôt !");
                        break;
                    default:
                        (new HandleProductStep($this))->execute($message);
                }
    }

    public function handleCallbackQuery($callbackQuery)
    {
        $callbackData = $callbackQuery['data'];  // Récupère la callback_data
        $userId = $callbackQuery['from']['id'];  // Récupère l'ID de l'utilisateur
        $messageId = $callbackQuery['message']['message_id'];  // ID du message auquel on répond

        if (strpos($callbackData, 'buy_') === 0) {
            $productId = substr($callbackData, 4);  // Extrait l'ID du produit
            $this->handleProductPurchase($userId, $productId, $messageId);
        } elseif (strpos($callbackData, 'fav_') === 0) {
            $productId = substr($callbackData, 4);  // Extrait l'ID du produit
            $this->handleAddToFavorites($userId, $productId, $messageId);
        } else {
            $this->handler->sendMessage("❌ Commande non reconnue.", 'MarkdownV2');
        }
    }

    public function sendDefaultResponse(string $message = null)
    {
        $message = $message ?? "❓ Commande non reconnue. Tapez /help pour voir les commandes disponibles.";
        $this->sendMessage($message);
    }

    public function handleProductPurchase($userId, $productId, $messageId)
    {
        // Logique pour gérer l'achat
        $product = Product::find($productId);
        $user = User::where('telegram_id', $userId)->first();

        // Logique d'achat, mise à jour de la commande, envoi de confirmation, etc.
        $this->handler->sendMessage("✅ Vous avez acheté *{$product->name}* pour *{$product->price} FCFA*.", 'MarkdownV2');
    }

    public function handleAddToFavorites($userId, $productId, $messageId)
    {
        // Logique pour ajouter le produit aux favoris
        $product = Product::find($productId);
        $user = User::where('telegram_id', $userId)->first();

        // Ajout aux favoris, envoi de confirmation, etc.
        $this->handler->sendMessage("❤️ *{$product->name}* a été ajouté à vos favoris.", 'MarkdownV2');
    }

    public function sendMessage(string $text, string $parseMode = 'MarkdownV2', array $replyMarkup = null)
    {
        try {
            if ($parseMode === 'MarkdownV2') {
                $text = TelegramHelper::escapeMarkdownV2($text);
            }

            $payload = [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];

            if (!is_null($replyMarkup)) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            $response = Http::timeout(10)
                ->retry(2, 100)
                ->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);

            if ($response->failed()) {
                return $this->sendPlainMessage($text);
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Échec sendMessage', [
                'error' => $e->getMessage(),
                'text' => $text
            ]);
            return $this->sendPlainMessage($text);
        }
    }


    public function sendPlainMessage(string $text)
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => htmlspecialchars_decode(strip_tags($text))
            ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::critical('Échec critique sendPlainMessage', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendPhotoWithCaption($chatId, $photoUrl, $caption, $parseMode = 'MarkdownV2')
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'photo' => $photoUrl,
                'caption' => TelegramHelper::escapeMarkdownV2($caption),
                'parse_mode' => $parseMode
            ];

            $response = Http::timeout(15)
                ->retry(3, 200)
                ->post("https://api.telegram.org/bot{$this->token}/sendPhoto", $payload);

            if ($response->failed()) {
                Log::error('Échec envoi photo', ['response' => $response->body()]);
                Http::post("https://api.telegram.org/bot{$this->token}/sendPhoto", [
                    'chat_id' => $chatId,
                    'photo' => $photoUrl
                ]);
                $this->sendMessage($caption, $parseMode);
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Exception sendPhotoWithCaption', ['error' => $e->getMessage()]);
            $this->sendPlainMessage("📷 Photo envoyée (erreur de légende)");
        }
    }
    

    public function getTelegramFilePath($fileId)
    {
        $response = Http::get("https://api.telegram.org/bot{$this->token}/getFile", [
            'file_id' => $fileId
        ]);

        return $response->json('result.file_path');
    }

    public function getChatId()
    {
        return $this->chatId;
    }

    public function getToken()
    {
        return $this->token;
    }
}