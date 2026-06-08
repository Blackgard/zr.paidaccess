<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Zr\PaidAccess\Payment\SubscriptionPaymentService;

/** @var array $arResult */

$this->addExternalCss($templateFolder . '/style.css');
$this->addExternalCss('/local/modules/zr.paidaccess/install/assets/payment-button.css');

if (!empty($arResult['ERROR'])): ?>
    <div class="zr-paidaccess-personal zr-paidaccess-personal--error">
        <p><?= htmlspecialcharsbx($arResult['ERROR']) ?></p>
    </div>
    <?php
    return;
endif;
?>
<div class="zr-paidaccess-personal">
    <div class="zr-paidaccess-personal__header">
        <h2 class="zr-paidaccess-personal__title">Моя подписка</h2>
        <span class="zr-paidaccess-badge <?= htmlspecialcharsbx($arResult['ACCESS_BADGE_CLASS'] ?? '') ?>">
            <?= htmlspecialcharsbx($arResult['ACCESS_LABEL'] ?? '') ?>
        </span>
    </div>

    <dl class="zr-paidaccess-personal__facts">
        <div class="zr-paidaccess-personal__fact">
            <dt>Расчётный период</dt>
            <dd><?= htmlspecialcharsbx($arResult['BILLING_PERIOD_LABEL'] ?? '') ?></dd>
        </div>
        <?php if (!empty($arResult['PERIOD_END'])): ?>
            <div class="zr-paidaccess-personal__fact">
                <dt>Дата окончания доступа</dt>
                <dd><?= htmlspecialcharsbx($arResult['PERIOD_END']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if ($arResult['DAYS_LEFT'] !== null && !empty($arResult['IS_ACTIVE'])): ?>
            <div class="zr-paidaccess-personal__fact">
                <dt>Дней до окончания</dt>
                <dd>
                    <?php if ((int)$arResult['DAYS_LEFT'] >= 0): ?>
                        <?= (int)$arResult['DAYS_LEFT'] ?>
                    <?php else: ?>
                        <span class="zr-paidaccess-personal__overdue">истёк</span>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($arResult['SHOW_PAYMENT_BLOCK']) && !empty($arResult['DUE_DATE'])): ?>
            <div class="zr-paidaccess-personal__fact">
                <dt>Оплатить до</dt>
                <dd>
                    <?= htmlspecialcharsbx($arResult['DUE_DATE']) ?>
                    <?php if ($arResult['DAYS_UNTIL_DUE'] !== null): ?>
                        <span class="zr-paidaccess-personal__hint">
                            (<?= (int)$arResult['DAYS_UNTIL_DUE'] >= 0 ? 'осталось ' . (int)$arResult['DAYS_UNTIL_DUE'] . ' дн.' : 'просрочено' ?>)
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endif; ?>
    </dl>

    <?php if (!empty($arResult['SHOW_PAYMENT_BLOCK'])): ?>
        <div class="zr-paidaccess-personal__payment">
            <div class="zr-paidaccess-personal__amount">
                <?= htmlspecialcharsbx($arResult['AMOUNT_FORMATTED'] ?? '') ?> ₽
            </div>

            <?php if (!empty($arResult['PAYMENT_ERROR'])): ?>
                <div class="zr-paidaccess-error-box">
                    <p class="zr-paidaccess-error-box__title">Не удалось открыть оплату</p>
                    <div class="zr-paidaccess-error-box__text"><?= nl2br(htmlspecialcharsbx($arResult['PAYMENT_ERROR'])) ?></div>
                </div>
            <?php elseif ((int)($arResult['MODULE_PAYMENT_ID'] ?? 0) > 0): ?>
                <div class="zr-paidaccess-personal__pay-widget">
                    <?php SubscriptionPaymentService::renderPaymentWidget((int)$arResult['MODULE_PAYMENT_ID']); ?>
                </div>
            <?php elseif (!empty($arResult['CAN_INIT_PAYMENT'])): ?>
                <a class="zr-paidaccess-btn" href="<?= htmlspecialcharsbx($arResult['PAY_PREPARE_URL'] ?? '#') ?>">
                    Оплатить подписку
                </a>
            <?php else: ?>
                <p class="zr-paidaccess-personal__hint">Оплата за этот период уже выполнена или недоступна.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
