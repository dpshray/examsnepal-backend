<?php

namespace App\Enums;

enum NotificationTypeEnum:string
{
    case BULK_NOTIFICATION = 'BULK_NOTIFICATION'; 
    case NEW_EXAM = 'NEW_EXAM'; 
    case DOUBT_RESOLVED = 'DOUBT_RESOLVED'; 
}
