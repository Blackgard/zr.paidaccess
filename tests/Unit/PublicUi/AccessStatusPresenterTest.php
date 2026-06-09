<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\PublicUi;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\PublicUi\AccessStatusPresenter;

final class AccessStatusPresenterTest extends TestCase
{
    public function testGetPublicLabelReturnsHumanReadableText(): void
    {
        $label = AccessStatusPresenter::getPublicLabel(SubscriberAdminService::ACCESS_DEBT);

        $this->assertSame('Просрочена оплата', $label);
    }

    /**
     * @dataProvider debtHighlightProvider
     */
    public function testIsDebtHighlight(string $status, bool $expected): void
    {
        $this->assertSame($expected, AccessStatusPresenter::isDebtHighlight($status));
    }

    public function debtHighlightProvider(): array
    {
        return [
            'debt' => [SubscriberAdminService::ACCESS_DEBT, true],
            'failed' => [SubscriberAdminService::ACCESS_FAILED, true],
            'expired' => [SubscriberAdminService::ACCESS_EXPIRED, true],
            'unpaid' => [SubscriberAdminService::ACCESS_UNPAID, true],
            'active' => [SubscriberAdminService::ACCESS_ACTIVE, false],
            'pending' => [SubscriberAdminService::ACCESS_PENDING, false],
        ];
    }

    public function testSortPriorityPutsDebtFirst(): void
    {
        $debt = AccessStatusPresenter::getSortPriority(SubscriberAdminService::ACCESS_DEBT);
        $active = AccessStatusPresenter::getSortPriority(SubscriberAdminService::ACCESS_ACTIVE);

        $this->assertLessThan($active, $debt);
    }

    public function testRowCssClassForDebt(): void
    {
        $this->assertSame(
            'zr-paidaccess-row--debt',
            AccessStatusPresenter::getRowCssClass(SubscriberAdminService::ACCESS_DEBT)
        );
        $this->assertSame(
            '',
            AccessStatusPresenter::getRowCssClass(SubscriberAdminService::ACCESS_ACTIVE)
        );
    }
}
