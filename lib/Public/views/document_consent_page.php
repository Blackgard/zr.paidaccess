<?php
/**
 * @var string $errorMessage
 * @var array<int, array<string, mixed>> $pendingDocuments
 * @var string $backUrl
 * @var bool $requireDocumentOpen
 */

if (!defined('B_PROLOG_INCLUDED')) {
    define('B_PROLOG_INCLUDED', true);
}

use Zr\PaidAccess\Document\DocumentConsentService;
use Zr\PaidAccess\Document\DocumentVersionService;

$documentCount = count($pendingDocuments);
$requireDocumentOpen = $requireDocumentOpen ?? true;

/**
 * @return string
 */
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

/**
 * @return string
 */
$resolveExtLabel = static function (array $document) use ($resolveExtClass): string {
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
            font: 14px/1.45 Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #111827;
            background: #f3f4f6;
        }
        .wrap { width: 100%; max-width: 520px; }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 6px 16px rgba(16,24,40,.06);
            padding: 20px;
        }
        .badge {
            display: inline-block;
            margin-bottom: 10px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #fffbeb;
            color: #92400e;
        }
        h1 { margin: 0 0 6px; font-size: 1.15rem; font-weight: 700; }
        .lead { margin: 0 0 16px; color: #6b7280; font-size: 13px; }
        .err {
            margin: 0 0 12px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 13px;
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
        .row__open:hover .row__name { color: #111827; text-decoration: underline; }
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
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
        .check input { width: 16px; height: 16px; margin: 0; flex-shrink: 0; accent-color: #111827; }
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
            font-size: 13px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px dashed #e5e7eb;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <span class="badge">Требуется действие</span>
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
                        $needsOpen = DocumentConsentService::mustOpenDocumentBeforeConsent($document);
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
                                        <span class="row__name"><?= htmlspecialcharsbx($displayName) ?></span>
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
