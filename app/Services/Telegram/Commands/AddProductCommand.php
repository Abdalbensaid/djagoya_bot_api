<?php

namespace App\Services\Telegram\Commands;

use App\Models\User;
use App\Models\ProductSession;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;
use Illuminate\Support\Facades\Log;

class AddProductCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(array $message)
    {
        try {
            $telegramId = $message['from']['id'];
            $user = User::where('telegram_id', $telegramId)->first();

            if (!$user) {
                $this->handler->sendMessage(
                    TelegramHelper::escapeMarkdownV2("❌ Vous devez d'abord créer un compte avec /start"),
                    'MarkdownV2'
                );
                return;
            }

            if ($user->role !== 'commercant') {
                $this->handler->sendMessage(
                    TelegramHelper::escapeMarkdownV2("🚫 Action réservée aux commerçants\\!"),
                    'MarkdownV2'
                );
                return;
            }

            ProductSession::updateOrCreate(
                ['user_id' => $user->id],
                ['step' => 'name', 'data' => json_encode([])]
            );

            $this->handler->sendMessage(
                TelegramHelper::escapeMarkdownV2("📝 Entrez le nom du produit \\:"),
                'MarkdownV2'
            );

        } catch (\Exception $e) {
            Log::error('Erreur initProductSession', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            
            $this->handler->sendMessage("❌ Erreur système. Veuillez réessayer plus tard.");
        }
    }
}