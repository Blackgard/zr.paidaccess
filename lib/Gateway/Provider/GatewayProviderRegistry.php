<?php

namespace Zr\PaidAccess\Gateway\Provider;

use Zr\PaidAccess\Gateway\Contract\GatewayProviderInterface;
use Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface;

/**
 * Фасад над GatewayProviderLoader (автообнаружение провайдеров в Providers/).
 */
class GatewayProviderRegistry
{
    public static function getProviderSelect()
    {
        $list = ['' => '— выберите провайдера —'];
        foreach (self::getAll() as $code => $provider) {
            $list[$code] = $provider->getTitle();
        }

        return $list;
    }

    public static function hasProvider($code)
    {
        return GatewayProviderLoader::has($code);
    }

    public static function getProvider($code)
    {
        return GatewayProviderLoader::get($code);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getFieldsForProvider($provider)
    {
        $instance = self::getProvider($provider);

        return $instance ? $instance->getAdminFields() : [];
    }

    public static function getDefaultOptionsForProvider($provider)
    {
        $instance = self::getProvider($provider);

        return $instance ? $instance->getDefaultOptions() : [];
    }

    /**
     * @param array<string, mixed> $gatewayRow
     */
    public static function createGateway(array $gatewayRow): PaymentGatewayInterface
    {
        $code = (string)($gatewayRow['PROVIDER'] ?? '');
        $provider = self::getProvider($code);
        if (!$provider) {
            throw new \RuntimeException('Провайдер не найден: ' . $code);
        }

        return $provider->createGateway($gatewayRow);
    }

    /**
     * @return array<string, GatewayProviderInterface>
     */
    public static function getAll()
    {
        return GatewayProviderLoader::getAll();
    }

    /**
     * Список провайдеров для админки: code => ['title' => ...].
     *
     * @return array<string, array{title: string}>
     */
    public static function getProviders()
    {
        $list = [];
        foreach (self::getAll() as $code => $provider) {
            $list[$code] = ['title' => $provider->getTitle()];
        }

        return $list;
    }
}
