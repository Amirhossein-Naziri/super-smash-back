<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Telegram\Bot\Api;

echo "Testing Telegram API...\n";

try {
    $telegram = new Api(config('telegram.bot_token'));
    
    // Test bot info
    $botInfo = $telegram->getMe();
    echo "Bot info: " . json_encode($botInfo, JSON_UNESCAPED_UNICODE) . "\n";
    
    // Test webhook info
    $webhookInfo = $telegram->getWebhookInfo();
    echo "Webhook info: " . json_encode($webhookInfo, JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "Telegram API connection successful!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} 