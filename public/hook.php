<?php

require_once __DIR__ . '/../vendor/autoload.php';

use RubikaBot\Bot;
use RubikaBot\Filters\Filters;
use RubikaBot\Keyboard\Button;
use RubikaBot\Keyboard\Keypad;

$rawInput = file_get_contents('php://input');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    echo 'RubikaBot webhook endpoint is ready.';
    exit;
}

function getWebhookPayload(): array
{
    static $payload = null;
    if ($payload !== null) {
        return $payload;
    }

    $input = file_get_contents('php://input');
    $payload = json_decode($input, true) ?: [];
    return $payload;
}

function getMessageUniqueId(array $payload): ?string
{
    $update = $payload['update'] ?? null;
    if (!$update || !is_array($update)) {
        return null;
    }

    if (!empty($update['new_message']['message_id'])) {
        return 'message:' . $update['new_message']['message_id'];
    }

    if (!empty($payload['update_id'])) {
        return 'update:' . $payload['update_id'];
    }

    $sender = $update['new_message']['sender_id'] ?? '';
    $text = trim($update['new_message']['text'] ?? '');
    if ($sender !== '' && $text !== '') {
        return 'sender_text:' . $sender . ':' . md5($text);
    }

    return null;
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

$bot->onMessage(Filters::command('start'), function(Bot $bot, $message) use ($rawInput) {
    $payload = getWebhookPayload();
    $uniqueId = getMessageUniqueId($payload);

    if ($uniqueId !== null && isDuplicateMessage($uniqueId)) {
        error_log("Duplicate /start ignored: {$uniqueId} | raw input: {$rawInput}");
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
