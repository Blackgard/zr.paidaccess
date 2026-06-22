<?php

/**
 * Шаблон страницы согласия с обязательными документами.
 * Редактируйте этот файл свободно — при обновлении модуля он не перезаписывается.
 * Бизнес-логика: Zr\PaidAccess\Document\DocumentConsentService.
 */

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Zr\PaidAccess\Document\DocumentConsentService;
use Zr\PaidAccess\Document\DocumentVersionService;
use Zr\PaidAccess\PaidAccessCore;

if (!function_exists('zrDocumentConsentStartsWith')) {
    function zrDocumentConsentStartsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('zrDocumentConsentIsSafeRedirectUrl')) {
    function zrDocumentConsentIsSafeRedirectUrl(string $url): bool
    {
        if ($url === '' || zrDocumentConsentStartsWith($url, '//')) {
            return false;
        }

        if (zrDocumentConsentStartsWith($url, '/')) {
            return !zrDocumentConsentStartsWith($url, '/bitrix/admin');
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return false;
        }

        return zrDocumentConsentStartsWith($url, 'http://' . $host)
            || zrDocumentConsentStartsWith($url, 'https://' . $host);
    }
}

if (!function_exists('zrDocumentConsentResolveBackUrl')) {
    function zrDocumentConsentResolveBackUrl(): string
    {
        $request = Context::getCurrent()->getRequest();
        $uri = (string)$request->getRequestUri();
        if ($uri !== '' && zrDocumentConsentIsSafeRedirectUrl($uri)) {
            return $uri;
        }

        return '/';
    }
}

global $USER;

$errorMessage = '';
$pendingDocuments = [];
$siteId = PaidAccessCore::normalizeSiteId();
$userId = is_object($USER) && $USER->IsAuthorized() ? (int)$USER->GetID() : 0;

