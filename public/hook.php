<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\BotController;
use App\Database\Connection;
use RubikaBot\Bot;
use RubikaBot\Filters\Filters;
use RubikaBot\Message;

$config = require_once __DIR__ . '/../src/config.php';
$pdo = Connection::make($config['db_path']);

$bot = new Bot('BIHAAB0GVXFTQQXOFHXUCHKWZHRXQMHOVPTGCEMBGUPDSAJUPGISSIKUCEZOGOOX');
$controller = new BotController($bot, $pdo, $config);

$bot->onMessage(Filters::any(), function (Bot $bot, Message $message) use ($controller) {
    $controller->handle($message);
});

$bot->run();
