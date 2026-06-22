<?php

/**
 * @link https://alexandr-zr.ru/
 * @author Alexandr Drachenin <alexdrachenin98@gmail.com>
 * @copyright Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)
 */

$moduleId = 'zr.paidaccess';

\Bitrix\Main\Loader::registerAutoLoadClasses($moduleId, require __DIR__ . '/autoload.production.map.php');

\Zr\PaidAccess\Gateway\Provider\GatewayProviderLoader::registerAutoload($moduleId);
