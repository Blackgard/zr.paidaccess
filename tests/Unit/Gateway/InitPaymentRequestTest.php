<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;

final class InitPaymentRequestTest extends TestCase
{
    /**
     * @dataProvider amountProvider
     */
    public function testGetAmountKopecks(float $amount, int $expected): void
    {
        $request = new InitPaymentRequest('ORDER-1', $amount, 'RUB', 'Test', 1);

        $this->assertSame($expected, $request->getAmountKopecks());
    }

    public function amountProvider(): array
    {
        return [
            'integer rubles' => [1000.0, 100000],
            'with kopecks' => [1000.50, 100050],
            'rounding' => [10.555, 1056],
            'zero' => [0.0, 0],
        ];
    }

    public function testPaymentWidgetModeHelpers(): void
    {
        $request = new InitPaymentRequest('ORDER-1', 100.0, 'RUB', 'Test', 1);
        $request->paymentWidgetMode = 'payment_button';

        $this->assertTrue($request->isPaymentButtonWidgetMode());

        $request->paymentWidgetMode = 'qr_sbp';
        $this->assertFalse($request->isPaymentButtonWidgetMode());
    }
}
