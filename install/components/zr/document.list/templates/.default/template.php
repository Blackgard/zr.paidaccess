<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

if (!empty($arResult['ERROR'])): ?>
    <p class="zr-doc-list__error"><?= htmlspecialcharsbx($arResult['ERROR']) ?></p>
    <?php
    return;
endif;

$items = $arResult['ITEMS'] ?? [];
$showHeader = !empty($arResult['SHOW_HEADER']);
$headerTitle = (string)($arResult['HEADER_TITLE'] ?? 'Наименование документа');
?>
<div class="zr-doc-list">
    <?php if ($showHeader): ?>
        <div class="zr-doc-list__head"><?= htmlspecialcharsbx($headerTitle) ?></div>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="zr-doc-list__row zr-doc-list__row--empty">Документы не найдены.</div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <?php
            $url = (string)($item['URL'] ?? '');
            $title = (string)($item['TITLE'] ?? '');
            $openInNewTab = !empty($item['OPEN_IN_NEW_TAB']);
            ?>
            <div class="zr-doc-list__row">
                <?php if ($url !== ''): ?>
                    <a class="zr-doc-list__link"
                       href="<?= htmlspecialcharsbx($url) ?>"
                       <?php if ($openInNewTab): ?>
                           target="_blank" rel="noopener noreferrer"
                       <?php endif; ?>>
                        <?= htmlspecialcharsbx($title) ?>
                    </a>
                <?php else: ?>
                    <span class="zr-doc-list__title"><?= htmlspecialcharsbx($title) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
