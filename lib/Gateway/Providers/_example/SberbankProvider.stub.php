<?php

/**
 * Шаблон провайдера. Скопируйте папку _example → Sberbank и переименуйте классы.
 * Файл .stub.php не подключается автоматически.
 */

namespace Zr\PaidAccess\Gateway\Providers\Sberbank;

use Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface;
use Zr\PaidAccess\Gateway\Provider\AbstractGatewayProvider;

class SberbankProvider extends AbstractGatewayProvider
{
    public function getCode(): string
    {
        return 'sberbank';
    }

    public function getTitle(): string
    {
        return 'Сбербанк';
    }

    public function getAdminFields()
    {
        return [
            [
                'code' => 'api_key',
                'title' => 'API Key',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    public function createGateway(array $gatewayRow): PaymentGatewayInterface
    {
        return new SberbankGateway($gatewayRow);
    }
}
