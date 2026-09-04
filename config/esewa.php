<?php

return [
    'merchant_code' => env('ESEWA_MERCHANT_CODE'),
    'secret_key' => env('ESEWA_SECRET_KEY'),
    'payment_url' => env('ESEWA_PAYMENT_URL'),
    'verify_url' => env('ESEWA_VERIFY_URL', 'https://rc-epay.esewa.com.np/api/epay/transaction/status'),
    'status_check_url' => env('ESEWA_STATUS_URL'),
    'app_redirect_base' => env('ESEWA_APP_REDIRECT_BASE'),
];
