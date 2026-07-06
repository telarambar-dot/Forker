<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\WorkflowStateRepository;
use App\Repository\FileRepository;
use RubikaBot\Bot;
use RubikaBot\Message;

class AdminService
{
    private UserRepository $userRepository;
    private WorkflowStateRepository $workflowStateRepository;
    private FileRepository $fileRepository;
    private array $adminIds;

    public function __construct(
        UserRepository $userRepository,
        WorkflowStateRepository $workflowStateRepository,
        FileRepository $fileRepository,
        array $adminIds
    ) {
        $this->userRepository = $userRepository;
        $this->workflowStateRepository = $workflowStateRepository;
        $this->fileRepository = $fileRepository;
        $this->adminIds = $adminIds;
    }

    public function isAdmin(array $user): bool
    {
        return in_array($user['external_id'], $this->adminIds, true) || (bool)($user['is_admin'] ?? false);
    }

    public function handleAdminCommand(Bot $bot, Message $message, array $user): ?array
    {
        if (!$this->isAdmin($user)) {
            return null;
        }

        $text = trim((string)$message->text);
        $buttonId = $message->button_id;

        if ($text === '/admin' || $buttonId === 'admin_list_requests') {
            return $this->listPendingRequests($bot, $message);
        }

        if ($buttonId && str_starts_with($buttonId, 'admin_')) {
            return $this->handleAdminButton($bot, $message, $buttonId);
        }

        $workflow = $this->workflowStateRepository->findByActorId($user['external_id']);
        if ($workflow && $workflow['type'] === 'reject_reason') {
            return $this->processRejectReason($bot, $message, $workflow, $user);
        }

        return null;
    }

    private function listPendingRequests(Bot $bot, Message $message): array
    {
        $pending = $this->userRepository->getPendingApplications();

        if (empty($pending)) {
            return [
                'text' => 'در حال حاضر درخواست ثبت‌نامی برای بررسی وجود ندارد.',
                'keyboard' => KeyboardService::adminMenuKeyboard(),
            ];
        }

        $rows = [];
        foreach ($pending as $user) {
            $rows[] = [
                ['id' => 'admin_view_' . $user['id'], 'text' => trim(($user['first_name'] ?: '') . ' ' . ($user['last_name'] ?: '')) ?: 'شناسه ' . $user['id']],
            ];
        }

        return [
            'text' => 'لیست درخواست‌های منتظر بررسی:',
            'keyboard' => KeyboardService::makeKeyboard($rows),
        ];
    }

    private function handleAdminButton(Bot $bot, Message $message, string $buttonId): ?array
    {
        if (preg_match('/^admin_view_(\d+)$/', $buttonId, $matches)) {
            return $this->viewRequest($bot, (int)$matches[1]);
        }

        if (preg_match('/^admin_approve_(\d+)$/', $buttonId, $matches)) {
            return $this->approveRequest($bot, (int)$matches[1], $message);
        }

        if (preg_match('/^admin_reject_(\d+)$/', $buttonId, $matches)) {
            return $this->requestRejectionReason((int)$matches[1], $message);
        }

        if ($buttonId === 'admin_cancel') {
            return [
                'text' => 'بازگشت به منوی اصلی مدیریت.',
                'keyboard' => KeyboardService::adminMenuKeyboard(),
            ];
        }

        return null;
    }

