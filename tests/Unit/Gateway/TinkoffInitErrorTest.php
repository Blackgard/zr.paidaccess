<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffInitError;

final class TinkoffInitErrorTest extends TestCase
{
    public function testDetectsDuplicateOrderIdByErrorCode(): void
    {
        $raw = json_encode([
            'Success' => false,
            'ErrorCode' => '8',
            'Message' => 'Неверный статус транзакции.',
            'Details' => 'Заказ с таким order_id уже существует.',
        ], JSON_UNESCAPED_UNICODE);

        $this->assertTrue(TinkoffInitError::isDuplicateOrderIdError($raw));
    }

    public function testDetectsDuplicateOrderIdFromInitPaymentResult(): void
    {
        $raw = json_encode([
            'Success' => false,
            'ErrorCode' => '8',
            'Message' => 'Неверный статус транзакции.',
            'Details' => 'Заказ с таким order_id уже существует.',
        ], JSON_UNESCAPED_UNICODE);
        $result = InitPaymentResult::fail(
            'Неверный статус транзакции. Заказ с таким order_id уже существует.',
            $raw
        );

        $this->assertTrue(TinkoffInitError::isDuplicateOrderIdError($result));
    }

    public function testIgnoresOtherErrors(): void
    {
        $raw = json_encode([
            'Success' => false,
            'ErrorCode' => '7',
            'Message' => 'Ошибка',
        ], JSON_UNESCAPED_UNICODE);

        $this->assertFalse(TinkoffInitError::isDuplicateOrderIdError($raw));
    }
}
