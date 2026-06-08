<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Enum\PaymentStatus;

final class StatusBadgeRendererTest extends TestCase
{
    public function testRenderPaymentStatusCancelled(): void
    {
        $html = StatusBadgeRenderer::renderPaymentStatus(PaymentStatus::CANCELLED);

        $this->assertStringContainsString('status-badge', $html);
        $this->assertStringContainsString('status-warning', $html);
        $this->assertStringContainsString('Отмена', $html);
    }

    public function testRenderPaymentStatusLegacyAuthorized(): void
    {
        $html = StatusBadgeRenderer::renderPaymentStatus(PaymentStatus::AUTHORIZED);

        $this->assertStringContainsString('Авторизован', $html);
        $this->assertStringContainsString('status-info', $html);
    }
}
