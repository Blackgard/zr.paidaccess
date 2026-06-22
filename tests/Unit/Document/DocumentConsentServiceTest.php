<?php

namespace Zr\PaidAccess\Tests\Unit\Document;

use Bitrix\Main\Config\Option;
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

    public function testMustOpenDocumentBeforeConsentReturnsFalseWhenOptionDisabled(): void
    {
        Option::set('zr.paidaccess', 'DOCUMENT_CONSENT_REQUIRE_OPEN_s1', 'N', 's1');

        $document = [
            'FILE_URL' => '/upload/doc.pdf',
            'BODY_HTML' => '',
        ];

        $this->assertFalse(DocumentConsentService::mustOpenDocumentBeforeConsent($document, 's1'));

        Option::set('zr.paidaccess', 'DOCUMENT_CONSENT_REQUIRE_OPEN_s1', 'Y', 's1');
    }

    public function testMustOpenDocumentBeforeConsentReturnsTrueForFileWhenOptionEnabled(): void
    {
        Option::set('zr.paidaccess', 'DOCUMENT_CONSENT_REQUIRE_OPEN_s1', 'Y', 's1');

        $document = [
            'FILE_URL' => '/upload/doc.pdf',
            'BODY_HTML' => '',
        ];

        $this->assertTrue(DocumentConsentService::mustOpenDocumentBeforeConsent($document, 's1'));
    }

    public function testMustOpenDocumentBeforeConsentReturnsFalseWithoutContent(): void
    {
        $document = [
            'FILE_URL' => '',
            'BODY_HTML' => '',
        ];

        $this->assertFalse(DocumentConsentService::mustOpenDocumentBeforeConsent($document, 's1'));
    }

    public function testResolveFileUrlReturnsNullWithoutFile(): void
    {
        $this->assertNull(DocumentVersionService::resolveFileUrl(['FILE_ID' => 0]));
    }
}
