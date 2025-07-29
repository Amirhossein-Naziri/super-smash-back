<?php

namespace App\Traits;

trait TelegramMessageTrait
{
    /**
     * Create inline keyboard markup
     */
    protected function createInlineKeyboard(array $keyboard): string
    {
        return json_encode(['inline_keyboard' => $keyboard]);
    }

    /**
     * Send formatted message with keyboard
     */
    protected function sendMessage($chatId, string $text, array $keyboard = []): void
    {
        $replyMarkup = $keyboard ? $this->createInlineKeyboard($keyboard) : null;
        
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Send error message
     */
    protected function sendErrorMessage($chatId, string $message): void
    {
        $this->sendMessage($chatId, "❌ {$message}");
    }

    /**
     * Send success message
     */
    protected function sendSuccessMessage($chatId, string $message): void
    {
        $this->sendMessage($chatId, "✅ {$message}");
    }
} 