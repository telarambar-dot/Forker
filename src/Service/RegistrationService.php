<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\RegistrationStateRepository;
use App\Repository\FileRepository;
use RubikaBot\Message;
use RubikaBot\Bot;

class RegistrationService
{
    private UserRepository $userRepository;
    private RegistrationStateRepository $stateRepository;
    private FileRepository $fileRepository;
    private \PDO $pdo;

    private const STEP_ENTER_FIRST_NAME = 'enter_first_name';
    private const STEP_CONFIRM_FIRST_NAME = 'confirm_first_name';
    private const STEP_ENTER_LAST_NAME = 'enter_last_name';
    private const STEP_CONFIRM_LAST_NAME = 'confirm_last_name';
    private const STEP_ENTER_NATIONAL_CODE = 'enter_national_code';
    private const STEP_CONFIRM_NATIONAL_CODE = 'confirm_national_code';
    private const STEP_ENTER_POSTAL_CODE = 'enter_postal_code';
    private const STEP_CONFIRM_POSTAL_CODE = 'confirm_postal_code';
    private const STEP_ENTER_ADDRESS = 'enter_address';
    private const STEP_CONFIRM_ADDRESS = 'confirm_address';
    private const STEP_UPLOAD_SELFIE = 'upload_selfie';
    private const STEP_CONFIRM_SELFIE = 'confirm_selfie';
    private const STEP_UPLOAD_ID_CARD_FRONT = 'upload_id_card_front';
    private const STEP_CONFIRM_ID_CARD_FRONT = 'confirm_id_card_front';
    private const STEP_UPLOAD_ID_CARD_BACK = 'upload_id_card_back';
    private const STEP_CONFIRM_ID_CARD_BACK = 'confirm_id_card_back';
    private const STEP_ENTER_REFEREE_NAME = 'enter_referee_name';
    private const STEP_CONFIRM_REFEREE_NAME = 'confirm_referee_name';
    private const STEP_ENTER_REFEREE_PHONE = 'enter_referee_phone';
    private const STEP_CONFIRM_REFEREE_PHONE = 'confirm_referee_phone';
    private const STEP_UPLOAD_EMPLOYMENT_DOCUMENT = 'upload_employment_document';
    private const STEP_CONFIRM_EMPLOYMENT_DOCUMENT = 'confirm_employment_document';
    private const STEP_REVIEW = 'review';

    private const STEPS = [
        self::STEP_ENTER_FIRST_NAME,
        self::STEP_CONFIRM_FIRST_NAME,
        self::STEP_ENTER_LAST_NAME,
        self::STEP_CONFIRM_LAST_NAME,
        self::STEP_ENTER_NATIONAL_CODE,
        self::STEP_CONFIRM_NATIONAL_CODE,
        self::STEP_ENTER_POSTAL_CODE,
        self::STEP_CONFIRM_POSTAL_CODE,
        self::STEP_ENTER_ADDRESS,
        self::STEP_CONFIRM_ADDRESS,
        self::STEP_UPLOAD_SELFIE,
        self::STEP_CONFIRM_SELFIE,
        self::STEP_UPLOAD_ID_CARD_FRONT,
        self::STEP_CONFIRM_ID_CARD_FRONT,
        self::STEP_UPLOAD_ID_CARD_BACK,
        self::STEP_CONFIRM_ID_CARD_BACK,
        self::STEP_ENTER_REFEREE_NAME,
        self::STEP_CONFIRM_REFEREE_NAME,
        self::STEP_ENTER_REFEREE_PHONE,
        self::STEP_CONFIRM_REFEREE_PHONE,
        self::STEP_UPLOAD_EMPLOYMENT_DOCUMENT,
        self::STEP_CONFIRM_EMPLOYMENT_DOCUMENT,
        self::STEP_REVIEW,
    ];

