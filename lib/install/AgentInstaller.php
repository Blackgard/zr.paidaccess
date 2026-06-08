<?php

namespace Zr\PaidAccess\Install;

use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\BillingDebtAgent;
use Zr\PaidAccess\Subscription\SubscriptionReminderAgent;

class AgentInstaller
{
    public static function ensureAgents(): void
    {
        if (!\CModule::IncludeModule('main')) {
            return;
        }

        self::registerAgent(BillingDebtAgent::class . '::run();', 86400);
        self::registerAgent(SubscriptionReminderAgent::class . '::run();', 86400);
    }

    public static function uninstallAgents(): void
    {
        if (\CModule::IncludeModule('main')) {
            \CAgent::RemoveModuleAgents(PaidAccessCore::MODULE_ID);
        }
    }

    protected static function registerAgent(string $methodCall, int $interval): void
    {
        $agentFunction = '\\' . $methodCall;

        $existing = \CAgent::GetList(
            ['ID' => 'DESC'],
            [
                'MODULE_ID' => PaidAccessCore::MODULE_ID,
                'NAME' => $agentFunction,
            ]
        )->Fetch();

        if ($existing) {
            return;
        }

        \CAgent::AddAgent(
            $agentFunction,
            PaidAccessCore::MODULE_ID,
            'N',
            $interval,
            '',
            'Y',
            ConvertTimeStamp(time() + 3600, 'FULL'),
            30
        );
    }
}
