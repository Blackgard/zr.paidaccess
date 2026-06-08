<?php

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\AuditContextRenderer;

class AuditContextRendererTest extends TestCase
{
    public function testRenderDeletedPaymentShowsSnapshot(): void
    {
        $oldValue = json_encode([
            'ID' => 15,
            'USER_ID' => 7,
            'STATUS' => 'paid',
            'AMOUNT' => 500.0,
            'CURRENCY' => 'RUB',
            'BILLING_PERIOD' => '2026-05',
            'ORDER_ID' => 'PA-15-2026-05',
            'GATEWAY_CODE' => 'manual',
            'DATE_PAID' => '2026-05-01 10:00:00',
        ], JSON_UNESCAPED_UNICODE);

        $html = AuditContextRenderer::render('payment', 'delete', $oldValue, null, 'ru');

        $this->assertStringContainsString('Удалённый платёж', $html);
        $this->assertStringContainsString('PA-15-2026-05', $html);
        $this->assertStringContainsString('Оплачен', $html);
        $this->assertStringContainsString('Полные данные', $html);
    }

    public function testRenderUpdateShowsChangedFields(): void
    {
        $oldValue = json_encode([
            'STATUS' => 'pending',
            'AMOUNT' => 500.0,
        ], JSON_UNESCAPED_UNICODE);
        $newValue = json_encode([
            'STATUS' => 'paid',
            'AMOUNT' => 500.0,
        ], JSON_UNESCAPED_UNICODE);

        $html = AuditContextRenderer::render('payment', 'update', $oldValue, $newValue, 'ru');

        $this->assertStringContainsString('Статус', $html);
        $this->assertStringContainsString('Ожидает оплаты', $html);
        $this->assertStringContainsString('Оплачен', $html);
    }

    public function testActionTitleTranslatesKnownActions(): void
    {
        $this->assertSame('Удаление', AuditContextRenderer::actionTitle('delete'));
        $this->assertSame('Создание', AuditContextRenderer::actionTitle('create'));
    }
}
