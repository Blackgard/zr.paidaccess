<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

if (!empty($arResult['ERROR'])): ?>
    <p><?= htmlspecialcharsbx($arResult['ERROR']) ?></p>
    <?php
    return;
endif;

$totalAmount = (int)($arResult['TOTAL_AMOUNT'] ?? 0);
$payerCount = (int)($arResult['PAYER_COUNT'] ?? 0);
$items = $arResult['ITEMS'] ?? [];
?>
<h3 class="wallet__title-h2">Участников: <?= $payerCount ?></h3>
<h3 class="wallet__title-h2">Баланс средств учредительного фонда: <?= number_format($totalAmount, 0, '.', ' ') ?> ₽</h3>

<div class="wallet__wrap wallet__wrap_max-width wallet__wrap_active" data-target="history">
    <div class="wallet__inner">
        <div class="wallet__col wc_1">Транзакция</div>
        <div class="wallet__col wc_7">Дата</div>
        <div class="wallet__col wc_2">Имя оплатившего</div>
        <div class="wallet__col wc_4">Операция</div>
    </div>
    <?php if ($items === []): ?>
        <div class="wallet__inner">
            <div class="wallet__col" style="grid-column: 1 / -1;">Платежи пока не найдены.</div>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="wallet__inner">
                <div class="wallet__col wc_1">
                    <?= htmlspecialcharsbx((string)($item['ORDER_ID'] ?? '')) ?>
                </div>
                <div class="wallet__col wc_7">
                    <?= htmlspecialcharsbx((string)($item['DATE_PAID'] ?? '')) ?>
                </div>
                <div class="wallet__col wc_2">
                    <?= htmlspecialcharsbx((string)($item['PAYER_NAME'] ?? '')) ?>
                </div>
                <div class="wallet__col wc_4 color_green">
                    +<?= htmlspecialcharsbx((string)($item['AMOUNT_FORMATTED'] ?? '0')) ?> ₽
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