if (!Loader::includeModule(PaidAccessCore::MODULE_ID) || $userId <= 0) {
    $errorMessage = 'Требуется авторизация.';
} else {
    $request = Context::getCurrent()->getRequest();
    if ($request->isPost() && check_bitrix_sessid()) {
        try {
            $versionIds = $request->getPost('version_ids');
            if (!is_array($versionIds)) {
                $versionIds = [];
            }
            DocumentConsentService::acceptDocuments($userId, $versionIds, $siteId);

            $backUrl = (string)$request->getPost('back_url');
            if ($backUrl === '' || !zrDocumentConsentIsSafeRedirectUrl($backUrl)) {
                $backUrl = '/';
            }
            LocalRedirect($backUrl);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }

    $pendingDocuments = DocumentConsentService::getPendingDocuments($userId, $siteId);
}

$backUrl = zrDocumentConsentResolveBackUrl();
$documentCount = count($pendingDocuments);
$footerText = PaidAccessCore::getBlockPageFooterText($siteId);
$requireDocumentOpen = PaidAccessCore::isDocumentConsentRequireOpen($siteId);

$resolveExtClass = static function (string $ext): string {
    $ext = strtolower($ext);
    if ($ext === 'pdf') {
        return 'pdf';
    }
    if (in_array($ext, ['doc', 'docx'], true)) {
        return 'doc';
    }
    if ($ext === 'txt') {
        return 'txt';
    }

    return 'file';
};

$resolveExtLabel = static function (array $document): string {
    $ext = (string)($document['FILE_EXT'] ?? '');
    if ($ext !== '' && $ext !== 'file') {
        return strtoupper($ext);
    }
    if ((string)($document['BODY_HTML'] ?? '') !== '') {
        return 'HTML';
    }

    return 'DOC';
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Согласие с документами</title>
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
            padding: 20px;
        }
        h1 { margin: 0 0 8px; font-size: 1.35rem; font-weight: 600; }
        .lead { margin: 0 0 16px; color: #555; font-size: 14px; line-height: 1.5; }
        .err {
            margin: 0 0 12px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 14px;
        }
        .list { list-style: none; margin: 0; padding: 0; }
        .row {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #fafafa;
        }
        .row.is-checked { background: #fff; border-color: #d1d5db; }
        .row__open {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
            min-width: 0;
        }
        .row__open:hover .row__name { text-decoration: underline; }
        .ext {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .02em;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
            color: #4b5563;
        }
        .ext--pdf { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .ext--doc { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .ext--txt { background: #f9fafb; color: #6b7280; }
        .row__main { flex: 1; min-width: 0; }
        .row__name {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-weight: 600;
            font-size: 13px;
            color: #1f2937;
            line-height: 1.4;
        }
        .row__meta { font-size: 11px; color: #9ca3af; margin-top: 2px; }
        .row__body {
            margin-top: 8px;
            padding: 8px 10px;
            max-height: 120px;
            overflow: auto;
            font-size: 12px;
            color: #4b5563;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            line-height: 1.5;
        }
        .check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            cursor: pointer;
            user-select: none;
        }
        .check.is-locked { cursor: not-allowed; opacity: .55; }
        .check input { width: 16px; height: 16px; margin: 0; accent-color: #111827; }
        .check input:disabled { cursor: not-allowed; }
        .check span { font-size: 12px; color: #4b5563; }
        .hint { margin: 4px 0 0 24px; font-size: 11px; color: #9ca3af; }
        .btn {
            width: 100%;
            margin-top: 12px;
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .empty {
            padding: 16px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px dashed #e5e7eb;
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
        <h1>Обязательные документы</h1>
        <p class="lead">
            <?php if ($requireDocumentOpen): ?>
                Откройте каждый документ и подтвердите согласие
            <?php else: ?>
                Подтвердите согласие с каждым документом
            <?php endif; ?>
            <?php if ($documentCount > 0): ?>
                (<?= $documentCount ?>).
            <?php else: ?>.
            <?php endif; ?>
        </p>

        <?php if ($errorMessage !== ''): ?>
            <div class="err" role="alert"><?= htmlspecialcharsbx($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($pendingDocuments === []): ?>
            <div class="empty">Нет документов, ожидающих подтверждения.</div>
        <?php else: ?>
            <form method="post" id="zr-document-consent-form">
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="back_url" value="<?= htmlspecialcharsbx($backUrl) ?>">

                <ul class="list">
                    <?php foreach ($pendingDocuments as $document): ?>
                        <?php
                        $fileUrl = (string)($document['FILE_URL'] ?? '');
                        $bodyHtml = (string)($document['BODY_HTML'] ?? '');
                        $hasFile = $fileUrl !== '';
                        $hasBody = $bodyHtml !== '';
                        $needsOpen = DocumentConsentService::mustOpenDocumentBeforeConsent($document, $siteId);
                        $extClass = $resolveExtClass((string)($document['FILE_EXT'] ?? ''));
                        $extLabel = $resolveExtLabel($document);
                        $displayName = (string)($document['FILE_NAME'] ?? $document['TITLE']);
                        ?>
                        <li class="row" data-doc-row data-needs-open="<?= $needsOpen ? 'Y' : 'N' ?>">
                            <?php if ($hasFile): ?>
                                <a class="row__open"
                                   href="<?= htmlspecialcharsbx($fileUrl) ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   data-doc-open>
                                    <span class="ext ext--<?= htmlspecialcharsbx($extClass) ?>"><?= htmlspecialcharsbx($extLabel) ?></span>
                                    <span class="row__main">
                                        <span class="row__name"><?= htmlspecialcharsbx((string)$document['TITLE']) ?></span>
                                        <span class="row__meta"><?= htmlspecialcharsbx(DocumentVersionService::formatVersionLabel((string)$document['VERSION'])) ?><?php if (($document['DATE_PUBLISH'] ?? '') !== ''): ?> · <?= htmlspecialcharsbx((string)$document['DATE_PUBLISH']) ?><?php endif; ?></span>
                                    </span>
                                </a>
                            <?php else: ?>
                                <a class="row__open"
                                   href="#"
                                   data-doc-open
                                   data-expand="Y">
                                    <span class="ext ext--<?= htmlspecialcharsbx($extClass) ?>"><?= htmlspecialcharsbx($extLabel) ?></span>
                                    <span class="row__main">
                                        <span class="row__name"><?= htmlspecialcharsbx((string)$document['TITLE']) ?></span>
                                        <span class="row__meta"><?= htmlspecialcharsbx(DocumentVersionService::formatVersionLabel((string)$document['VERSION'])) ?><?php if (($document['DATE_PUBLISH'] ?? '') !== ''): ?> · <?= htmlspecialcharsbx((string)$document['DATE_PUBLISH']) ?><?php endif; ?></span>
                                    </span>
                                </a>
                                <?php if ($hasBody): ?>
                                    <div class="row__body" data-doc-body hidden><?= $bodyHtml ?></div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <label class="check<?= $needsOpen ? ' is-locked' : '' ?>">
                                <input type="checkbox"
                                       name="version_ids[]"
                                       value="<?= (int)$document['VERSION_ID'] ?>"
                                       class="zr-consent-checkbox"
                                       <?= $needsOpen ? 'disabled' : '' ?>>
                                <span>Согласен(на) с условиями</span>
                            </label>
                            <?php if ($needsOpen): ?>
                                <p class="hint" data-doc-hint>Сначала откройте документ</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <button type="submit" class="btn" id="zr-document-consent-submit" disabled>
                    Подтвердить
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($footerText !== ''): ?>
        <footer class="page-footer"><?= nl2br($footerText) ?></footer>
    <?php endif; ?>
</div>
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
        submitBtn.disabled = !allChecked;
    }

    form.querySelectorAll('[data-doc-row]').forEach(function (row) {
        var openEl = row.querySelector('[data-doc-open]');
        var checkbox = row.querySelector('.zr-consent-checkbox');
        var checkLabel = row.querySelector('.check');
        var hint = row.querySelector('[data-doc-hint]');
        var needsOpen = row.getAttribute('data-needs-open') === 'Y';

        function unlock() {
            checkbox.disabled = false;
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
            openEl.addEventListener('click', function (event) {
                if (openEl.getAttribute('data-expand') === 'Y') {
                    event.preventDefault();
                    var body = row.querySelector('[data-doc-body]');
                    if (body) {
                        body.hidden = !body.hidden;
                        if (!body.hidden) {
                            unlock();
                        }
                    } else {
                        unlock();
                    }
                } else {
                    unlock();
                }
            });
        }

        checkbox.addEventListener('change', function () {
            row.classList.toggle('is-checked', checkbox.checked);
            updateSubmitState();
        });
    });

    updateSubmitState();
})();
</script>
</body>
</html>