    private function viewRequest(Bot $bot, int $targetUserId): array
    {
        $user = $this->userRepository->findById($targetUserId);
        if (!$user) {
            return [
                'text' => 'درخواست مورد نظر پیدا نشد.',
                'keyboard' => KeyboardService::adminMenuKeyboard(),
            ];
        }

        $text = implode("\n", [
            'اطلاعات کامل کاربر:',
            'نام: ' . ($user['first_name'] ?: '-'),
            'نام خانوادگی: ' . ($user['last_name'] ?: '-'),
            'کد ملی: ' . ($user['national_code'] ?: '-'),
            'کد پستی: ' . ($user['postal_code'] ?: '-'),
            'آدرس: ' . ($user['address'] ?: '-'),
            'معرف: ' . ($user['referee_name'] ?: '-'),
            'شماره معرف: ' . ($user['referee_phone'] ?: '-'),
            'وضعیت ثبت‌نام: ' . ($user['registration_status'] ?: '-'),
            'وضعیت احراز: ' . ($user['verification_status'] ?: '-'),
            'علت رد: ' . ($user['rejected_reason'] ?: '-'),
        ]);

        $fileResponse = [];
        $fileTypes = [
            'selfie' => 'سلفی',
            'id_card_front' => 'روی کارت ملی',
            'id_card_back' => 'پشت کارت ملی',
            'employment_document' => 'مدرک شغلی',
        ];

        foreach ($fileTypes as $type => $label) {
            $file = $this->fileRepository->findByUserIdAndType($targetUserId, $type);
            if ($file) {
                $fileResponse[] = [
                    'file_id' => $file['file_id'],
                    'type' => match ($type) {
                        'selfie', 'id_card_front', 'id_card_back' => 'Image',
                        'employment_document' => 'File',
                        default => 'File',
                    },
                    'caption' => $label,
                ];
            }
        }

        return [
            'text' => $text,
            'keyboard' => KeyboardService::adminRequestActionKeyboard($targetUserId),
            'files' => $fileResponse,
        ];
    }

    private function approveRequest(Bot $bot, int $targetUserId, Message $message): array
    {
        $targetUser = $this->userRepository->findById($targetUserId);
        if (!$targetUser) {
            return [
                'text' => 'درخواست مورد نظر پیدا نشد.',
                'keyboard' => KeyboardService::adminMenuKeyboard(),
            ];
        }

        $this->userRepository->update($targetUserId, [
            'verification_status' => 'verified',
            'registration_status' => 'completed',
            'is_verified' => 1,
            'rejected_reason' => null,
        ]);

        $bot->chat($targetUser['external_id'])
            ->message('احراز هویت شما تایید شد.\nاکنون می‌توانید از تمام امکانات ربات استفاده کنید.')
            ->chatKeypad(KeyboardService::unverifiedKeyboard())
            ->send();

        return [
            'text' => 'درخواست با موفقیت تایید شد و پیام تایید برای کاربر ارسال شد.',
            'keyboard' => KeyboardService::adminMenuKeyboard(),
        ];
    }

    private function requestRejectionReason(int $targetUserId, Message $message): array
    {
        $this->workflowStateRepository->create(
            $message->sender_id,
            'reject_reason',
            'awaiting_rejection_reason',
            json_encode(['target_user_id' => $targetUserId]),
            null
        );

        return [
            'text' => 'لطفاً دلیل رد درخواست را وارد کنید.',
        ];
    }

    private function processRejectReason(Bot $bot, Message $message, array $workflow, array $admin): array
    {
        $reason = trim((string)$message->text);
        if ($reason === '') {
            return [
                'text' => 'دلیل رد نمی‌تواند خالی باشد. لطفاً متن دلیل را ارسال کنید.',
            ];
        }

        $context = json_decode($workflow['context'] ?? '{}', true);
        $targetUserId = isset($context['target_user_id']) ? (int)$context['target_user_id'] : null;
        if (!$targetUserId) {
            $this->workflowStateRepository->deleteByActorId($admin['external_id']);
            return [
                'text' => 'خطا در دریافت شناسه کاربر. عملیات رد متوقف شد.',
                'keyboard' => KeyboardService::adminMenuKeyboard(),
            ];
        }

        $targetUser = $this->userRepository->findById($targetUserId);
        if (!$targetUser) {
            $this->workflowStateRepository->deleteByActorId($admin['external_id']);
            return [
                'text' => 'کاربر مورد نظر پیدا نشد.',
                'keyboard' => KeyboardService::adminMenuKeyboard(),
            ];
        }

        $this->userRepository->update($targetUserId, [
            'verification_status' => 'rejected',
            'registration_status' => 'draft',
            'is_verified' => 0,
            'rejected_reason' => $reason,
        ]);

        $this->workflowStateRepository->deleteByActorId($admin['external_id']);

        $bot->chat($targetUser['external_id'])
            ->message('درخواست شما رد شد.\nعلت: ' . $reason . '\n\nمی‌توانید مجدداً مراحل را تکمیل کنید.')
            ->chatKeypad(KeyboardService::unverifiedKeyboard())
            ->send();

        return [
            'text' => 'رد درخواست ثبت شد و پیام به کاربر ارسال شد.',
            'keyboard' => KeyboardService::adminMenuKeyboard(),
        ];
    }
}
