<?php

namespace App\Services\Telegram\Commands;

use App\Models\Product;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;
use Illuminate\Support\Facades\Log;

class ProductsCommand
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute()
    {
        $products = Product::latest()->take(5)->get();

        if ($products->isEmpty()) {
            $this->handler->sendMessage("ℹ️ Aucun produit disponible pour le moment\\.");
            return;
        }

        $keyboard = $products->map(function ($product) {
            return [[
                'text' => "{$product->name} - {$product->price} FCFA",
                'callback_data' => "product_{$product->id}"
            ]];
        })->toArray();

        $this->handler->sendMessage(
            TelegramHelper::escapeMarkdownV2("🛍️ *Nos produits* \\: Sélectionnez\\-en un"),
            'MarkdownV2',
            ['inline_keyboard' => $keyboard]
        );
    }

    public function handleProductSelection($userId, $productId, $messageId)
    {
        try {
            $product = Product::find($productId);
            if (!$product) {
                throw new \Exception("Produit $productId introuvable");
            }

            $user = User::where('telegram_id', $userId)->first();
            if (!$user) {
                throw new \Exception("Utilisateur $userId introuvable");
            }

            $message = $this->formatProductDetails($product);

            $this->handler->sendMessage(
                $message,
                'MarkdownV2',
                [
                    'reply_to_message_id' => $messageId,
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => "🛒 Acheter maintenant", 'callback_data' => "buy_$productId"],
                                ['text' => "❤️ Ajouter aux favoris", 'callback_data' => "fav_$productId"]
                            ]
                        ]
                    ]
                ]
            );

        } catch (\Exception $e) {
            Log::error('Erreur handleProductSelection', [
                'error' => $e->getMessage(),
                'product_id' => $productId
            ]);
            $this->handler->sendPlainMessage($userId, "❌ Impossible d'afficher ce produit");
        }
    }


    protected function formatProductDetails(Product $product): string
    {
        return TelegramHelper::escapeMarkdownV2(
            "📱 *{$product->name}*\n\n".
            "💰 Prix : *{$product->price} FCFA*\n".
            "📦 Stock : {$product->stock}\n\n".
            "📝 Description :\n".
            "{$product->description}\n\n".
            "🛒 [Voir sur le site](https://example.com/products/{$product->id})"
        );
    }
}