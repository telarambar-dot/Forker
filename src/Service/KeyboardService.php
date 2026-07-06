<?php

namespace App\Service;

use RubikaBot\Keyboard\Button;
use RubikaBot\Keyboard\Keypad;

class KeyboardService
{
    public static function makeKeyboard(array $buttons): array
    {
        $keypad = Keypad::make();
        foreach ($buttons as $buttonRow) {
            $row = $keypad->row();
            foreach ($buttonRow as $button) {
                $row->add(Button::simple($button['id'], $button['text']));
            }
        }
        return $keypad->toArray();
    }

    public static function unverifiedKeyboard(): array
    {
        return self::makeKeyboard([
            [
                ['id' => 'membership_auth', 'text' => 'عضویت و احراز هویت'],
            ],
        ]);
    }

    public static function confirmKeyboard(): array
    {
        return self::makeKeyboard([
            [
                ['id' => 'confirm_register', 'text' => 'ثبت'],
                ['id' => 'confirm_edit', 'text' => 'ویرایش'],
            ],
        ]);
    }

    public static function reviewKeyboard(): array
    {
        return self::makeKeyboard([
            [
                ['id' => 'review_register_final', 'text' => 'ثبت نهایی'],
                ['id' => 'review_edit_info', 'text' => 'ویرایش اطلاعات'],
            ],
        ]);
    }

    public static function adminMenuKeyboard(): array
    {
        return self::makeKeyboard([
            [
                ['id' => 'admin_list_requests', 'text' => 'مشاهده درخواست‌ها'],
            ],
        ]);
    }

    public static function adminPendingListKeyboard(array $pendingUsers): array
    {
        $rows = [];
        foreach ($pendingUsers as $user) {
            $rows[] = [
                ['id' => 'admin_view_' . $user['id'], 'text' => sprintf('%s %s', $user['first_name'] ?: 'نامشخص', $user['last_name'] ?: '...')],
            ];
        }
        return empty($rows) ? self::makeKeyboard([[['id' => 'admin_cancel', 'text' => 'بازگشت']]]) : self::makeKeyboard($rows);
    }

    public static function adminRequestActionKeyboard(int $userId): array
    {
        return self::makeKeyboard([
            [
                ['id' => 'admin_approve_' . $userId, 'text' => 'تایید'],
                ['id' => 'admin_reject_' . $userId, 'text' => 'رد'],
            ],
            [
                ['id' => 'admin_cancel', 'text' => 'بازگشت'],
            ],
        ]);
    }
}
