<?php

namespace Zr\PaidAccess\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Document\DocumentConsentService;
use Zr\PaidAccess\Document\DocumentVersionService;

final class DocumentConsentServiceTest extends TestCase
{
    public function testIsVersionAcceptedReturnsTrueWhenPresent(): void
    {
        $this->assertTrue(DocumentConsentService::isVersionAccepted(5, [3, 5, 7]));
    }

    public function testIsVersionAcceptedReturnsFalseWhenMissing(): void
    {
        $this->assertFalse(DocumentConsentService::isVersionAccepted(5, [3, 7]));
    }

    public function testBuildPendingItemContainsVersionMetadata(): void
    {
        $item = DocumentConsentService::buildPendingItem(
            [
                'ID' => 10,
                'TITLE' => 'Устав',
                'CODE' => 'charter',
            ],
            [
                'ID' => 20,
                'VERSION' => '1.02',
                'BODY_HTML' => '<p>Текст</p>',
            ]
        );

        $this->assertSame(10, $item['DOCUMENT_ID']);
        $this->assertSame(20, $item['VERSION_ID']);
        $this->assertSame('1.02', $item['VERSION']);
        $this->assertSame('Устав', $item['TITLE']);
        $this->assertSame('charter', $item['CODE']);
        $this->assertSame('<p>Текст</p>', $item['BODY_HTML']);
    }

    public function testResolveFileUrlReturnsNullWithoutFile(): void
    {
        $this->assertNull(DocumentVersionService::resolveFileUrl(['FILE_ID' => 0]));
    }
}
