<?php

namespace Zr\PaidAccess\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Document\RequiredDocumentService;

final class RequiredDocumentServiceTest extends TestCase
{
    public function testBuildPublicItemContainsVersionMetadata(): void
    {
        $item = RequiredDocumentService::buildPublicItem(
            [
                'ID' => 1,
                'CODE' => 'user-agreement',
                'TITLE' => 'Пользовательское соглашение',
                'SORT' => 100,
                'IS_REQUIRED' => 'Y',
            ],
            [
                'ID' => 10,
                'VERSION' => '1.01',
                'BODY_HTML' => '<p>Текст</p>',
            ]
        );

        $this->assertSame(1, $item['ID']);
        $this->assertSame('user-agreement', $item['CODE']);
        $this->assertSame('v1.01', $item['VERSION_LABEL']);
        $this->assertTrue($item['IS_REQUIRED']);
        $this->assertTrue($item['HAS_BODY']);
    }

    public function testResolvePublicUrlUsesFileUrlWhenPresent(): void
    {
        $item = [
            'FILE_URL' => '/upload/docs/agreement.pdf',
            'HAS_FILE' => true,
            'HAS_BODY' => false,
            'CODE' => 'user-agreement',
        ];

        $this->assertSame('/upload/docs/agreement.pdf', RequiredDocumentService::resolvePublicUrl($item));
    }

    public function testResolvePublicUrlUsesDetailTemplateForHtmlDocument(): void
    {
        $item = RequiredDocumentService::buildPublicItem(
            [
                'ID' => 2,
                'CODE' => 'policy',
                'TITLE' => 'Политика',
                'SORT' => 200,
                'IS_REQUIRED' => 'N',
            ],
            [
                'ID' => 20,
                'VERSION' => '2',
                'BODY_HTML' => '<p>Текст</p>',
            ]
        );

        $url = RequiredDocumentService::resolvePublicUrl($item, '/documents/#CODE#/');

        $this->assertSame('/documents/policy/', $url);
    }

    public function testResolvePublicUrlFallsBackToAnchorWithoutTemplate(): void
    {
        $item = RequiredDocumentService::buildPublicItem(
            [
                'ID' => 3,
                'CODE' => 'charter',
                'TITLE' => 'Устав',
                'SORT' => 300,
                'IS_REQUIRED' => 'Y',
            ],
            [
                'ID' => 30,
                'VERSION' => '1',
                'BODY_HTML' => '<p>Устав</p>',
            ]
        );

        $this->assertSame('#zr-doc-charter', RequiredDocumentService::resolvePublicUrl($item));
    }

    public function testResolveDetailUrlReplacesPlaceholders(): void
    {
        $url = RequiredDocumentService::resolveDetailUrl('/docs/#ID#/#CODE#/#VERSION_ID#/', [
            'ID' => 5,
            'CODE' => 'rules',
            'VERSION_ID' => 9,
        ]);

        $this->assertSame('/docs/5/rules/9/', $url);
    }
}