    private const PROMPTS = [
        self::STEP_ENTER_FIRST_NAME => 'نام خود را وارد کنید.',
        self::STEP_CONFIRM_FIRST_NAME => 'نام دریافت شده: %s\nآیا مورد تایید است؟',
        self::STEP_ENTER_LAST_NAME => 'نام خانوادگی خود را وارد کنید.',
        self::STEP_CONFIRM_LAST_NAME => 'نام خانوادگی دریافت شده: %s\nآیا مورد تایید است؟',
        self::STEP_ENTER_NATIONAL_CODE => 'کد ملی خود را وارد کنید.',
        self::STEP_CONFIRM_NATIONAL_CODE => 'کد ملی دریافت شده: %s\nآیا مورد تایید است؟',
        self::STEP_ENTER_POSTAL_CODE => 'کد پستی خود را وارد کنید.',
        self::STEP_CONFIRM_POSTAL_CODE => 'کد پستی دریافت شده: %s\nآیا مورد تایید است؟',
        self::STEP_ENTER_ADDRESS => 'آدرس منزل خود را وارد کنید.',
        self::STEP_CONFIRM_ADDRESS => 'آدرس دریافت شده:\n%s\nآیا مورد تایید است؟',
        self::STEP_UPLOAD_SELFIE => 'لطفاً یک عکس سلفی ارسال کنید.',
        self::STEP_CONFIRM_SELFIE => 'سلفی دریافت شد. آیا مورد تایید است؟',
        self::STEP_UPLOAD_ID_CARD_FRONT => 'لطفاً عکس روی کارت ملی را ارسال کنید.',
        self::STEP_CONFIRM_ID_CARD_FRONT => 'عکس روی کارت ملی دریافت شد. آیا مورد تایید است؟',
        self::STEP_UPLOAD_ID_CARD_BACK => 'لطفاً عکس پشت کارت ملی را ارسال کنید.',
        self::STEP_CONFIRM_ID_CARD_BACK => 'عکس پشت کارت ملی دریافت شد. آیا مورد تایید است؟',
        self::STEP_ENTER_REFEREE_NAME => 'نام معرف خود را وارد کنید.',
        self::STEP_CONFIRM_REFEREE_NAME => 'نام معرف دریافت شده: %s\nآیا مورد تایید است؟',
        self::STEP_ENTER_REFEREE_PHONE => 'شماره موبایل معرف خود را وارد کنید.',
        self::STEP_CONFIRM_REFEREE_PHONE => 'شماره موبایل دریافت شده: %s\nآیا مورد تایید است؟',
        self::STEP_UPLOAD_EMPLOYMENT_DOCUMENT => 'لطفاً مدرک شغلی خود را ارسال کنید (تصویر یا PDF).',
        self::STEP_CONFIRM_EMPLOYMENT_DOCUMENT => 'مدرک شغلی دریافت شد. آیا مورد تایید است؟',
    ];

    public function __construct(
        UserRepository $userRepository,
        RegistrationStateRepository $stateRepository,
        FileRepository $fileRepository,
        \PDO $pdo
    ) {
        $this->userRepository = $userRepository;
        $this->stateRepository = $stateRepository;
        $this->fileRepository = $fileRepository;
        $this->pdo = $pdo;
    }

    public function ensureUser(string $externalId, bool $isAdmin = false): array
    {
        $user = $this->userRepository->findByExternalId($externalId);
        if ($user !== null) {
            return $user;
        }

        return $this->userRepository->create($externalId, $isAdmin);
    }

