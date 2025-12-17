<?php

namespace App\Enums;

#this is linked to 'status' column of 'exams' table
enum ExamTypeEnum: Int
{
    case FREE_QUIZ = 3;
    case SPRINT_QUIZ = 4;
    case MOCK_TEST = 1;

    public static function getKeyByValue(?int $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case->name;
            }
        }
        return null;
    }
}
