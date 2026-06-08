<?php

/**
 * Webhook платёжных шлюзов.
 *
 * URL: /local/modules/zr.paidaccess/tools/webhook.php?id={ID шлюза}
 * ID — запись в zr_paidaccess_gateway (указан в форме редактирования шлюза).
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_PUBLIC_MODE', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Zr\PaidAccess\Payment\PaymentWebhookService;
use Zr\PaidAccess\PaidAccessCore;

if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
    http_response_code(503);
    echo 'Module not loaded';
    return;
}

$gatewayId = (int)($_GET['id'] ?? $_GET['gateway_id'] ?? 0);
$rawBody = file_get_contents('php://input') ?: '';

if ($gatewayId <= 0) {
    http_response_code(400);
    echo 'Gateway id required';
    return;
}

PaymentWebhookService::processRequest($gatewayId, $rawBody);
