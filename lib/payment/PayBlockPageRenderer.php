<?php

namespace Zr\PaidAccess\Payment;

use Bitrix\Main\Loader;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown;

class PayBlockPageRenderer
{
    public static function render(): void
    {
        global $USER;

        $hasPaymentError = false;
        $infoMessages = [];
        $modulePaymentId = 0;
        $siteId = PaidAccessCore::normalizeSiteId();
        $userId = is_object($USER) && $USER->IsAuthorized() ? (int)$USER->GetID() : 0;

        if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
            $hasPaymentError = true;
            ModuleEventLogService::error('payment_page_module', 'Модуль zr.paidaccess не загружен');
        } else {
            $gatewayError = GatewayRepository::getConfigurationError($siteId);
            if ($gatewayError !== null) {
                $hasPaymentError = true;
                ModuleEventLogService::error('payment_page_gateway', $gatewayError, [], null, $userId > 0 ? $userId : null, $siteId);
            } elseif (!is_object($USER) || !$USER->IsAuthorized()) {
                $infoMessages[] = 'Требуется авторизация.';
            } else {
                try {
                    $modulePaymentId = SubscriptionPaymentService::preparePayment($userId, $siteId);
                } catch (\Throwable $e) {
                    $hasPaymentError = true;
                }
            }
        }

        $breakdown = SubscriptionAmountBreakdown::fromSite($siteId);
        $billingPeriod = SubscriptionPaymentService::getCurrentBillingPeriod($userId, $siteId);
        $billingPeriodLabel = BillingPolicy::formatPeriodLabel($billingPeriod, $siteId);
        $paymentPageErrorText = PaidAccessCore::getPaymentPageErrorText($siteId);

        include __DIR__ . '/views/pay_block_page.php';
    }
}
