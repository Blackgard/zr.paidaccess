<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

if (!empty($arResult['ERROR'])): ?>
    <p><?= htmlspecialcharsbx($arResult['ERROR']) ?></p>
<?php else: ?>
    <p>Шаблон компонента <code>zr:panel</code> не найден в шаблоне сайта.
        Скопируйте визуал в <code>local/templates/&lt;шаблон&gt;/components/zr/panel/.default/</code>.</p>
<?php endif; ?>
