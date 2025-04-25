<?php

namespace App\Enums;

enum ExamTypeEnum: Int
{
    case FREE_QUIZ = 1;
    case SPRINT_QUIZ = 3;
    case MOCK_TEST = 4;

    public static function getKeyByValue(int $value): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case->name;
            }
        }
        return null;
    }
}
