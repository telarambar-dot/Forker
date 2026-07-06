<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\LoggingBotController;
use App\Database\Connection;
use App\Logger\Logger;
use RubikaBot\Bot;
use RubikaBot\Filters\Filters;
use RubikaBot\Message;

$config = require_once __DIR__ . '/../src/config.php';
$pdo = Connection::make($config['db_path']);
$logger = new Logger($config['log_path']);

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true) ?: [];
$logger->info('Webhook received', ['raw_payload' => $payload, 'method' => $_SERVER['REQUEST_METHOD'] ?? null]);

$bot = new Bot('BIHAAB0GVXFTQQXOFHXUCHKWZHRXQMHOVPTGCEMBGUPDSAJUPGISSIKUCEZOGOOX');
$controller = new LoggingBotController($bot, $pdo, $config, $logger);

$bot->onMessage(Filters::any(), function (Bot $bot, Message $message) use ($controller, $payload, $logger) {
    try {
        $controller->handle($message, $payload);
    } catch (\Throwable $exception) {
        $logger->error('Unhandled exception in webhook callback', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
});

$bot->run();
