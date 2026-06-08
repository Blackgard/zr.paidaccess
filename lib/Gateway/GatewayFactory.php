<?php

namespace Zr\PaidAccess\Gateway;

use Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;

class GatewayFactory
{
    public static function create($siteId = null)
    {
        $gateway = GatewayRepository::getDefaultForSite($siteId);
        if (!$gateway) {
            throw new \RuntimeException('Платёжный шлюз не настроен. Создайте шлюз в админке: Платёжные шлюзы.');
        }

        return self::createFromRow($gateway);
    }

    public static function createById($gatewayId)
    {
        $gateway = GatewayRepository::getById($gatewayId);
        if (!$gateway) {
            throw new \RuntimeException('Платёжный шлюз #' . (int)$gatewayId . ' не найден');
        }

        return self::createFromRow($gateway);
    }

    /**
     * @param array<string, mixed> $gatewayRow
     */
    public static function createFromRow(array $gatewayRow, bool $allowInactive = false): PaymentGatewayInterface
    {
        if (!$allowInactive && ($gatewayRow['ACTIVE'] ?? 'N') !== 'Y') {
            throw new \RuntimeException('Платёжный шлюз отключён: ' . ($gatewayRow['NAME'] ?? ''));
        }

        return GatewayProviderRegistry::createGateway($gatewayRow);
    }

    public static function isConfigured($siteId = null)
    {
        return GatewayRepository::isConfigured($siteId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getDefaultGatewayRow($siteId = null)
    {
        return GatewayRepository::getDefaultForSite($siteId);
    }
}
