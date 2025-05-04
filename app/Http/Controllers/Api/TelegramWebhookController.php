<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

   public function handleWebhook(Request $request)
    {
        Log::debug('Payload Telegram reçu : ', $request->all());

        try {
            $this->validateTelegramWebhook($request);

            $this->telegramService->handleWebhook($request->all());

            return response()->json(['status' => 'success']);
            
        } catch (\Throwable $e) {
            Log::error('Erreur de traitement du webhook : ' . $e->getMessage(), [
                'exception' => $e,
                'payload' => $request->all()
            ]);
            
            return response()->json(
                ['status' => 'error', 'message' => 'Internal server error'],
                500
            );
        }
    }


    protected function validateTelegramWebhook(Request $request): void
    {
        if (!$request->has('update_id')) {
            throw new \InvalidArgumentException('Payload Telegram invalide');
        }
    }
}