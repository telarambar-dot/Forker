<?php

require_once __DIR__ . '/../vendor/autoload.php';

use RubikaBot\Bot;
use RubikaBot\Filters\Filters;

$botToken = 'BIHAAB0GVXFTQQXOFHXUCHKWZHRXQMHOVPTGCEMBGUPDSAJUPGISSIKUCEZOGOOX';
$adminChatId = 'b0BsqLd0pPb05eb9ecffb526a9d97e28';

$bot = new Bot($botToken);

$bot->onMessage(Filters::command('start'), function (Bot $bot, $message): void {
    $chatId = $message->chat_id;

    $welcomeText = "سلام 👋\nبه ربات خوش آمدی.\nاین ربات با کتابخانه RubikaBot ساخته شده است.";

    $bot->chat($chatId)
        ->message($welcomeText)
        ->send();
});

// Run bot only for POST requests (webhook) or from CLI. When opened in a browser (GET)
// we should not start the long-polling loop which blocks the request.
if (php_sapi_name() === 'cli' || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    $bot->run();
} else {
    http_response_code(200);
    echo "RubikaBot webhook endpoint. Send POST requests or run via CLI.\n";
}
