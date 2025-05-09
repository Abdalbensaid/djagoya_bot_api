<?php

namespace App\Services\Telegram\Helpers;

class TelegramHelper
{
    public static function escapeMarkdownV2(string $text): string
{
    $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

    foreach ($specialChars as $specialChar) {
        $text = str_replace($specialChar, '\\'.$specialChar, $text);
    }

    return $text;
}


    public static function formatWithEmoji(string $text, string $emoji): string
    {
        return $emoji . ' ' . self::escapeMarkdownV2($text);
    }
}
