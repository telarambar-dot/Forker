<?php

require_once __DIR__ . '/../vendor/autoload.php';

use RubikaBot\Bot;
use RubikaBot\Filters\Filters;
use RubikaBot\Keyboard\Button;
use RubikaBot\Keyboard\Keypad;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    echo 'RubikaBot webhook endpoint is ready.';
    exit;
}

function isDuplicateMessage(string $messageId): bool
{
    $cacheFile = __DIR__ . '/processed_message_ids.json';
    $processed = [];

    if (file_exists($cacheFile)) {
        $processed = json_decode(file_get_contents($cacheFile), true) ?: [];
    }

    $now = time();
    foreach ($processed as $id => $timestamp) {
        if ($timestamp < $now - 300) {
            unset($processed[$id]);
        }
    }

    if (isset($processed[$messageId])) {
        return true;
    }

    $processed[$messageId] = $now;
    file_put_contents($cacheFile, json_encode($processed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return false;
}

$bot = new Bot('BIHAAB0GVXFTQQXOFHXUCHKWZHRXQMHOVPTGCEMBGUPDSAJUPGISSIKUCEZOGOOX');

$bot->onMessage(Filters::command('start'), function(Bot $bot, $message) {
    if ($message->message_id !== null && isDuplicateMessage($message->message_id)) {
        error_log("Duplicate /start ignored: {$message->message_id}");
        return;
    }

    $keypad = Keypad::make();
    $keypad->row()->add(Button::simple('help', 'راهنما'));

    $bot->chat($message->chat_id)
        ->message('ربات با موفقیت فعال شد!\nبرای راهنمایی /help را بفرستید.')
        ->inlineKeypad($keypad->toArray())
        ->send();
});

$bot->run();
