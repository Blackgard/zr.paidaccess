<?php

/**
 * Проверка подписи webhook T-Bank по JSON из журнала «Платёжный шлюз».
 *
 * CLI:
 *   php tools/verify_webhook_token.php --gateway-id=1 --body='{"TerminalKey":"...","Token":"..."}'
 *   php tools/verify_webhook_token.php --gateway-id=1 --file=webhook.json
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Zr\PaidAccess\Gateway\Contract\GatewayWebhookDebugVerifierInterface;
use Zr\PaidAccess\Gateway\GatewayFactory;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\PaidAccessCore;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Запускайте из CLI.\n");
    exit(1);
}

if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
    fwrite(STDERR, "Модуль zr.paidaccess не загружен.\n");
    exit(1);
}

$options = getopt('', ['gateway-id:', 'body:', 'file:']);
$gatewayId = (int)($options['gateway-id'] ?? 0);
$rawBody = '';

if (!empty($options['file'])) {
    $path = (string)$options['file'];
    if (!is_file($path)) {
        fwrite(STDERR, "Файл не найден: {$path}\n");
        exit(1);
    }
    $rawBody = (string)file_get_contents($path);
} elseif (!empty($options['body'])) {
    $rawBody = (string)$options['body'];
} else {
    fwrite(STDERR, "Укажите --body или --file.\n");
    exit(1);
}

if ($gatewayId <= 0) {
    fwrite(STDERR, "Укажите --gateway-id=ID шлюза из webhook.php?id=...\n");
    exit(1);
}

$decoded = json_decode($rawBody, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "Некорректный JSON.\n");
    exit(1);
}

$payload = isset($decoded['body']) && is_array($decoded['body']) ? $decoded['body'] : $decoded;

$gatewayRow = GatewayRepository::getById($gatewayId);
if (!$gatewayRow) {
    fwrite(STDERR, "Шлюз #{$gatewayId} не найден.\n");
    exit(1);
}

$gateway = GatewayFactory::createById($gatewayId);
if (!$gateway instanceof GatewayWebhookDebugVerifierInterface) {
    fwrite(STDERR, "Шлюз #{$gatewayId} не поддерживает диагностику подписи webhook.\n");
    exit(2);
}

$debugInfo = $gateway->getWebhookDebugInfo($payload);
$payloadTerminal = $debugInfo['payloadTerminalKey'];
$configuredTerminal = $debugInfo['configuredTerminalKey'];

echo "Шлюз #{$gatewayId}\n";
echo "TerminalKey в настройках: {$configuredTerminal}\n";
echo "TerminalKey в webhook:    {$payloadTerminal}\n";
echo 'Поля подписи: ' . implode(', ', $debugInfo['tokenFields']) . "\n";

if ($payloadTerminal !== '' && $configuredTerminal !== '' && $payloadTerminal !== $configuredTerminal) {
    echo "Результат: FAIL — TerminalKey не совпадает.\n";
    exit(2);
}

$received = $debugInfo['receivedToken'];
$expected = $debugInfo['expectedToken'];

if ($received === '' || !hash_equals($expected, $received)) {
    echo "Результат: FAIL — Invalid Token\n";
    echo "Ожидали: {$expected}\n";
    echo "Получили: {$received}\n";
    exit(3);
}

echo "Результат: OK — подпись webhook валидна.\n";

try {
    $result = $gateway->handleWebhook($payload);
    echo 'handleWebhook: ' . ($result->valid ? 'valid' : 'invalid') . ', status=' . $result->gatewayStatus . "\n";
} catch (Throwable $e) {
    echo 'handleWebhook exception: ' . $e->getMessage() . "\n";
}

exit(0);