    public function startRegistration(int $userId, array $user = []): array
    {
        $this->pdo->beginTransaction();
        try {
            $this->stateRepository->create($userId, self::STEP_ENTER_FIRST_NAME, null, null, null);
            $this->userRepository->update($userId, [
                'registration_status' => 'draft',
                'verification_status' => 'draft',
                'is_verified' => 0,
                'rejected_reason' => null,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        // Get fresh user data
        $userData = $this->userRepository->findById($userId);
        
        // Build welcome message with user's name
        $firstName = trim((string)($userData['first_name'] ?? ''));
        $lastName = trim((string)($userData['last_name'] ?? ''));
        
        $greeting = 'کاربر گرامی';
        if ($firstName && $lastName) {
            $greeting = "کاربر گرامی {$firstName} {$lastName}";
        } elseif ($firstName) {
            $greeting = "کاربر گرامی {$firstName}";
        }
        
        return [
            'text' => "{$greeting}\n\nجهت ادامه و استفاده از خدمات، ابتدا باید فرآیند احراز هویت را تکمیل کنید.\n\nلطفاً اطلاعات خود را دقیق وارد کنید.\n\n" . self::PROMPTS[self::STEP_ENTER_FIRST_NAME],
        ];
    }

    public function getState(int $userId): ?array
    {
        return $this->stateRepository->findByUserId($userId);
    }

    public function handleMessage(int $userId, array $user, array $state, Message $message): array
    {
        $buttonId = $message->button_id;
        if ($buttonId !== null) {
            return $this->handleButtonAction($userId, $user, $state, $buttonId);
        }

        if ($state['step'] === self::STEP_REVIEW) {
            return $this->buildReviewResponse($user, $state);
        }

        if ($this->isConfirmStep($state['step'])) {
            return [
                'text' => sprintf(self::PROMPTS[$state['step']], $this->getPendingLabel($state['step'], $state)),
                'keyboard' => KeyboardService::confirmKeyboard(),
            ];
        }

        return $this->handleStepInput($userId, $state, $message);
    }

    private function handleButtonAction(int $userId, array $user, array $state, string $buttonId): array
    {
        if ($buttonId === 'confirm_register') {
            return $this->confirmPendingStep($userId, $state);
        }

        if ($buttonId === 'confirm_edit') {
            return $this->editPendingStep($userId, $state);
        }

        if ($buttonId === 'review_register_final') {
            return $this->finalizeRegistration($userId);
        }

        if ($buttonId === 'review_edit_info') {
            return $this->resumeFromFirstIncomplete($userId, $user);
        }

        return [
            'text' => 'دکمه نامشخص است. لطفاً از دکمه‌های موجود استفاده کنید.',
            'keyboard' => KeyboardService::unverifiedKeyboard(),
        ];
    }

    private function buildResponse(string $step, ?array $state = null): array
    {
        $text = self::PROMPTS[$step] ?? 'لطفاً ادامه دهید.';
        if ($this->isConfirmStep($step) && $state) {
            $pendingLabel = $this->getPendingLabel($step, $state);
            if ($pendingLabel !== null) {
                $text = sprintf($text, $pendingLabel);
            }
        }

        $keyboard = match (true) {
            $this->isConfirmStep($step) => KeyboardService::confirmKeyboard(),
            $step === self::STEP_REVIEW => KeyboardService::reviewKeyboard(),
            default => null,
        };

        $response = ['text' => $text];
        if ($keyboard !== null) {
            $response['keyboard'] = $keyboard;
        }

        return $response;
    }

    private function handleStepInput(int $userId, array $state, Message $message): array
    {
        $step = $state['step'];
        $value = null;
        $fileId = null;
        $fileName = null;

        if ($this->isUploadStep($step)) {
            if (!$message->file_id) {
                return [
                    'text' => 'لطفاً یک فایل معتبر ارسال کنید.',
                    'keyboard' => KeyboardService::unverifiedKeyboard(),
                ];
            }
            $fileId = $message->file_id;
            $fileName = $message->file_name;
        } else {
            $value = trim((string)$message->text);
            if ($value === '') {
                return [
                    'text' => 'لطفاً مقدار مورد نظر را به صورت متن ارسال کنید.',
                    'keyboard' => KeyboardService::unverifiedKeyboard(),
                ];
            }
        }

        $validation = $this->validateStepInput($step, $value, $fileName);
        if ($validation !== true) {
            return [
                'text' => $validation,
                'keyboard' => KeyboardService::unverifiedKeyboard(),
            ];
        }

        $nextConfirmStep = $this->getConfirmStep($step);
        $this->stateRepository->update($userId, [
            'step' => $nextConfirmStep,
            'pending_text' => $value,
            'pending_file_id' => $fileId,
            'pending_file_name' => $fileName,
            'context' => null,
        ]);

        return $this->buildResponse($nextConfirmStep, $this->stateRepository->findByUserId($userId));
    }

    private function confirmPendingStep(int $userId, array $state): array
    {
        $this->pdo->beginTransaction();
        try {
            $this->commitStep($userId, $state);
            $nextStep = $this->getNextStep($state['step']);
            $this->stateRepository->update($userId, [
                'step' => $nextStep,
                'pending_text' => null,
                'pending_file_id' => null,
                'pending_file_name' => null,
                'context' => null,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->buildResponse($nextStep, $this->stateRepository->findByUserId($userId));
    }

    private function editPendingStep(int $userId, array $state): array
    {
        $previousStep = $this->getPreviousEnterStep($state['step']);
        $this->stateRepository->update($userId, [
            'step' => $previousStep,
            'pending_text' => null,
            'pending_file_id' => null,
            'pending_file_name' => null,
            'context' => null,
        ]);

        return $this->buildResponse($previousStep);
    }

    private function finalizeRegistration(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        
        $firstName = trim((string)($user['first_name'] ?? ''));
        $lastName = trim((string)($user['last_name'] ?? ''));
        
        $greeting = 'کاربر گرامی';
        if ($firstName && $lastName) {
            $greeting = "کاربر گرامی {$firstName} {$lastName}";
        } elseif ($firstName) {
            $greeting = "کاربر گرامی {$firstName}";
        }
        
        $this->pdo->beginTransaction();
        try {
            $this->userRepository->update($userId, [
                'registration_status' => 'pending',
                'verification_status' => 'waiting_admin',
                'is_verified' => 0,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'text' => "{$greeting}\n\nثبت‌نام شما با موفقیت انجام شد.\nاطلاعات شما برای بررسی و تایید به مدیران ارسال گردید.\n\nتا زمان تایید مدیر، امکان استفاده از خدمات ربات را ندارید.\n\nمنتظر بمانید...",
            'keyboard' => KeyboardService::unverifiedKeyboard(),
        ];
    }

    private function resumeFromFirstIncomplete(int $userId, array $user): array
    {
        $nextStep = $this->findFirstIncompleteStep($userId, $user);
        $this->stateRepository->update($userId, [
            'step' => $nextStep,
            'pending_text' => null,
            'pending_file_id' => null,
            'pending_file_name' => null,
            'context' => null,
        ]);

        return $this->buildResponse($nextStep);
    }

    private function commitStep(int $userId, array $state): void
    {
        if ($state['step'] === self::STEP_CONFIRM_FIRST_NAME) {
            $this->userRepository->update($userId, ['first_name' => $state['pending_text']]);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_LAST_NAME) {
            $this->userRepository->update($userId, ['last_name' => $state['pending_text']]);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_NATIONAL_CODE) {
            $this->userRepository->update($userId, ['national_code' => $state['pending_text']]);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_POSTAL_CODE) {
            $this->userRepository->update($userId, ['postal_code' => $state['pending_text']]);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_ADDRESS) {
            $this->userRepository->update($userId, ['address' => $state['pending_text']]);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_SELFIE) {
            $this->fileRepository->saveFile($userId, 'selfie', $state['pending_file_id'], $state['pending_file_name']);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_ID_CARD_FRONT) {
            $this->fileRepository->saveFile($userId, 'id_card_front', $state['pending_file_id'], $state['pending_file_name']);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_ID_CARD_BACK) {
            $this->fileRepository->saveFile($userId, 'id_card_back', $state['pending_file_id'], $state['pending_file_name']);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_REFEREE_NAME) {
            $this->userRepository->update($userId, ['referee_name' => $state['pending_text']]);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_REFEREE_PHONE) {
            $this->userRepository->update($userId, ['referee_phone' => $state['pending_text']]);
            return;
        }
        if ($state['step'] === self::STEP_CONFIRM_EMPLOYMENT_DOCUMENT) {
            $this->fileRepository->saveFile($userId, 'employment_document', $state['pending_file_id'], $state['pending_file_name']);
            return;
        }
    }

    private function findFirstIncompleteStep(int $userId, array $user): string
    {
        $userData = $this->userRepository->findById($userId);
        $fileMap = [
            'selfie' => 'selfie',
            'id_card_front' => 'id_card_front',
            'id_card_back' => 'id_card_back',
            'employment_document' => 'employment_document',
        ];

        $steps = [
            self::STEP_ENTER_FIRST_NAME => fn() => !(bool)trim((string)$userData['first_name']),
            self::STEP_ENTER_LAST_NAME => fn() => !(bool)trim((string)$userData['last_name']),
            self::STEP_ENTER_NATIONAL_CODE => fn() => !(bool)trim((string)$userData['national_code']),
            self::STEP_ENTER_POSTAL_CODE => fn() => !(bool)trim((string)$userData['postal_code']),
            self::STEP_ENTER_ADDRESS => fn() => !(bool)trim((string)$userData['address']),
            self::STEP_UPLOAD_SELFIE => fn() => $this->fileRepository->findByUserIdAndType($userId, 'selfie') === null,
            self::STEP_UPLOAD_ID_CARD_FRONT => fn() => $this->fileRepository->findByUserIdAndType($userId, 'id_card_front') === null,
            self::STEP_UPLOAD_ID_CARD_BACK => fn() => $this->fileRepository->findByUserIdAndType($userId, 'id_card_back') === null,
            self::STEP_ENTER_REFEREE_NAME => fn() => !(bool)trim((string)$userData['referee_name']),
            self::STEP_ENTER_REFEREE_PHONE => fn() => !(bool)trim((string)$userData['referee_phone']),
            self::STEP_UPLOAD_EMPLOYMENT_DOCUMENT => fn() => $this->fileRepository->findByUserIdAndType($userId, 'employment_document') === null,
        ];

        foreach ($steps as $step => $predicate) {
            if ($predicate()) {
                return $step;
            }
        }

        return self::STEP_REVIEW;
    }

    private function getNextStep(string $currentStep): string
    {
        $index = array_search($currentStep, self::STEPS, true);
        if ($index === false || $index === count(self::STEPS) - 1) {
            return self::STEP_REVIEW;
        }

        return self::STEPS[$index + 1];
    }

    private function getPreviousEnterStep(string $confirmStep): string
    {
        $index = array_search($confirmStep, self::STEPS, true);
        if ($index === false || $index === 0) {
            return self::STEP_ENTER_FIRST_NAME;
        }
        return self::STEPS[$index - 1];
    }

    private function isConfirmStep(string $step): bool
    {
        return str_starts_with($step, 'confirm_');
    }

    private function isUploadStep(string $step): bool
    {
        return str_starts_with($step, 'upload_');
    }

    private function getConfirmStep(string $enterStep): string
    {
        $mapping = [
            self::STEP_ENTER_FIRST_NAME => self::STEP_CONFIRM_FIRST_NAME,
            self::STEP_ENTER_LAST_NAME => self::STEP_CONFIRM_LAST_NAME,
            self::STEP_ENTER_NATIONAL_CODE => self::STEP_CONFIRM_NATIONAL_CODE,
            self::STEP_ENTER_POSTAL_CODE => self::STEP_CONFIRM_POSTAL_CODE,
            self::STEP_ENTER_ADDRESS => self::STEP_CONFIRM_ADDRESS,
            self::STEP_UPLOAD_SELFIE => self::STEP_CONFIRM_SELFIE,
            self::STEP_UPLOAD_ID_CARD_FRONT => self::STEP_CONFIRM_ID_CARD_FRONT,
            self::STEP_UPLOAD_ID_CARD_BACK => self::STEP_CONFIRM_ID_CARD_BACK,
            self::STEP_ENTER_REFEREE_NAME => self::STEP_CONFIRM_REFEREE_NAME,
            self::STEP_ENTER_REFEREE_PHONE => self::STEP_CONFIRM_REFEREE_PHONE,
            self::STEP_UPLOAD_EMPLOYMENT_DOCUMENT => self::STEP_CONFIRM_EMPLOYMENT_DOCUMENT,
        ];

        return $mapping[$enterStep] ?? self::STEP_REVIEW;
    }

    private function getPendingLabel(string $step, array $state): ?string
    {
        if ($this->isUploadStep($step)) {
            return $state['pending_file_name'] ?? 'یک فایل ارسال شده است.';
        }

        return $state['pending_text'] ?? null;
    }

    private function validateStepInput(string $step, ?string $value, ?string $fileName): true|string
    {
        if ($step === self::STEP_ENTER_FIRST_NAME || $step === self::STEP_ENTER_LAST_NAME || $step === self::STEP_ENTER_REFEREE_NAME) {
            if ($value === '' || mb_strlen($value) < 2) {
                return 'مقدار وارد شده باید حداقل ۲ کاراکتر باشد.';
            }
            return true;
        }

        if ($step === self::STEP_ENTER_NATIONAL_CODE) {
            if (!preg_match('/^\d{10}$/', $value)) {
                return 'کد ملی باید ۱۰ رقم باشد.';
            }
            if (!$this->validateIranianNationalCode($value)) {
                return 'کد ملی معتبر نیست.';
            }
            return true;
        }

        if ($step === self::STEP_ENTER_POSTAL_CODE) {
            if (!preg_match('/^\d{10}$/', $value)) {
                return 'کد پستی باید ۱۰ رقم باشد.';
            }
            return true;
        }

        if ($step === self::STEP_ENTER_ADDRESS) {
            if (mb_strlen($value) < 5) {
                return 'آدرس باید حداقل ۵ کاراکتر داشته باشد.';
            }
            return true;
        }

        if ($step === self::STEP_ENTER_REFEREE_PHONE) {
            if (!preg_match('/^09\d{9}$/', $value)) {
                return 'شماره موبایل معرف باید ۱۱ رقم و با 09 شروع شود.';
            }
            return true;
        }

        if ($this->isUploadStep($step)) {
            if ($fileName) {
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($step, [self::STEP_UPLOAD_SELFIE, self::STEP_UPLOAD_ID_CARD_FRONT, self::STEP_UPLOAD_ID_CARD_BACK], true)) {
                    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                        return 'لطفاً فقط عکس با فرمت JPG یا PNG ارسال کنید.';
                    }
                    return true;
                }
                if ($step === self::STEP_UPLOAD_EMPLOYMENT_DOCUMENT) {
                    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                        return 'لطفاً یک عکس یا فایل PDF معتبر ارسال کنید.';
                    }
                    return true;
                }
            }

            return true;
        }

        return 'مقدار ارسال شده معتبر نیست. لطفاً دوباره تلاش کنید.';
    }

    private function validateIranianNationalCode(string $code): bool
    {
        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }

        $check = (int)$code[9];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$code[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        return ($remainder < 2 && $check === $remainder) || ($remainder >= 2 && $check === 11 - $remainder);
    }

    public function buildReviewResponse(array $user, array $state): array
    {
        $files = [
            'selfie' => $this->fileRepository->findByUserIdAndType($user['id'], 'selfie'),
            'id_card_front' => $this->fileRepository->findByUserIdAndType($user['id'], 'id_card_front'),
            'id_card_back' => $this->fileRepository->findByUserIdAndType($user['id'], 'id_card_back'),
            'employment_document' => $this->fileRepository->findByUserIdAndType($user['id'], 'employment_document'),
        ];

        $firstName = trim((string)($user['first_name'] ?? ''));
        $lastName = trim((string)($user['last_name'] ?? ''));
        
        $greeting = 'کاربر گرامی';
        if ($firstName && $lastName) {
            $greeting = "کاربر گرامی {$firstName} {$lastName}";
        } elseif ($firstName) {
            $greeting = "کاربر گرامی {$firstName}";
        }

        $text = implode("\n", [
            $greeting,
            '',
            'لطفاً اطلاعات وارد شده خود را مرور کنید:',
            '-------------------------',
            'نام: ' . ($user['first_name'] ?? '-'),
            'نام خانوادگی: ' . ($user['last_name'] ?? '-'),
            'کد ملی: ' . ($user['national_code'] ?? '-'),
            'کد پستی: ' . ($user['postal_code'] ?? '-'),
            'آدرس: ' . ($user['address'] ?? '-'),
            'سلفی: ' . ($files['selfie'] ? '✔' : '✖'),
            'روی کارت ملی: ' . ($files['id_card_front'] ? '✔' : '✖'),
            'پشت کارت ملی: ' . ($files['id_card_back'] ? '✔' : '✖'),
            'معرف: ' . ($user['referee_name'] ?? '-'),
            'شماره معرف: ' . ($user['referee_phone'] ?? '-'),
            'مدرک شغلی: ' . ($files['employment_document'] ? '✔' : '✖'),
            '-------------------------',
        ]);

        return [
            'text' => $text,
            'keyboard' => KeyboardService::reviewKeyboard(),
        ];
    }
}
