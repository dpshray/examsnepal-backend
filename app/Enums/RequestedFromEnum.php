<?php

namespace App\Enums;

enum RequestedFromEnum:string
{
    case ANDROID = 'ANDROID';
    case IOS = 'IOS';
    case WEB = 'WEB';
}
