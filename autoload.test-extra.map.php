<?php

/**
 * Дополнительные классы для PHPUnit autoload (provider adapters и зависимости без Bitrix Loader).
 *
 * Production map: autoload.production.map.php
 * Test map = production + этот файл.
 */
return [
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffApiClient' => 'lib/Gateway/Providers/Tinkoff/TinkoffApiClient.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffStatusMapper' => 'lib/Gateway/Providers/Tinkoff/TinkoffStatusMapper.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffGateway' => 'lib/Gateway/Providers/Tinkoff/TinkoffGateway.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffPaymentUrlResolver' => 'lib/Gateway/Providers/Tinkoff/TinkoffPaymentUrlResolver.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffInitError' => 'lib/Gateway/Providers/Tinkoff/TinkoffInitError.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffDuplicateOrderRecovery' => 'lib/Gateway/Providers/Tinkoff/TinkoffDuplicateOrderRecovery.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffProvider' => 'lib/Gateway/Providers/Tinkoff/TinkoffProvider.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffConfig' => 'lib/Gateway/Providers/Tinkoff/TinkoffConfig.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffFieldOptions' => 'lib/Gateway/Providers/Tinkoff/TinkoffFieldOptions.php',
    'Zr\\PaidAccess\\Gateway\\Providers\\Tinkoff\\TinkoffReceiptBuilder' => 'lib/Gateway/Providers/Tinkoff/TinkoffReceiptBuilder.php',
    'Zr\\PaidAccess\\Gateway\\Provider\\GatewayProviderLoader' => 'lib/Gateway/Provider/GatewayProviderLoader.php',
];
