<?php

namespace App\Enums;

enum PaymentStatusEnum: string
{
    case PAYMENT_INIT = 'PAYMENT_INIT';
    case PAYMENT_PENDING = 'PAYMENT_PENDING';
    case PAYMENT_SUCCESS = 'PAYMENT_SUCCESS';
    case PAYMENT_ERROR = 'PAYMENT_ERROR';
}
