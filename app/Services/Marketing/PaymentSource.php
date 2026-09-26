<?php

namespace App\Services\Marketing;

/**
 * Tells admin-added subscriptions (offline payments entered through
 * AdminController::addSubscriber) apart from gateway checkouts.
 *
 * The transaction id can't be used: eSewa, ConnectIPS and the admin form all
 * generate 'TXN' . rand(10000, 99999). The `data` column differs instead:
 * gateways store the provider payload, while the admin form stores only
 * {"remark": ...} - double-encoded, because it json_encode()s into a column
 * the model already casts to array.
 */
class PaymentSource
{
    public static function isManual(?string $data): bool
    {
        $decoded = json_decode((string) $data, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) && array_diff(array_keys($decoded), ['remark']) === [];
    }
}
