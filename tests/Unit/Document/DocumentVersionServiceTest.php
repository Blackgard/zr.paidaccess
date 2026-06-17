<?php

namespace Zr\PaidAccess\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Document\DocumentVersionService;

final class DocumentVersionServiceTest extends TestCase
{
    public function testNormalizeVersionLabelStripsLeadingV(): void
    {
        $this->assertSame('1.01', DocumentVersionService::normalizeVersionLabel('v1.01'));
        $this->assertSame('2', DocumentVersionService::normalizeVersionLabel(' V2 '));
    }

    public function testFormatVersionLabelAddsPrefix(): void
    {
        $this->assertSame('v1.01', DocumentVersionService::formatVersionLabel('1.01'));
        $this->assertSame('v2', DocumentVersionService::formatVersionLabel('v2'));
    }

    public function testValidateVersionLabelRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentVersionService::validateVersionLabel('beta-1');
    }

    public function testGetSuggestedVersionStartsFromOneWhenNoDocument(): void
    {
        $this->assertSame('1', DocumentVersionService::getSuggestedVersion(0));
    }

    public function testGetSuggestedVersionIncrementsInteger(): void
    {
        $this->assertSame('2', DocumentVersionService::getSuggestedVersionFromLabel('1'));
        $this->assertSame('11', DocumentVersionService::getSuggestedVersionFromLabel('10'));
    }

    public function testGetSuggestedVersionIncrementsMinorPart(): void
    {
        $this->assertSame('1.02', DocumentVersionService::getSuggestedVersionFromLabel('1.01'));
        $this->assertSame('2.10', DocumentVersionService::getSuggestedVersionFromLabel('2.09'));
    }
}
