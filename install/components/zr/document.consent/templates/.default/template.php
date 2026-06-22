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
$requireDocumentOpen = !empty($arResult['REQUIRE_DOCUMENT_OPEN']);
if ($pendingDocuments === []): ?>
    <p>Все обязательные документы подтверждены.</p>
    <?php return;
endif;
?>
<form method="post" class="zr-document-consent-form" id="zr-document-consent-form">
    <?= bitrix_sessid_post() ?>
    <?php foreach ($pendingDocuments as $document): ?>
        <?php
        $needsOpen = \Zr\PaidAccess\Document\DocumentConsentService::mustOpenDocumentBeforeConsent(
            $document,
            (string)($arResult['SITE_ID'] ?? '')
        );
        ?>
        <div class="zr-document-consent-item" data-doc-row data-needs-open="<?= $needsOpen ? 'Y' : 'N' ?>">
            <div class="zr-document-consent-item__title"><?= htmlspecialcharsbx((string)$document['TITLE']) ?></div>
            <div class="zr-document-consent-item__meta"><?= htmlspecialcharsbx(\Zr\PaidAccess\Document\DocumentVersionService::formatVersionLabel((string)$document['VERSION'])) ?></div>
            <?php if (($document['FILE_URL'] ?? '') !== ''): ?>
                <a href="<?= htmlspecialcharsbx((string)$document['FILE_URL']) ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   data-doc-open>Открыть документ</a>
            <?php endif; ?>
            <?php if (($document['BODY_HTML'] ?? '') !== ''): ?>
                <div class="zr-document-consent-item__body" data-doc-body><?= $document['BODY_HTML'] ?></div>
            <?php endif; ?>
            <label class="zr-document-consent-check<?= $needsOpen ? ' is-locked' : '' ?>">
                <input type="checkbox"
                       name="version_ids[]"
                       value="<?= (int)$document['VERSION_ID'] ?>"
                       class="zr-consent-checkbox"
                       <?= $needsOpen ? 'disabled' : '' ?>
                       <?= $needsOpen ? '' : 'required' ?>>
                Ознакомлен(а) и согласен(на)
            </label>
            <?php if ($needsOpen): ?>
                <p class="zr-document-consent-hint" data-doc-hint>Сначала откройте документ</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <button type="submit" id="zr-document-consent-submit" <?= $requireDocumentOpen ? 'disabled' : '' ?>>Подтвердить</button>
</form>
<?php if ($requireDocumentOpen): ?>
<script>
(function () {
    var form = document.getElementById('zr-document-consent-form');
    if (!form) {
        return;
    }

    var submitBtn = document.getElementById('zr-document-consent-submit');

    function updateSubmitState() {
        var allChecked = true;
        form.querySelectorAll('.zr-consent-checkbox').forEach(function (checkbox) {
            if (!checkbox.checked) {
                allChecked = false;
            }
        });
        if (submitBtn) {
            submitBtn.disabled = !allChecked;
        }
    }

    form.querySelectorAll('[data-doc-row]').forEach(function (row) {
        var openEl = row.querySelector('[data-doc-open]');
        var checkbox = row.querySelector('.zr-consent-checkbox');
        var checkLabel = row.querySelector('.zr-document-consent-check');
        var hint = row.querySelector('[data-doc-hint]');
        var needsOpen = row.getAttribute('data-needs-open') === 'Y';

        function unlock() {
            checkbox.disabled = false;
            checkbox.required = true;
            if (checkLabel) {
                checkLabel.classList.remove('is-locked');
            }
            if (hint) {
                hint.style.display = 'none';
            }
        }

        if (!needsOpen) {
            unlock();
        } else if (openEl) {
            openEl.addEventListener('click', function () {
                unlock();
            });
        }

        checkbox.addEventListener('change', updateSubmitState);
    });

    updateSubmitState();
})();
</script>
<?php endif; ?>
