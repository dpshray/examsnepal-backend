<?php

namespace App\Enums;

enum ExamTypeEnum: Int
{
    case FREE_QUIZ = 1;
    case SPRINT_QUIZ = 3;
    case MOCK_TEST = 4;
}
