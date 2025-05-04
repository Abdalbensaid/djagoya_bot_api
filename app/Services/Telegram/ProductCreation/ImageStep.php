<?php

namespace App\Services\Telegram\ProductCreation;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Helpers\TelegramHelper;
use Illuminate\Support\Facades\Log;

class ImageStep
{
    protected $handler;

    public function __construct(MessageHandler $handler)
    {
        $this->handler = $handler;
    }

    public function execute(array $message, User $user, ProductSession $session, array $data)
    {
        if (!isset($message['photo'])) {
            $this->handler->sendPlainMessage("❌ Veuillez envoyer une photo valide du produit.");
            return;
        }

        try {
            $photo = end($message['photo']);
            $filePath = $this->handler->getTelegramFilePath($photo['file_id']);
            $imageUrl = "https://api.telegram.org/file/bot{$this->handler->getToken()}/{$filePath}";
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

            $successMessage = TelegramHelper::escapeMarkdownV2(
                "✅ *Produit ajouté avec succès*\\!\n\n".
                "📦 *Nom*\\: {$data['name']}\n".
                "💰 *Prix*\\: {$data['price']} FCFA\n".
                "📝 *Description*\\: {$data['description']}"
            );

            $this->handler->sendPhotoWithCaption(
                $this->handler->getChatId(),
                Storage::url($filename),
                $successMessage,
                'MarkdownV2'
            );

            $this->handler->sendPlainMessage("Pour ajouter un autre produit, tapez /add_product");

        } catch (\Exception $e) {
            Log::error('Erreur création produit', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            $this->handler->sendPlainMessage("❌ Erreur lors de l'ajout du produit. Veuillez réessayer.");
        }
    }
}