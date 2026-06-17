<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

if (!empty($arResult['ERROR'])): ?>
    <div class="zr-document-consent-error"><?= htmlspecialcharsbx($arResult['ERROR']) ?></div>
    <?php return;
endif;

if (!empty($arResult['SUCCESS'])): ?>
    <div class="zr-document-consent-success">Согласие сохранено.</div>
    <?php return;
endif;

$pendingDocuments = $arResult['PENDING_DOCUMENTS'] ?? [];
if ($pendingDocuments === []): ?>
    <p>Все обязательные документы подтверждены.</p>
    <?php return;
endif;
?>
<form method="post" class="zr-document-consent-form">
    <?= bitrix_sessid_post() ?>
    <?php foreach ($pendingDocuments as $document): ?>
        <div class="zr-document-consent-item">
            <div class="zr-document-consent-item__title"><?= htmlspecialcharsbx((string)$document['TITLE']) ?></div>
            <div class="zr-document-consent-item__meta"><?= htmlspecialcharsbx(\Zr\PaidAccess\Document\DocumentVersionService::formatVersionLabel((string)$document['VERSION'])) ?></div>
            <?php if (($document['FILE_URL'] ?? '') !== ''): ?>
                <a href="<?= htmlspecialcharsbx((string)$document['FILE_URL']) ?>" target="_blank" rel="noopener noreferrer">Открыть документ</a>
            <?php endif; ?>
            <?php if (($document['BODY_HTML'] ?? '') !== ''): ?>
                <div class="zr-document-consent-item__body"><?= $document['BODY_HTML'] ?></div>
            <?php endif; ?>
            <label>
                <input type="checkbox" name="version_ids[]" value="<?= (int)$document['VERSION_ID'] ?>" required>
                Ознакомлен(а) и согласен(на)
            </label>
        </div>
    <?php endforeach; ?>
    <button type="submit">Подтвердить</button>
</form>
