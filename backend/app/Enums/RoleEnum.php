<?php

namespace App\Enums;

enum RoleEnum:String
{
    case ADMIN = 'admin';
    case TEACHER = 'teacher';
    case UPLOAD = 'upload';
    case UPLOADER = 'uploader';
    case CORPORATE = 'corporate';
    case PARTICIPANT = 'participant';# linked to corporation


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
