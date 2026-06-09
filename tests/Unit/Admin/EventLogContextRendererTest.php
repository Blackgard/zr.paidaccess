<?php

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\EventLogContextRenderer;

class EventLogContextRendererTest extends TestCase
{
    public function testRendersEmptyContextAsDash(): void
    {
        $html = EventLogContextRenderer::render('webhook_received', '');

        $this->assertStringContainsString('zr-paidaccess-muted', $html);
    }

    public function testRendersWebhookSummaryAndDetails(): void
    {
        $context = json_encode([
            'source' => 'webhook',
            'gatewayId' => 3,
            'orderId' => 'PA-6-2026-06-08',
            'bankStatus' => 'AUTHORIZED',
            'requestUrl' => 'https://example.test/webhook.php?id=3',
        ], JSON_UNESCAPED_UNICODE);

        $html = EventLogContextRenderer::render('webhook_received', $context);

        $this->assertStringContainsString('PA-6-2026-06-08', $html);
        $this->assertStringContainsString('AUTHORIZED', $html);
        $this->assertStringContainsString('zr-paidaccess-audit-context__facts', $html);
        $this->assertStringContainsString('Полный контекст', $html);
        $this->assertStringContainsString('zr-paidaccess-audit-context__json', $html);
    }
}
