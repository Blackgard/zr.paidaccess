<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

$this->addExternalCss($templateFolder . '/style.css');

if (!empty($arResult['ERROR'])): ?>
    <div class="zr-paidaccess-members zr-paidaccess-members--error">
        <p><?= htmlspecialcharsbx($arResult['ERROR']) ?></p>
    </div>
    <?php
    return;
endif;

$showTotal = ($arResult['SHOW_TOTAL_AMOUNT'] ?? 'N') === 'Y';
$items = $arResult['ITEMS'] ?? [];
?>
<div class="zr-paidaccess-members">
    <h2 class="zr-paidaccess-members__title">Участники</h2>

    <?php if ($items === []): ?>
        <p class="zr-paidaccess-members__empty">Участники не найдены.</p>
    <?php else: ?>
        <div class="zr-paidaccess-members__table-wrap">
            <table class="zr-paidaccess-members__table">
                <thead>
                <tr>
                    <th>Участник</th>
                    <th>Статус</th>
                    <th>Период</th>
                    <th>Окончание доступа</th>
                    <th>Последняя оплата</th>
                    <?php if ($showTotal): ?>
                        <th>Всего оплачено</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr class="<?= htmlspecialcharsbx($item['ROW_CSS_CLASS'] ?? '') ?>">
                        <td><?= htmlspecialcharsbx($item['NAME'] ?? '') ?></td>
                        <td>
                            <span class="zr-paidaccess-badge <?= htmlspecialcharsbx($item['BADGE_CSS_CLASS'] ?? '') ?>">
                                <?= htmlspecialcharsbx($item['ACCESS_LABEL'] ?? '') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialcharsbx($item['BILLING_PERIOD_LABEL'] ?? '') ?></td>
                        <td><?= htmlspecialcharsbx($item['PERIOD_END'] ?? '—') ?></td>
                        <td><?= htmlspecialcharsbx($item['LAST_PAID_DATE'] !== '' ? $item['LAST_PAID_DATE'] : '—') ?></td>
                        <?php if ($showTotal): ?>
                            <td><?= htmlspecialcharsbx($item['TOTAL_PAID_FORMATTED'] ?? '0') ?> ₽</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
