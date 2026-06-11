<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Options\ModuleOptionsProvider;
use Zr\PaidAccess\Options\ModuleOptionsStructure;
use Zr\PaidAccess\PaidAccessCore;

final class ModuleOptionsStructureTest extends TestCase
{
    public function testGroupsOrderAndIds(): void
    {
        $groups = ModuleOptionsStructure::getGroups();

        $this->assertSame(
            ['general', 'billing', 'access', 'payment_tariff', 'payment_flow', 'user_messages', 'notifications', 'logging'],
            array_column($groups, 'ID')
        );
    }

    public function testPaymentPageErrorTextIsInUserMessagesGroup(): void
    {
        $groups = ModuleOptionsStructure::getGroups();
        $messagesGroup = null;

        foreach ($groups as $group) {
            if ($group['ID'] === 'user_messages') {
                $messagesGroup = $group;
                break;
            }
        }

        $this->assertNotNull($messagesGroup);
        $this->assertArrayHasKey(PaidAccessCore::OPTION_PAYMENT_PAGE_ERROR_TEXT, $messagesGroup['OPTIONS']);
        $this->assertArrayNotHasKey(
            PaidAccessCore::OPTION_PAYMENT_PAGE_ERROR_TEXT,
            $this->findGroupOptions($groups, 'payment_flow')
        );
    }

    public function testUserMessageRegistryMatchesStructure(): void
    {
        $codes = ModuleOptionsProvider::getUserMessageOptionCodes();
        $built = ModuleOptionsProvider::buildUserMessageOptions();

        foreach ($codes as $code) {
            $this->assertArrayHasKey($code, $built, 'Missing user message field: ' . $code);
        }
    }

    /**
     * @param list<array{ID: string, OPTIONS: array<string, mixed>}> $groups
     *
     * @return array<string, mixed>
     */
    private function findGroupOptions(array $groups, string $id): array
    {
        foreach ($groups as $group) {
            if ($group['ID'] === $id) {
                return $group['OPTIONS'];
            }
        }

        return [];
    }
}
