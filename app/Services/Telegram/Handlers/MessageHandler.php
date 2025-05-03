<?php

namespace App\Services\Telegram\Handlers;

use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Product;
use App\Models\Message;
use App\Models\ProductSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MessageHandler
{
    protected $token;
    protected $chatId;

  public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        Log::debug('Token Telegram chargé', ['token' => substr($this->token, 0, 5) . '...']); // Log partiel du token
    }

    public function handle(array $message)
    {
        Log::debug('Message reçu complet', ['message' => $message]);

        if (!isset($message['chat']['id'])) {
            Log::error('Message invalide: structure incorrecte', $message);
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
                $this->handleStartCommand($message);
                break;
            case '/products':
                $this->sendProductList();
                break;
            case '/register_commercant':
                $this->registerAsCommercant($message);
                break;
            case '/add_product':
                $this->initProductSession($message);
                break;
            case '/help':
                $this->sendHelpMessage();
                break;
            case '/stop':
                $this->sendMessage("Merci d'avoir utilisé notre service. À bientôt !");
                break;
            default:
                $this->handleProductCreationStep($message);
        }
    }

    protected function handleProductCreationStep(array $message)
    {
        $telegramId = $message['from']['id'];
        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user || $user->role !== 'commercant') {
            $this->sendDefaultResponse();
            return;
        }

        $session = ProductSession::where('user_id', $user->id)->first();

        if (!$session) {
            $this->sendDefaultResponse();
            return;
        }

        $data = json_decode($session->data, true) ?? [];
        $text = $message['text'] ?? '';

        switch ($session->step) {
            case 'name':
                $this->handleProductNameStep($text, $session, $data);
                break;
            case 'price':
                $this->handleProductPriceStep($text, $session, $data);
                break;
            case 'description':
                $this->handleProductDescriptionStep($text, $session, $data);
                break;
            case 'image':
                $this->handleProductImageStep($message, $user, $session, $data);
                break;
            default:
                $this->sendDefaultResponse();
        }
    }

    protected function handleProductNameStep(string $text, ProductSession $session, array $data)
    {
        $data['name'] = $text;
        $session->update([
            'step' => 'price',
            'data' => json_encode($data)
        ]);
        $this->sendMessage("💰 Entrez le *prix du produit* (en FCFA) :", 'Markdown');
    }

    protected function handleProductPriceStep(string $text, ProductSession $session, array $data)
    {
        if (!is_numeric($text)) {
            $this->sendMessage("❌ Prix invalide. Entrez un nombre valide (ex : 10000)");
            return;
        }
        
        $data['price'] = $text;
        $session->update([
            'step' => 'description',
            'data' => json_encode($data)
        ]);
        $this->sendMessage("🖊️ Entrez la *description du produit* :", 'Markdown');
    }

    protected function handleProductDescriptionStep(string $text, ProductSession $session, array $data)
    {
        $data['description'] = $text;
        $session->update([
            'step' => 'image',
            'data' => json_encode($data)
        ]);
        $this->sendMessage("📸 Envoyez maintenant une *photo* du produit :", 'Markdown');
    }


    protected function handleProductImageStep(array $message, User $user, ProductSession $session, array $data)
    {
        if (!isset($message['photo'])) {
            $this->sendMessage("❌ Veuillez envoyer une photo valide du produit.");
            return;
        }

        try {
            $photo = end($message['photo']);
            $filePath = $this->getTelegramFilePath($photo['file_id']);
            $imageUrl = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
            $imageContents = Http::get($imageUrl)->body();
            
            $filename = 'products/product_'.time().'.jpg';
            Storage::disk('public')->put($filename, $imageContents);

            Product::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'price' => $data['price'],
                'description' => $data['description'],
                'image' => $filename,
            ]);

            $session->delete();

            $this->sendPhotoWithCaption(
                $this->chatId,
                Storage::url($filename),
                "✅ *Produit ajouté avec succès !*\n\n".
                "📦 Nom : *{$data['name']}*\n".
                "💰 Prix : *{$data['price']} FCFA*\n".
                "📝 Description : _{$data['description']}_",
                'Markdown'
            );

            $this->sendMessage("➕ Tapez /add_product pour ajouter un autre produit.", 'Markdown');
        } catch (\Exception $e) {
            Log::error('Erreur création produit', ['error' => $e->getMessage()]);
            $this->sendMessage("❌ Une erreur est survenue lors de l'ajout du produit.");
        }
    }

     protected function handleStartCommand(array $message)
    {
        Log::debug('Début handleStartCommand', ['message' => $message]);
        
        try {
            $telegramId = $message['from']['id'];
            Log::debug('ID Telegram', ['id' => $telegramId]);
            
            $username = $message['from']['username'] ?? null;
            $name = trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? ''));
            Log::debug('Infos utilisateur', ['username' => $username, 'name' => $name]);

            // Recherche utilisateur
            $user = User::where('telegram_id', $telegramId)->first();
            Log::debug('Utilisateur trouvé par telegram_id', ['user' => $user ? $user->toArray() : null]);

            // Fallback par username
            if (!$user && $username) {
                $user = User::where('username', $username)->first();
                Log::debug('Utilisateur trouvé par username', ['user' => $user ? $user->toArray() : null]);
                if ($user) {
                    $user->telegram_id = $telegramId;
                    $user->save();
                    Log::debug('Mise à jour telegram_id effectuée');
                }
            }

            $isNewUser = false;
            if (!$user) {
                $userData = [
                    'telegram_id' => $telegramId,
                    'name' => $name,
                    'username' => $username,
                    'email' => $this->generateUniqueEmail($telegramId),
                    'password' => bcrypt(uniqid()),
                    'role' => 'client'
                ];

                $user = User::create($userData);
                $isNewUser = true;
                Log::debug('Nouvel utilisateur créé', ['user' => $user->toArray()]);
            }

            // Construction du message avec échappement Markdown
            $escapedName = $this->escapeMarkdown($name);
            $welcomeMessage = $isNewUser
                ? "👋 Bienvenue *{$escapedName}* dans notre boutique\\!\n\n"
                : "👋 Bon retour *{$escapedName}*\\!\n\n";

            $welcomeMessage .= "*Commandes disponibles* \\:\n"
                . "• /products \\- Voir nos produits\n"
                . ($user->role === 'commercant' 
                    ? "• /add\\_product \\- Ajouter un produit\n" 
                    : "• /register\\_commercant \\- Devenir commerçant\n")
                . "• /help \\- Aide";

            Log::debug('Message formaté avant envoi', ['message' => $welcomeMessage]);

            // Enregistrement en base
            Message::create([
                'user_id' => $user->id,
                'message' => $message['text'],
                'is_from_bot' => false
            ]);

            // Envoi avec gestion d'erreur
            $this->sendMessage($welcomeMessage, 'MarkdownV2');

        } catch (\Exception $e) {
            Log::error('Erreur critique dans handleStartCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_data' => $message
            ]);
            
            // Message d'erreur simple sans formatage
            $this->sendMessage("❌ Une erreur est survenue lors de votre inscription. Veuillez réessayer.");
        }
    }

    protected function escapeMarkdown(string $text): string
    {
        $charsToEscape = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($charsToEscape as $char) {
            $text = str_replace($char, '\\'.$char, $text);
        }
        return $text;
    }

    protected function generateUniqueEmail($telegramId): string
    {
        $baseEmail = "tg_{$telegramId}@telegram.example";
        $email = $baseEmail;
        $count = 1;

        while (User::where('email', $email)->exists()) {
            $email = "tg_{$telegramId}_{$count}@telegram.example";
            $count++;
        }

        return $email;
    }

    protected function registerAsCommercant(array $message)
    {
        try {
            $telegramId = $message['from']['id'];
            if (!$telegramId) {
                throw new \Exception('ID Telegram manquant');
            }

            // Recherche prioritaire par telegram_id
            $user = User::where('telegram_id', $telegramId)->first();

            // Si non trouvé, recherche alternative
            if (!$user) {
                $username = $message['from']['username'] ?? null;
                if ($username) {
                    $user = User::where('username', $username)->first();
                }
            }

            if (!$user) {
                $this->sendMessage(
                    "⚠️ Compte non trouvé. Veuillez d'abord :\n".
                    "1. Envoyer /start pour créer un compte\n".
                    "2. Puis réessayer /register_commercant",
                    'Markdown'
                );
                return;
            }

            // Mise à jour du telegram_id si nécessaire
            if (!$user->telegram_id) {
                $user->telegram_id = $telegramId;
            }

            $user->role = 'commercant';
            $user->save();

            $this->sendMessage(
                "✅ Félicitations *{$user->name}* !\n".
                "Vous êtes maintenant enregistré comme commerçant.\n\n".
                "Vous pouvez maintenant :\n".
                "- Ajouter des produits avec /add_product\n".
                "- Gérer votre boutique",
                'Markdown'
            );

        } catch (\Exception $e) {
            Log::error('Erreur registerAsCommercant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendMessage("❌ Une erreur technique est survenue. Veuillez réessayer.");
        }
    }

    protected function initProductSession(array $message)
    {
        $telegramId = $message['from']['id'];
        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user) {
            $this->sendMessage("❌ Vous devez d'abord créer un compte avec /start");
            return;
        }

        if ($user->role !== 'commercant') {
            $this->sendMessage(
                "🚫 Réservé aux commerçants.\n".
                "Pour devenir commerçant, envoyez /commercant",
                'Markdown'
            );
            return;
        }

        ProductSession::updateOrCreate(
            ['user_id' => $user->id],
            ['step' => 'name', 'data' => json_encode([])]
        );

        $this->sendMessage("📝 Entrez le *nom du produit* :", 'Markdown');
    }

    protected function sendHelpMessage()
    {
        $helpText = "ℹ️ *Aide* : Commandes disponibles\n\n".
            "/start - Créer un compte\n".
            "/products - Voir les produits\n".
            "/commercant - Devenir commerçant\n".
            "/add_product - Ajouter un produit (commerçants)\n".
            "/help - Afficher ce message";

        $this->sendMessage($helpText, 'Markdown');
    }

    protected function sendProductList()
    {
        $products = Product::latest()->take(5)->get();

        if ($products->isEmpty()) {
            $this->sendMessage("ℹ️ Aucun produit disponible pour le moment.");
            return;
        }

        $keyboard = $products->map(function ($product) {
            return [[
                'text' => "{$product->name} - {$product->price} FCFA",
                'callback_data' => "product_{$product->id}"
            ]];
        })->toArray();

        $this->sendMessage(
            "🛍️ *Nos produits* : Sélectionnez-en un",
            'Markdown',
            ['inline_keyboard' => $keyboard]
        );
    }

    protected function sendDefaultResponse()
    {
        $this->sendMessage(
            "❌ Commande non reconnue. Essayez /help pour voir les commandes disponibles.",
            'Markdown'
        );
    }

    protected function sendPhotoWithCaption($chatId, $photoUrl, $caption, $parseMode = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        Http::post("https://api.telegram.org/bot{$this->token}/sendPhoto", $payload);
    }

    protected function getTelegramFilePath($fileId)
    {
        $response = Http::get("https://api.telegram.org/bot{$this->token}/getFile", [
            'file_id' => $fileId
        ]);

        return $response->json('result.file_path');
    }

      protected function sendMessage(string $text, string $parseMode = null, array $replyMarkup = null)
    {
        try {
            Log::debug('Tentative d\'envoi de message', [
                'chat_id' => $this->chatId,
                'text_length' => strlen($text),
                'parse_mode' => $parseMode
            ]);

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

            $response = Http::timeout(10)
                ->retry(3, 100)
                ->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);

            Log::debug('Réponse API Telegram', [
                'status' => $response->status(),
                'response' => $response->json(),
                'payload' => $payload
            ]);

            if ($response->failed()) {
                Log::error('Échec envoi message Telegram', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'payload' => $payload
                ]);
                throw new \Exception("Échec de l'envoi: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Exception dans sendMessage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    


        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }
}