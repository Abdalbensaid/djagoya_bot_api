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

   // Dans la classe ProductsCommand
    public function execute()
    {
        $products = Product::with('user')->latest()->take(5)->get();

        if ($products->isEmpty()) {
            $this->handler->sendMessage("ℹ️ Aucun produit disponible pour le moment.");
            return;
        }

        // Envoyer un message d'introduction
        $this->handler->sendMessage(
            "🛍️ *Nos produits disponibles* :",
            'MarkdownV2'
        );

        foreach ($products as $product) {
            $caption = $this->formatProductCaption($product);
            
            if (is_array($product->image) && count($product->image) > 1) {
                // Envoyer le carrousel pour plusieurs images
                $this->sendMediaCarousel($product, $caption);
            } else {
                // Envoyer une seule image
                $imageUrl = is_array($product->image) ? $product->image[0] : $product->image;
                $this->handler->sendPhoto(
                    $imageUrl,
                    $caption,
                    'MarkdownV2',
                    $this->getProductButtons($product)
                );
            }
        }
    }

    protected function formatProductCaption(Product $product): string
    {
        return TelegramHelper::escapeMarkdownV2(
            "📌 *{$product->name}*\n" .
            "💰 *Prix : {$product->price} FCFA*\n" .
            "📝 *Description :* {$product->description}\n" .
            "👤 Vendeur : " . ($product->user ? $product->user->name : 'Anonyme')
        );
    }

protected function sendMediaCarousel(Product $product, string $caption)
{
    $mediaGroup = [];
    
    foreach ($product->image as $index => $imageUrl) {
        $media = [
            'type' => 'photo',
            'media' => $imageUrl
        ];
        
        if ($index === 0) {
            $media['caption'] = $caption;
            $media['parse_mode'] = 'MarkdownV2';
        }
        
        $mediaGroup[] = $media;
    }
    
    $this->handler->sendMediaGroup($mediaGroup);
    $this->handler->sendMessage(
        "Actions pour *{$product->name}* :",
        'MarkdownV2',
        $this->getProductButtons($product)
    );
}

protected function getProductButtons(Product $product): array
{
    return [
        'inline_keyboard' => [
            [
                ['text' => "🛒 Acheter", 'callback_data' => "buy_{$product->id}"],
                ['text' => "💖 Favoris", 'callback_data' => "fav_{$product->id}"],
                ['text' => "📝 Détails", 'callback_data' => "details_{$product->id}"]
            ]
        ]
    ];
}

    protected function sendSingleProductPhoto(Product $product)
    {
        $caption = TelegramHelper::escapeMarkdownV2(
            "📱 *{$product->name}*\n\n" .
            "{$product->description}\n\n" .
            "💰 *Prix : {$product->price} FCFA*"
        );

        $this->handler->sendPhoto(
            $product->image,
            $caption,
            'MarkdownV2',
            [
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => "👁️ Voir détails", 'callback_data' => "view_{$product->id}"],
                            ['text' => "🛒 Acheter", 'callback_data' => "buy_{$product->id}"]
                        ]
                    ]
                ]
            ]
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

            $caption = $this->formatProductDetails($product); // MarkdownV2 échappé

            $this->handler->sendPhoto(
                $product->image,
                $caption,
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
            "📱 *{$product->name}*\n\n" .
            "💰 Prix : *{$product->price} FCFA*\n" .
            "📦 Stock : {$product->stock}\n\n" .
            "📝 Description :\n" .
            "{$product->description}\n\n" .
            "[🔗 Voir sur le site](https://example.com/products/{$product->id})"
        );
    }

}