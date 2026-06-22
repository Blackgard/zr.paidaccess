<?php

namespace Zr\PaidAccess\PublicUi;

use Bitrix\Main\Loader;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Subscription\SubscriptionPaymentQuote;

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

        $quote = SubscriptionPaymentQuote::forUser($userId, $siteId);
        if ($modulePaymentId > 0) {
            $paymentQuote = SubscriptionPaymentQuote::fromPaymentId($modulePaymentId, $siteId);
            if ($paymentQuote !== null) {
                $quote = $paymentQuote;
            }
        }

        $breakdown = $quote->totalBreakdown;
        $monthlyBreakdown = $quote->monthlyBreakdown;
        $billingPeriod = $quote->coveredPeriods !== [] ? $quote->coveredPeriods[count($quote->coveredPeriods) - 1] : '';
        $billingPeriodLabel = $quote->periodsRangeLabel !== ''
            ? $quote->periodsRangeLabel
            : BillingPolicy::formatPeriodLabel($billingPeriod, $siteId);
        $paymentPageErrorText = PaidAccessCore::getPaymentPageErrorText($siteId);
        $isArrearsPayment = $quote->isArrearsPayment;

        include __DIR__ . '/views/pay_block_page.php';
    }
}
