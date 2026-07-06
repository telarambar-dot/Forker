<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\RegistrationStateRepository;
use App\Repository\FileRepository;
use App\Repository\WorkflowStateRepository;
use App\Service\RegistrationService;
use App\Service\AdminService;
use App\Service\KeyboardService;
use RubikaBot\Bot;
use RubikaBot\Message;

class BotController
{
    private Bot $bot;
    private RegistrationService $registrationService;
    private AdminService $adminService;
    private array $config;

    public function __construct(Bot $bot, \PDO $pdo, array $config)
    {
        $this->bot = $bot;
        $userRepository = new UserRepository($pdo);
        $stateRepository = new RegistrationStateRepository($pdo);
        $fileRepository = new FileRepository($pdo);
        $workflowRepository = new WorkflowStateRepository($pdo);

        $this->registrationService = new RegistrationService($userRepository, $stateRepository, $fileRepository, $pdo);
        $this->adminService = new AdminService($userRepository, $workflowRepository, $fileRepository, $config['admin_ids'] ?? []);
        $this->config = $config;
    }

    public function handle(Message $message): void
    {
        $message->loadChatInfo($this->bot);

        if (!$message->sender_id) {
            return;
        }

        $user = $this->registrationService->ensureUser($message->sender_id, false);
        $buttonId = $message->button_id;
        $text = trim((string)$message->text);

        if ($this->adminService->isAdmin($user)) {
            $response = $this->adminService->handleAdminCommand($this->bot, $message, $user);
            if ($response !== null) {
                $this->sendResponse($message->chat_id, $response);
                return;
            }
        }

        if (!$user['is_verified']) {
            if ($buttonId === 'membership_auth' || $text === 'عضویت و احراز هویت') {
                $response = $this->registrationService->startRegistration($user['id'], $user);
                $this->sendResponse($message->chat_id, $response);
                return;
            }

            if ($user['registration_status'] === 'pending' && $user['verification_status'] === 'waiting_admin') {
                $this->sendResponse($message->chat_id, [
                    'text' => 'ثبت‌نام شما در حال بررسی است. لطفاً منتظر پاسخ مدیر باشید.',
                    'keyboard' => KeyboardService::unverifiedKeyboard(),
                ]);
                return;
            }

            $state = $this->registrationService->getState($user['id']);
            if ($state === null) {
                $this->sendResponse($message->chat_id, [
                    'text' => 'برای استفاده از ربات ابتدا باید ثبت‌نام و احراز هویت خود را تکمیل کنید.',
                    'keyboard' => KeyboardService::unverifiedKeyboard(),
                ]);
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
    }

    private function sendResponse(string $chatId, array $response): void
    {
        $this->bot->chat($chatId)->message($response['text']);
        if (isset($response['keyboard'])) {
            $this->bot->inlineKeypad($response['keyboard']);
        }
        $this->bot->send();

        if (!empty($response['files']) && is_array($response['files'])) {
            foreach ($response['files'] as $fileData) {
                $this->bot->chat($chatId)
                    ->file_id($fileData['file_id'])
                    ->file_type($fileData['type'])
                    ->caption($fileData['caption'] ?? '')
                    ->sendFile();
            }
        }
    }
}
