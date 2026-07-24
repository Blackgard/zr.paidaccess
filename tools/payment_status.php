<?php

/**
 * Опрос статуса платежа для авторедиректа после оплаты.
 *
 * URL: /local/modules/zr.paidaccess/tools/payment_status.php?payment_id={ID}
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_PUBLIC_MODE', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\PublicUi\PaymentStatusPollService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'module_not_loaded'], JSON_UNESCAPED_UNICODE);

    return;
}

global $USER;

$userId = (is_object($USER) && method_exists($USER, 'IsAuthorized') && $USER->IsAuthorized())
    ? (int)$USER->GetID()
    : 0;

$paymentId = (int)($_GET['payment_id'] ?? $_GET['id'] ?? 0);
$siteId = PaidAccessCore::normalizeSiteId(
    isset($_GET['site_id']) ? (string)$_GET['site_id'] : null
);

$result = PaymentStatusPollService::buildResponse($paymentId, $userId, $siteId);
$httpCode = (int)($result['httpCode'] ?? 200);
unset($result['httpCode']);

http_response_code($httpCode);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
