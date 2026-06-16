<?php
/**
 * @var string $errorMessage
 * @var array<int, array<string, mixed>> $pendingDocuments
 * @var string $backUrl
 */

if (!defined('B_PROLOG_INCLUDED')) {
    define('B_PROLOG_INCLUDED', true);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Согласие с документами</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f4f6f8;
            color: #1a1a1a;
            padding: 16px;
        }
        .zr-document-consent {
            max-width: 640px;
            width: 100%;
            padding: 32px 28px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }
        .zr-document-consent h1 {
            margin: 0 0 12px;
            font-size: 1.35rem;
            font-weight: 600;
        }
        .zr-document-consent__lead {
            margin: 0 0 24px;
            line-height: 1.5;
            color: #555;
        }
        .zr-document-consent__error {
            margin: 0 0 16px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
        }
        .zr-document-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .zr-document-item__title {
            font-weight: 600;
            margin-bottom: 6px;
        }
        .zr-document-item__meta {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .zr-document-item__body {
            font-size: 0.92rem;
            color: #374151;
            margin-bottom: 10px;
            max-height: 180px;
            overflow: auto;
        }
        .zr-document-item__link {
            display: inline-block;
            margin-bottom: 12px;
            color: #2563eb;
            text-decoration: none;
        }
        .zr-document-item__link:hover {
            text-decoration: underline;
        }
        .zr-document-item__check {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            font-size: 0.95rem;
        }
        .zr-document-consent__submit {
            margin-top: 20px;
            width: 100%;
            border: 0;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: #2563eb;
            cursor: pointer;
        }
        .zr-document-consent__submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="zr-document-consent">
    <h1>Обязательные документы</h1>
    <p class="zr-document-consent__lead">
        Для продолжения работы с сайтом ознакомьтесь с документами ниже и подтвердите согласие.
    </p>

    <?php if ($errorMessage !== ''): ?>
        <div class="zr-document-consent__error"><?= htmlspecialcharsbx($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ($pendingDocuments === []): ?>
        <p>Нет документов, ожидающих подтверждения.</p>
    <?php else: ?>
        <form method="post" id="zr-document-consent-form">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="back_url" value="<?= htmlspecialcharsbx($backUrl) ?>">

            <?php foreach ($pendingDocuments as $document): ?>
                <div class="zr-document-item">
                    <div class="zr-document-item__title">
                        <?= htmlspecialcharsbx((string)$document['TITLE']) ?>
                    </div>
                    <div class="zr-document-item__meta">
                        Версия <?= (int)$document['VERSION'] ?>
                        <?php if (($document['DATE_PUBLISH'] ?? '') !== ''): ?>
                            · опубликовано <?= htmlspecialcharsbx((string)$document['DATE_PUBLISH']) ?>
                        <?php endif; ?>
                    </div>

                    <?php if (($document['FILE_URL'] ?? '') !== ''): ?>
                        <a class="zr-document-item__link"
                           href="<?= htmlspecialcharsbx((string)$document['FILE_URL']) ?>"
                           target="_blank"
                           rel="noopener noreferrer">Открыть документ</a>
                    <?php endif; ?>

                    <?php if (($document['BODY_HTML'] ?? '') !== ''): ?>
                        <div class="zr-document-item__body"><?= $document['BODY_HTML'] ?></div>
                    <?php endif; ?>

                    <label class="zr-document-item__check">
                        <input type="checkbox"
                               name="version_ids[]"
                               value="<?= (int)$document['VERSION_ID'] ?>"
                               class="zr-document-consent-checkbox">
                        <span>Ознакомлен(а) и согласен(на) с условиями документа</span>
                    </label>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="zr-document-consent__submit" id="zr-document-consent-submit" disabled>
                Подтвердить
            </button>
        </form>
    <?php endif; ?>
</div>
<script>
(function () {
    var form = document.getElementById('zr-document-consent-form');
    if (!form) {
        return;
    }
    var submitBtn = document.getElementById('zr-document-consent-submit');
    var checkboxes = form.querySelectorAll('.zr-document-consent-checkbox');

    function updateSubmitState() {
        var allChecked = true;
        checkboxes.forEach(function (checkbox) {
            if (!checkbox.checked) {
                allChecked = false;
            }
        });
        submitBtn.disabled = !allChecked;
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSubmitState);
    });
    updateSubmitState();
})();
</script>
</body>
</html>
