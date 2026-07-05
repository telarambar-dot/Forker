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

$bot->run();
