<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffGateway;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffInitDiagnosticService;
use Zr\PaidAccess\Tables\GatewayTable;

/**
 * Admin/CLI-обёртка диагностики Init T-Bank.
 */
final class TinkoffInitDiagnosticAdminService
{
    public const DEFAULT_TIMEOUT_SECONDS = TinkoffInitDiagnosticService::DEFAULT_TIMEOUT_SECONDS;

    /**
     * @return array<int, string> gatewayId => label
     */
    public static function getTinkoffGatewayOptions(): array
    {
        $options = [];
        $result = GatewayTable::getList([
            'filter' => ['=PROVIDER' => TinkoffGateway::CODE],
            'order' => ['ID' => 'ASC'],
            'select' => ['ID', 'NAME', 'ACTIVE', 'IS_TEST'],
        ]);

        while ($row = $result->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            $label = '#' . (int)$row['ID'] . ' ' . (string)$row['NAME'];
            if ((string)$row['IS_TEST'] === 'Y') {
                $label .= ' (тест)';
            }
            if ((string)$row['ACTIVE'] !== 'Y') {
                $label .= ' [неактивен]';
            }

            $options[(int)$row['ID']] = $label;
        }

        return $options;
    }

    /**
     * @param array{
     *     runInit?: bool,
     *     runTraceroute?: bool,
     *     email?: string,
     *     timeoutSeconds?: int
     * } $options
     * @return array<string, mixed>
     */
    public static function run(int $gatewayId, ?string $siteId = null, array $options = []): array
    {
        return TinkoffInitDiagnosticService::run($gatewayId, $siteId, $options);
    }
}
