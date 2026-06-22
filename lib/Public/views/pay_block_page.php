<?php
/**
 * @var bool $hasPaymentError
 * @var array $infoMessages
 * @var int $modulePaymentId
 * @var \Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown $breakdown
 * @var \Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown $monthlyBreakdown
 * @var \Zr\PaidAccess\Subscription\SubscriptionPaymentQuote $quote
 * @var bool $isArrearsPayment
 * @var string $billingPeriod
 * @var string $billingPeriodLabel
 * @var string $paymentPageErrorText
 */

use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;

if (!defined('B_PROLOG_INCLUDED')) {
    define('B_PROLOG_INCLUDED', true);
}

$showBreakdown = $quote->showComponentBreakdown();
$periodText = htmlspecialcharsbx($billingPeriodLabel ?? $billingPeriod);
$siteId = PaidAccessCore::normalizeSiteId();
$footerText = PaidAccessCore::getBlockPageFooterText($siteId);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оплата подписки</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            font: 14px/1.5 Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1a1a1a;
            background: #f4f6f8;
        }
        .wrap { width: 100%; max-width: 520px; }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            padding: 32px 28px;
        }
        .badge {
            display: inline-block;
            margin-bottom: 12px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .badge--err { background: #f3f4f6; color: #4b5563; }
        h1 { margin: 0 0 12px; font-size: 1.35rem; font-weight: 600; }
        .lead { margin: 0 0 16px; color: #555; font-size: 14px; line-height: 1.5; }
        .lead strong { color: #1a1a1a; }
        .err {
            padding: 12px 14px;
            border-radius: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 14px;
            line-height: 1.55;
        }
        .info {
            padding: 12px 14px;
            border-radius: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }
        .sum {
            padding: 16px;
            border-radius: 10px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            margin-bottom: 16px;
        }
        .sum__label {
            margin: 0 0 4px;
            font-size: 0.9rem;
            color: #6b7280;
        }
        .sum__amount {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.2;
            color: #1a1a1a;
        }
        .sum__period { margin: 8px 0 0; font-size: 0.9rem; color: #6b7280; }
        .rows { list-style: none; margin: 12px 0 0; padding: 12px 0 0; border-top: 1px solid #e5e7eb; }
        .rows li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 4px 0;
            font-size: 0.9rem;
            color: #555;
        }
        .rows li span:last-child { color: #1a1a1a; font-weight: 500; font-variant-numeric: tabular-nums; }
        .rows li.total {
            margin-top: 8px;
            padding-top: 10px;
            border-top: 1px dashed #e5e7eb;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a1a;
        }
        .rows li.total span:last-child { font-weight: 600; color: #1a1a1a; }
        .rows li.arrears-note { font-size: 0.85rem; color: #6b7280; }
        .rows li.arrears-period { padding-left: 8px; }
        .pay { padding-top: 16px; border-top: 1px solid #e5e7eb; }
        .pay__hint { margin: 0 0 12px; text-align: center; font-size: 0.9rem; color: #6b7280; }
        .zr-paidaccess-pay form { margin: 0; }
        .zr-paidaccess-pay-action { width: 100%; margin: 0; }
        .zr-paidaccess-pay-btn--tbank {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 20px;
            border: 1px solid #111827;
            border-radius: 10px;
            background: #111827;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            line-height: 1.2;
        }
        .zr-paidaccess-pay-btn--tbank:hover { background: #1f2937; color: #fff; text-decoration: none; }
        .zr-paidaccess-pay-btn__logo { width: 22px; height: 22px; filter: brightness(0) invert(1); }
        .zr-paidaccess-qr { text-align: center; }
        .zr-paidaccess-qr img {
            max-width: 100%;
            height: auto;
            padding: 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }
        .page-footer {
            margin-top: 16px;
            padding: 0 4px;
            text-align: center;
            font-size: 0.9rem;
            line-height: 1.5;
            color: #6b7280;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <?php if ($hasPaymentError): ?>
            <span class="badge badge--err">Ошибка</span>
            <h1>Не удалось открыть оплату</h1>
            <div class="err"><?= nl2br(htmlspecialcharsbx($paymentPageErrorText)) ?></div>
        <?php else: ?>
            <span class="badge">Оплата подписки</span>
            <h1>Доступ ограничен</h1>
            <p class="lead">
                <?php if ($isArrearsPayment): ?>
                    Для продолжения работы с сайтом оплатите задолженность за <strong><?= (int)$quote->periodCount ?></strong>
                    <?= $quote->periodCount === 1 ? 'период' : ($quote->periodCount < 5 ? 'периода' : 'периодов') ?>
                    (<strong><?= $periodText ?></strong>) одним платежом.
                <?php else: ?>
                    Для продолжения работы с сайтом оплатите ежемесячный взнос за период <strong><?= $periodText ?></strong>.
                <?php endif; ?>
            </p>

            <?php if (!empty($infoMessages)): ?>
                <div class="info">
                    <?php foreach ($infoMessages as $message): ?>
                        <p style="margin:0 0 6px"><?= htmlspecialcharsbx($message) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sum">
                    <?php if ($showBreakdown): ?>
                        <p class="sum__label">К оплате</p>
                        <p class="sum__amount"><?= number_format($breakdown->chargeTotal, 0, '.', ' ') ?> ₽</p>
                        <p class="sum__period">Период<?= $isArrearsPayment ? 'ы' : '' ?>: <?= $periodText ?></p>
                        <ul class="rows">
                            <?php if ($isArrearsPayment): ?>
                                <li class="arrears-note"><span>Задолженность</span><span><?= (int)$quote->periodCount ?> × <?= number_format($monthlyBreakdown->chargeTotal, 0, '.', ' ') ?> ₽</span></li>
                                <?php foreach ($quote->coveredPeriodLabels as $periodLabel): ?>
                                    <li class="arrears-period"><span><?= htmlspecialcharsbx($periodLabel) ?></span><span><?= number_format($monthlyBreakdown->chargeTotal, 0, '.', ' ') ?> ₽</span></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li><span>Налоги</span><span><?= number_format($breakdown->taxAmount, 0, '.', ' ') ?> ₽</span></li>
                            <li><span>Содержание сайта (ФОТ)</span><span><?= number_format($breakdown->maintenanceAmount, 0, '.', ' ') ?> ₽</span></li>
                            <li><span>Средства в учредительный фонд</span><span><?= number_format($breakdown->fundAmount, 0, '.', ' ') ?> ₽</span></li>
                            <li class="total"><span>Итого</span><span><?= number_format($breakdown->chargeTotal, 0, '.', ' ') ?> ₽</span></li>
                        </ul>
                    <?php else: ?>
                        <p class="sum__label">Фондовый взнос</p>
                        <p class="sum__amount"><?= number_format($breakdown->fundAmount, 0, '.', ' ') ?> ₽</p>
                        <p class="sum__period">Период<?= $isArrearsPayment ? 'ы' : '' ?>: <?= $periodText ?></p>
                        <?php if ($isArrearsPayment): ?>
                            <ul class="rows">
                                <li class="arrears-note"><span>Задолженность</span><span><?= (int)$quote->periodCount ?> × <?= number_format($monthlyBreakdown->fundAmount, 0, '.', ' ') ?> ₽</span></li>
                                <?php foreach ($quote->coveredPeriodLabels as $periodLabel): ?>
                                    <li class="arrears-period"><span><?= htmlspecialcharsbx($periodLabel) ?></span><span><?= number_format($monthlyBreakdown->fundAmount, 0, '.', ' ') ?> ₽</span></li>
                                <?php endforeach; ?>
                                <li class="total"><span>Итого</span><span><?= number_format($breakdown->fundAmount, 0, '.', ' ') ?> ₽</span></li>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="pay">
                    <p class="pay__hint">Способ оплаты</p>
                    <div class="zr-paidaccess-pay">
                        <?php
                        if (Loader::includeModule(PaidAccessCore::MODULE_ID)) {
                            SubscriptionPaymentService::renderPaymentWidget($modulePaymentId);
                        }
?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($footerText !== ''): ?>
        <footer class="page-footer"><?= nl2br(htmlspecialcharsbx($footerText)) ?></footer>
    <?php endif; ?>
</div>
</body>
</html>
