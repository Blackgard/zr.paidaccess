<?php

namespace Zr\PaidAccess\Payment;

use Zr\PaidAccess\PaidAccessCore;

/**
 * Сообщение об ошибке оплаты для пользователя (без технических деталей).
 */
class PaymentPageErrorRenderer
{
    public static function render(?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $text = PaidAccessCore::getPaymentPageErrorText($siteId);

        echo '<div class="zr-paidaccess-error-box">';
        echo '<p class="zr-paidaccess-error-box__title">Не удалось открыть оплату</p>';
        echo '<div class="zr-paidaccess-error-box__text">' . nl2br(htmlspecialcharsbx($text)) . '</div>';
        echo '</div>';
    }
}
