<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Log;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tools\Logger;

final class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
    }

    public function testSanitizeParamsMasksSecrets(): void
    {
        $sanitized = Logger::sanitizeParams([
            'TerminalKey' => '1234567890',
            'Token' => 'secret-token',
            'Password' => 'pwd',
            'Amount' => 1000,
            'Receipt' => [
                'Email' => 'user@example.com',
            ],
        ]);

        $this->assertSame('1234***90', $sanitized['TerminalKey']);
        $this->assertSame('***', $sanitized['Token']);
        $this->assertSame('***', $sanitized['Password']);
        $this->assertSame(1000, $sanitized['Amount']);
        $this->assertSame('user@example.com', $sanitized['Receipt']['Email']);
    }

    public function testIsEnabledWhenLoggingActive(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_LOGGING_ACTIVE . '_s1',
            'Y',
            's1'
        );

        $this->assertTrue(Logger::isEnabled('s1'));
    }

    public function testIsDisabledWhenLoggingInactive(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_LOGGING_ACTIVE . '_s1',
            'N',
            's1'
        );

        $this->assertFalse(Logger::isEnabled('s1'));
    }
}
