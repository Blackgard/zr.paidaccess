<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Payment\PaymentRepository;

/**
 * Оркестрация admin-формы редактирования платежа (zr_paidaccess_payment_edit.php).
 */
class PaymentAdminEditService
{
    /**
     * @return array<string, mixed>
     */
    public static function buildEmptyFormValues(int $prefillUserId): array
    {
        return [
            'USER_ID' => $prefillUserId > 0 ? $prefillUserId : '',
            'BILLING_PERIOD' => PaymentAdminService::getDefaultBillingPeriod($prefillUserId),
            'AMOUNT' => PaymentAdminService::getDefaultAmount(),
            'CURRENCY' => 'RUB',
            'STATUS' => PaymentStatus::PENDING,
            'DESCRIPTION' => '',
            'ORDER_ID' => '',
            'GATEWAY_CODE' => PaymentAdminService::MANUAL_GATEWAY_CODE,
            'DATE_PAID' => '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function loadPaymentFormValues(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $payment = PaymentRepository::getById($id);

        return is_array($payment) ? self::normalizePaymentRow($payment) : null;
    }

    /**
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    public static function normalizePaymentRow(array $payment): array
    {
        $values = array_merge(self::buildEmptyFormValues((int)($payment['USER_ID'] ?? 0)), $payment);
        if (!empty($payment['DATE_PAID']) && $payment['DATE_PAID'] instanceof \Bitrix\Main\Type\DateTime) {
            $values['DATE_PAID'] = $payment['DATE_PAID']->toString();
        }

        return $values;
    }

    public static function shouldProcessSave($save, $apply): bool
    {
        return ($save !== null && $save !== '') || ($apply !== null && $apply !== '');
    }

    /**
     * @param array<string, mixed>|\Bitrix\Main\HttpRequest $request
     * @return array<string, mixed>
     */
    public static function extractPostData($request): array
    {
        if (is_array($request)) {
            return [
                'USER_ID' => $request['USER_ID'] ?? null,
                'BILLING_PERIOD' => $request['BILLING_PERIOD'] ?? null,
                'AMOUNT' => $request['AMOUNT'] ?? null,
                'CURRENCY' => $request['CURRENCY'] ?? null,
                'STATUS' => $request['STATUS'] ?? null,
                'DESCRIPTION' => $request['DESCRIPTION'] ?? null,
            ];
        }

        return [
            'USER_ID' => $request->getPost('USER_ID'),
            'BILLING_PERIOD' => $request->getPost('BILLING_PERIOD'),
            'AMOUNT' => $request->getPost('AMOUNT'),
            'CURRENCY' => $request->getPost('CURRENCY'),
            'STATUS' => $request->getPost('STATUS'),
            'DESCRIPTION' => $request->getPost('DESCRIPTION'),
        ];
    }

    /**
     * @param array<string, mixed> $postData
     */
    public static function processSave(
        array $postData,
        bool $isEditMode,
        int $id,
        $save,
        $apply,
        string $languageId,
        string $currentPage
    ): PaymentAdminEditSaveResult {
        $result = new PaymentAdminEditSaveResult();
        $result->formValues = $postData;

        try {
            $wasNew = !$isEditMode;
            $paymentId = PaymentAdminService::save($postData, $isEditMode ? $id : null);
            $payment = PaymentRepository::getById($paymentId);

            $result->success = true;
            $result->paymentId = $paymentId;
            $result->isEditMode = true;
            $result->formValues = is_array($payment)
                ? self::normalizePaymentRow($payment)
                : array_merge($postData, ['ID' => $paymentId]);

            if ($save !== null && $save !== '') {
                $result->redirectUrl = 'zr_paidaccess_payments.php?lang=' . $languageId;

                return $result;
            }

            if ($wasNew) {
                $result->redirectUrl = $currentPage . '?ID=' . $paymentId . '&lang=' . $languageId;
                $result->redirectHttpStatus = 301;

                return $result;
            }

            $result->showSuccessMessage = true;

            return $result;
        } catch (\Throwable $e) {
            $result->success = false;
            $result->errorMessage = $e->getMessage();

            return $result;
        }
    }
}
