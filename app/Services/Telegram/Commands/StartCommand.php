<?php

namespace App\Services\Telegram\Commands;

use App\Models\User;
use App\Models\Message;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;
use Illuminate\Support\Facades\Log;

class StartCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(array $message)
    {
        Log::debug('Début handleStartCommand', ['message' => $message]);
        
        try {
            $telegramId = $message['from']['id'];
            Log::debug('ID Telegram', ['id' => $telegramId]);
            
            $username = $message['from']['username'] ?? null;
            $name = trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? ''));
            Log::debug('Infos utilisateur', ['username' => $username, 'name' => $name]);

            $user = User::where('telegram_id', $telegramId)->first();
            Log::debug('Utilisateur trouvé par telegram_id', ['user' => $user ? $user->toArray() : null]);

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

            $escapedName = TelegramHelper::escapeMarkdownV2($name);
            $welcomeMessage = $isNewUser
                ? "👋 Bienvenue *{$escapedName}* dans notre boutique\\!\n\n"
                : "👋 Bon retour *{$escapedName}*\\!\n\n";

            $welcomeMessage .= "*Commandes disponibles* \\:\n"
                . "• /products - Voir nos produits\n"
                . ($user->role === 'commercant' 
                    ? "• /add_product - Ajouter un produit\n" 
                    : "• /register_commercant - Devenir commerçant\n")
                . "• /help - Aide";

            Log::debug('Message formaté avant envoi', ['message' => $welcomeMessage]);

            Message::create([
                'user_id' => $user->id,
                'message' => $message['text'],
                'is_from_bot' => false
            ]);

            $this->handler->sendMessage($welcomeMessage, 'MarkdownV2');

        } catch (\Exception $e) {
            Log::error('Erreur critique dans handleStartCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_data' => $message
            ]);
            
            $this->handler->sendMessage("❌ Une erreur est survenue lors de votre inscription. Veuillez réessayer.");
        }
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
}