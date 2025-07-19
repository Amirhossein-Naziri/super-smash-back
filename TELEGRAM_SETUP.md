# Telegram Bot Setup Guide

## 1. Create a Telegram Bot

1. Open Telegram and search for `@BotFather`
2. Send `/newbot` command
3. Follow the instructions to create your bot
4. Save the bot token that BotFather gives you

## 2. Configure Environment Variables

Add the following to your `.env` file:

```env
TELEGRAM_BOT_TOKEN=8140283298:AAEiANouwoVgV2WKOIqsXEp-nQyV5ARUrlw
TELEGRAM_WEBHOOK_URL=https://api.daom.ir/api/telegram/webhook
APP_URL=https://api.daom.ir
```

## 3. Set Webhook

After setting up your bot and configuring the environment variables, visit:

```
https://api.daom.ir/api/telegram/set-webhook
```

This will set up the webhook for your bot.

## 4. Test the Bot

1. Open your bot in Telegram
2. Send `/start` command
3. The bot should respond with a welcome message and a button to start the game

## 5. Available Endpoints

- `POST /api/telegram/webhook` - Webhook endpoint for Telegram updates
- `GET /api/telegram/set-webhook` - Set webhook URL
- `GET /api/telegram/bot-info` - Get bot information

## 6. Features

- **Welcome Message**: When users send `/start`, the bot sends a Persian welcome message
- **Inline Keyboard**: Includes a button to open the game web app
- **Web App Integration**: The button opens your game at `https://daom.ir/game`

## 7. Domain Configuration

- **Backend API**: `https://api.daom.ir`
- **Frontend**: `https://daom.ir`
- **Game URL**: `https://daom.ir/game`
- **Bot Test**: `https://daom.ir/bot-test`

## 8. Customization

You can modify the welcome message in `app/Http/Controllers/TelegramBotController.php` in the `handleStartCommand` method. 