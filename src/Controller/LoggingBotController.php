<?php

namespace App\Controller;

use App\Logger\Logger;
use App\Repository\UserRepository;
use App\Repository\RegistrationStateRepository;
use App\Repository\FileRepository;
use App\Repository\WorkflowStateRepository;
use App\Service\RegistrationService;
use App\Service\AdminService;
use App\Service\KeyboardService;
use RubikaBot\Bot;
use RubikaBot\Message;

class LoggingBotController
{
    private Bot $bot;
    private RegistrationService $registrationService;
    private AdminService $adminService;
    private Logger $logger;
    private array $config;

    public function __construct(Bot $bot, \PDO $pdo, array $config, Logger $logger)
    {
        $this->bot = $bot;
        $this->logger = $logger;
        $userRepository = new UserRepository($pdo);
        $stateRepository = new RegistrationStateRepository($pdo);
        $fileRepository = new FileRepository($pdo);
        $workflowRepository = new WorkflowStateRepository($pdo);

        $this->registrationService = new RegistrationService($userRepository, $stateRepository, $fileRepository, $pdo);
        $this->adminService = new AdminService($userRepository, $workflowRepository, $fileRepository, $config['admin_ids'] ?? []);
        $this->config = $config;
    }

    public function handle(Message $message, array $rawPayload): void
    {
        $this->logger->info('Incoming message', ['payload' => $rawPayload]);

        try {
            $message->loadChatInfo($this->bot);

            if (!$message->sender_id) {
                $this->logger->error('Missing sender_id in message payload', ['payload' => $rawPayload]);
                return;
            }

            $user = $this->registrationService->ensureUser($message->sender_id, false);
            $buttonId = $message->button_id;
            $text = trim((string)$message->text);

            $this->logger->info('Parsed message', [
                'sender_id' => $message->sender_id,
                'chat_id' => $message->chat_id,
                'button_id' => $buttonId,
                'text' => $text,
            ]);

            if ($this->adminService->isAdmin($user)) {
                $response = $this->adminService->handleAdminCommand($this->bot, $message, $user);
                if ($response !== null) {
                    $this->sendResponse($message->chat_id, $response);
                    return;
                }
            }

            if (!$user['is_verified']) {
                if ($buttonId === 'membership_auth' || $text === 'عضویت و احراز هویت') {
                    $response = $this->registrationService->startRegistration($user['id']);
                    $this->sendResponse($message->chat_id, $response);
                    return;
                }

                if ($user['registration_status'] === 'pending' && $user['verification_status'] === 'waiting_admin') {
                    $response = [
                        'text' => 'ثبت‌نام شما در حال بررسی است. لطفاً منتظر پاسخ مدیر باشید.',
                        'keyboard' => KeyboardService::unverifiedKeyboard(),
                    ];
                    $this->sendResponse($message->chat_id, $response);
                    return;
                }

                $state = $this->registrationService->getState($user['id']);
                if ($state === null) {
                    $response = [
                        'text' => 'برای استفاده از ربات ابتدا باید ثبت‌نام و احراز هویت خود را تکمیل کنید.',
                        'keyboard' => KeyboardService::unverifiedKeyboard(),
                    ];
                    $this->sendResponse($message->chat_id, $response);
                    return;
                }

                $response = $this->registrationService->handleMessage($user['id'], $user, $state, $message);
                $this->sendResponse($message->chat_id, $response);
                return;
            }

            if ($text === '/start' || $text === '/help') {
                $this->sendResponse($message->chat_id, [
                    'text' => 'شما احراز هویت شده‌اید. اکنون می‌توانید از امکانات ربات استفاده کنید.',
                ]);
                return;
            }

            $this->sendResponse($message->chat_id, [
                'text' => 'شما در حالت احراز هویت شده هستید. برای شروع از دستور /start استفاده کنید.',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Unhandled exception in controller', [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $this->bot->chat($message->chat_id)->message('خطای داخلی رخ داد. لطفاً مجدداً تلاش کنید.')->send();
        }
    }

    private function sendResponse(string $chatId, array $response): void
    {
        try {
            $this->logger->info('Sending response', ['chat_id' => $chatId, 'response' => $response]);
            $this->bot->chat($chatId)->message($response['text']);
            if (isset($response['keyboard'])) {
                $this->bot->inlineKeypad($response['keyboard']);
            }
            $sendResult = $this->bot->send();
            $this->logger->info('Response sent', ['send_result' => $sendResult]);

            if (!empty($response['files']) && is_array($response['files'])) {
                foreach ($response['files'] as $fileData) {
                    $sendFileResult = $this->bot->chat($chatId)
                        ->file_id($fileData['file_id'])
                        ->file_type($fileData['type'])
                        ->caption($fileData['caption'] ?? '')
                        ->sendFile();
                    $this->logger->info('File sent', ['file' => $fileData, 'result' => $sendFileResult]);
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Error sending response', [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'response' => $response,
            ]);
        }
    }
}
