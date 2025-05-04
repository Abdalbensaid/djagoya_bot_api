<?php

namespace App\Services\Telegram\Commands;

use App\Models\User;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;
use Illuminate\Support\Facades\Log;

class RegisterCommercantCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(array $message)
    {
        Log::debug('Début registerAsCommercant', ['message' => $message]);
        
        try {
            $telegramId = $message['from']['id'];
            if (!$telegramId) {
                throw new \Exception('ID Telegram manquant');
            }

            $user = User::where('telegram_id', $telegramId)->first();

            if (!$user) {
                $username = $message['from']['username'] ?? null;
                if ($username) {
                    $user = User::where('username', $username)->first();
                }
            }

            if (!$user) {
                $responseText = TelegramHelper::escapeMarkdownV2("⚠️ Compte non trouvé. Veuillez d'abord :\n1. Envoyer /start pour créer un compte\n2. Puis réessayer /register_commercant");
                $this->handler->sendMessage($responseText, 'MarkdownV2');
                return;
            }

            $user->role = 'commercant';
            $user->save();

            $escapedName = TelegramHelper::escapeMarkdownV2($user->name);
            $successMessage = TelegramHelper::escapeMarkdownV2(
                "✅ Félicitations {$escapedName} !\n".
                "Vous êtes maintenant enregistré comme commerçant.\n\n".
                "Vous pouvez maintenant :\n".
                "- Ajouter des produits avec /add_product".
                "- Gérer votre boutique"
            );

            Log::debug('Envoi message succès commerçant');
            $this->handler->sendMessage($successMessage, 'MarkdownV2');

        } catch (\Exception $e) {
            Log::error('Erreur registerAsCommercant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $errorMessage = TelegramHelper::escapeMarkdownV2("❌ Une erreur technique est survenue. Veuillez réessayer.");
            $this->handler->sendMessage($errorMessage, 'MarkdownV2');
        }
    }
}