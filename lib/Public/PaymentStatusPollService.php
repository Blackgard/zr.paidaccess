<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;

/**
 * Публичный опрос статуса платежа для JS-редиректа после оплаты.
 */
class PaymentStatusPollService
{
    public const STATUS_ENDPOINT = '/local/modules/zr.paidaccess/tools/payment_status.php';

    /**
     * @return array{
     *     ok: bool,
     *     paid?: bool,
     *     status?: string,
     *     redirectUrl?: string,
     *     error?: string,
     *     httpCode: int
     * }
     */
    public static function buildResponse(int $paymentId, int $userId, ?string $siteId = null): array
    {
        if ($userId <= 0) {
            return [
                'ok' => false,
                'error' => 'unauthorized',
                'httpCode' => 401,
            ];
        }

        if ($paymentId <= 0) {
            return [
                'ok' => false,
                'error' => 'invalid_payment_id',
                'httpCode' => 400,
            ];
        }

        return self::buildResponseForPayment(
            PaymentRepository::getById($paymentId),
            $userId,
            $siteId
        );
    }

    /**
     * @param array<string, mixed>|null $payment
     * @return array{
     *     ok: bool,
     *     paid?: bool,
     *     status?: string,
     *     redirectUrl?: string,
     *     error?: string,
     *     httpCode: int
     * }
     */
    public static function buildResponseForPayment(?array $payment, int $userId, ?string $siteId = null): array
    {
        if ($userId <= 0) {
            return [
                'ok' => false,
                'error' => 'unauthorized',
                'httpCode' => 401,
            ];
        }

        if ($payment === null) {
            return [
                'ok' => false,
                'error' => 'not_found',
                'httpCode' => 404,
            ];
        }

        if ((int)($payment['USER_ID'] ?? 0) !== $userId) {
            return [
                'ok' => false,
                'error' => 'forbidden',
                'httpCode' => 403,
            ];
        }

        $status = (string)($payment['STATUS'] ?? '');
        $paid = PaymentStatus::grantsAccess($status);

        return [
            'ok' => true,
            'paid' => $paid,
            'status' => $status,
            'redirectUrl' => PaidAccessCore::getPaymentSuccessRedirectAbsoluteUrl($siteId),
            'httpCode' => 200,
        ];
    }

    public static function getStatusEndpointUrl(): string
    {
        return self::STATUS_ENDPOINT;
    }
}
