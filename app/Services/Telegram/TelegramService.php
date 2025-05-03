<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Handlers\MessageHandler;

class TelegramService
{
   public function handleWebhook(array $data)
{
    if (isset($data['message'])) {
        (new MessageHandler())->handle($data['message']);
    } elseif (isset($data['callback_query'])) {
        $this->handleCallback($data['callback_query']);
    }
}

protected function handleCallback(array $callback)
{
    $chatId = $callback['message']['chat']['id'];
    $data = $callback['data'];

    if (str_starts_with($data, 'product_')) {
        $productId = str_replace('product_', '', $data);
        $product = \App\Models\Product::find($productId);

        if ($product) {
            $text = "🛍️ *{$product->name}*\n💰 Prix : {$product->price} FCFA";
        } else {
            $text = "Produit introuvable.";
        }

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }
}

}
