<?php
/**
 * @var bool $hasPaymentError
 * @var array $infoMessages
 * @var int $modulePaymentId
 * @var float $amount
 * @var string $billingPeriod
 * @var string $billingPeriodLabel
 * @var string $paymentPageErrorText
 */

use Zr\PaidAccess\Payment\SubscriptionPaymentService;

if (!defined('B_PROLOG_INCLUDED')) {
    define('B_PROLOG_INCLUDED', true);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оплата подписки</title>
    <link rel="stylesheet" href="/local/modules/zr.paidaccess/install/assets/payment-button.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f4f6f8;
            color: #1a1a1a;
            padding: 16px;
        }
        .zr-paidaccess-block {
            max-width: 520px;
            width: 100%;
            padding: 40px 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .zr-paidaccess-block h1 {
            margin: 0 0 12px;
            font-size: 1.35rem;
            font-weight: 600;
        }
        .zr-paidaccess-block p {
            margin: 0 0 16px;
            line-height: 1.5;
            color: #555;
        }
        .zr-paidaccess-amount {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 24px;
            color: #1a1a1a;
        }
        .zr-paidaccess-info {
            color: #555;
            text-align: left;
            margin-bottom: 16px;
        }
        .zr-paidaccess-error-box {
            text-align: left;
            margin: 20px 0 0;
            padding: 16px 18px;
            border-radius: 8px;
            background: #fdecea;
            border: 1px solid #f5c6cb;
        }
        .zr-paidaccess-error-box__title {
            margin: 0 0 8px;
            font-weight: 600;
            color: #c0392b;
        }
        .zr-paidaccess-error-box__text {
            margin: 0;
            line-height: 1.55;
            color: #7f2a22;
        }
        .zr-paidaccess-pay {
            width: 100%;
            text-align: left;
            margin-top: 8px;
        }
        .zr-paidaccess-pay form {
            margin: 0 auto;
        }
        .zr-paidaccess-qr img {
            display: block;
            margin: 0 auto 12px;
        }
    </style>
</head>
<body>
<div class="zr-paidaccess-block<?= $hasPaymentError ? ' zr-paidaccess-block--error' : '' ?>">
    <h1><?= $hasPaymentError ? 'Ошибка оплаты' : 'Доступ ограничен' ?></h1>

    <?php if ($hasPaymentError): ?>
        <div class="zr-paidaccess-error-box">
            <p class="zr-paidaccess-error-box__title">Не удалось открыть оплату</p>
            <div class="zr-paidaccess-error-box__text"><?= nl2br(htmlspecialcharsbx($paymentPageErrorText)) ?></div>
        </div>
    <?php else: ?>
        <p>Для продолжения работы с сайтом оплатите ежемесячный взнос за период <strong><?= htmlspecialcharsbx($billingPeriodLabel ?? $billingPeriod) ?></strong>.</p>

        <?php if (!empty($infoMessages)): ?>
            <div class="zr-paidaccess-info">
                <?php foreach ($infoMessages as $message): ?>
                    <p><?= htmlspecialcharsbx($message) ?></p>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="zr-paidaccess-amount"><?= number_format($amount, 0, '.', ' ') ?> ₽</div>
            <div class="zr-paidaccess-pay">
                <?php SubscriptionPaymentService::renderPaymentWidget($modulePaymentId); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
