<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Core;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PaidAccessCore;

final class PaidAccessCoreSiteContextTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
    }

    public function testDefaultPaymentDescriptionUsesSiteNamePlaceholder(): void
    {
        $description = PaidAccessCore::getPaymentDescription('s1');

        $this->assertSame('Ежемесячный взнос подписки — Сайт', $description);
    }

    public function testCustomPaymentDescriptionReplacesSiteName(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_PAYMENT_DESCRIPTION . '_s1',
            'Подписка {SITE_NAME}',
            's1'
        );

        $this->assertSame('Подписка Сайт', PaidAccessCore::getPaymentDescription('s1'));
    }

    public function testEnrichMailFieldsAddsSiteName(): void
    {
        $fields = PaidAccessCore::enrichMailFields(['EMAIL' => 'admin@example.com'], 's1');

        $this->assertSame('admin@example.com', $fields['EMAIL']);
        $this->assertSame('Сайт', $fields['SITE_NAME']);
    }
}
