<?php

namespace Zr\PaidAccess\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Utility\DocumentMigrationMapping;
use Zr\PaidAccess\Utility\DocumentMigrationValueResolver;

class DocumentMigrationValueResolverTest extends TestCase
{
    public function testResolveFieldValue(): void
    {
        $fields = ['NAME' => 'Устав', 'CODE' => 'charter'];
        $properties = [];

        $this->assertSame('Устав', DocumentMigrationValueResolver::resolve('field:NAME', $fields, $properties));
        $this->assertSame('charter', DocumentMigrationValueResolver::resolve('field:CODE', $fields, $properties));
        $this->assertSame('Y', DocumentMigrationValueResolver::resolve('const:Y', $fields, $properties));
    }

    public function testResolvePropertyValue(): void
    {
        $fields = [];
        $properties = [
            'FILE' => ['VALUE' => 101],
        ];

        $this->assertSame(101, DocumentMigrationValueResolver::resolve('property:FILE', $fields, $properties));
        $this->assertSame(101, DocumentMigrationValueResolver::resolveFileId(
            DocumentMigrationValueResolver::resolve('property:FILE', $fields, $properties)
        ));
    }

    public function testBuildDocumentCodeFromTitleWhenCodeMissing(): void
    {
        $code = DocumentMigrationValueResolver::buildDocumentCode('', 'Учредительный устав', 55);
        $this->assertNotSame('', $code);
        $this->assertSame('doc-55', DocumentMigrationValueResolver::buildDocumentCode('', '', 55));
    }

    public function testMappingValidationRequiresFileOrBody(): void
    {
        $errors = DocumentMigrationMapping::validate([
            'document.code' => 'field:CODE',
            'document.title' => 'field:NAME',
            'version.file' => '',
            'version.body_html' => '',
        ]);

        $this->assertNotEmpty($errors);
    }
}
