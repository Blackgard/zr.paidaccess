<?php

/**
 * Диагностика Init T-Bank: IP, traceroute, пробный Init для техподдержки.
 *
 * CLI:
 *   php tools/diagnose_tinkoff_init.php --gateway-id=1
 *   php tools/diagnose_tinkoff_init.php --gateway-id=1 --site-id=s1 --email=user@example.com
 *   php tools/diagnose_tinkoff_init.php --gateway-id=1 --no-init --json
 *   php tools/diagnose_tinkoff_init.php --gateway-id=1 --output=report.txt
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Zr\PaidAccess\Admin\TinkoffInitDiagnosticAdminService;
use Zr\PaidAccess\PaidAccessCore;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Запускайте из CLI.\n");
    exit(1);
}

if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
    fwrite(STDERR, "Модуль zr.paidaccess не загружен.\n");
    exit(1);
}

$options = getopt('', [
    'gateway-id:',
    'site-id::',
    'email::',
    'no-init',
    'no-traceroute',
    'json',
    'output::',
]);

$gatewayId = (int)($options['gateway-id'] ?? 0);
if ($gatewayId <= 0) {
    fwrite(STDERR, "Укажите --gateway-id=ID шлюза T-Bank из админки.\n");
    exit(1);
}

$siteId = isset($options['site-id']) ? (string)$options['site-id'] : null;
$email = isset($options['email']) ? (string)$options['email'] : '';
$runInit = !isset($options['no-init']);
$runTraceroute = !isset($options['no-traceroute']);
$asJson = isset($options['json']);
$outputPath = isset($options['output']) ? (string)$options['output'] : '';

try {
    $report = TinkoffInitDiagnosticAdminService::run($gatewayId, $siteId, [
        'runInit' => $runInit,
        'runTraceroute' => $runTraceroute,
        'email' => $email,
        'timeoutSeconds' => TinkoffInitDiagnosticAdminService::DEFAULT_TIMEOUT_SECONDS,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
    exit(2);
}

$supportPackage = (string)($report['supportPackage'] ?? '');

if ($outputPath !== '') {
    $written = file_put_contents($outputPath, $supportPackage);
    if ($written === false) {
        fwrite(STDERR, "Не удалось записать файл: {$outputPath}\n");
        exit(3);
    }
    fwrite(STDOUT, "Отчёт записан: {$outputPath}\n");
}

if ($asJson) {
    $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    fwrite(STDOUT, ($json !== false ? $json : '{}') . "\n");
} else {
    fwrite(STDOUT, $supportPackage . "\n");
}

$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$initOk = !empty($summary['initSuccess']);
$tcpOk = !empty($summary['tcp443Success']);

exit($runInit && !$initOk && (int)($summary['initHttpStatus'] ?? 0) === 0 ? 4 : ($tcpOk || $initOk ? 0 : 1));
