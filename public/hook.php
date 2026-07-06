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

$bot = new Bot('BIHAAB0GVXFTQQXOFHXUCHKWZHRXQMHOVPTGCEMBGUPDSAJUPGISSIKUCEZOGOOX');

$bot->onMessage(Filters::command('start'), function(Bot $bot, $message) {
    $keypad = Keypad::make();
    $keypad->row()->add(Button::simple('help', 'راهنما'));

    $bot->chat($message->chat_id)
        ->message('ربات با موفقیت فعال شد!\nبرای راهنمایی /help را بفرستید.')
        ->inlineKeypad($keypad->toArray())
        ->send();
});

$bot->run();
