<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Web\Json;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;
use Zr\PaidAccess\Tables\GatewayTable;

class GatewayImportExportService
{
    public const FORMAT = 'zr.paidaccess.gateways';
    public const VERSION = 1;

    public const MODE_CREATE = 'create';
    public const MODE_UPSERT = 'upsert';
    public const MODE_SKIP_EXISTING = 'skip_existing';

    /**
     * @param int[] $ids Пустой массив — экспорт всех шлюзов.
     */
    public static function exportToJson(array $ids = []): string
    {
        $filter = [];
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids !== []) {
            $filter['@ID'] = $ids;
        }

        $gateways = [];
        $result = GatewayTable::getList([
            'filter' => $filter,
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $gateways[] = self::formatGatewayForExport($row);
        }

        $payload = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exportedAt' => date('c'),
            'gateways' => $gateways,
        ];

        return Json::encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: string[]}
     */
    public static function importFromJson(
        string $json,
        string $mode = self::MODE_UPSERT,
        bool $preserveTestState = false
    ): array {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $data = Json::decode($json);
        } catch (\Throwable $e) {
            $result['errors'][] = 'Некорректный JSON: ' . $e->getMessage();

            return $result;
        }

        if (!is_array($data)) {
            $result['errors'][] = 'Ожидается JSON-объект.';

            return $result;
        }

        if (($data['format'] ?? '') !== self::FORMAT) {
            $result['errors'][] = 'Неподдерживаемый формат файла (ожидается ' . self::FORMAT . ').';

            return $result;
        }

        $items = $data['gateways'] ?? null;
        if (!is_array($items) || $items === []) {
            $result['errors'][] = 'В файле нет записей gateways.';

            return $result;
        }

        if (!in_array($mode, [self::MODE_CREATE, self::MODE_UPSERT, self::MODE_SKIP_EXISTING], true)) {
            $mode = self::MODE_UPSERT;
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $result['errors'][] = 'Строка #' . ($index + 1) . ': ожидается объект.';
                continue;
            }

            try {
                $importResult = self::importGatewayItem($item, $mode, $preserveTestState);
                $result[$importResult]++;
            } catch (\Throwable $e) {
                $name = (string)($item['name'] ?? '');
                $label = $name !== '' ? '"' . $name . '"' : '#' . ($index + 1);
                $result['errors'][] = $label . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function formatGatewayForExport(array $row): array
    {
        $siteId = (string)($row['SITE_ID'] ?? '');
        $name = (string)($row['NAME'] ?? '');

        return [
            'sourceId' => (int)($row['ID'] ?? 0),
            'matchKey' => self::buildMatchKey((string)($row['PROVIDER'] ?? ''), $siteId, $name),
            'name' => $name,
            'provider' => (string)($row['PROVIDER'] ?? ''),
            'siteId' => $siteId,
            'active' => ($row['ACTIVE'] ?? 'N') === 'Y',
            'isDefault' => ($row['IS_DEFAULT'] ?? 'N') === 'Y',
            'isTest' => ($row['IS_TEST'] ?? 'N') === 'Y',
            'testPassed' => ($row['TEST_PASSED'] ?? 'N') === 'Y',
            'sort' => (int)($row['SORT'] ?? 500),
            'options' => GatewayRepository::getOptionsForGateway($row),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return 'created'|'updated'|'skipped'
     */
    protected static function importGatewayItem(array $item, string $mode, bool $preserveTestState): string
    {
        $name = trim((string)($item['name'] ?? ''));
        $provider = strtolower(trim((string)($item['provider'] ?? '')));

        if ($name === '') {
            throw new \RuntimeException('Не указано название (name).');
        }
        if ($provider === '') {
            throw new \RuntimeException('Не указан провайдер (provider).');
        }
        if (!isset(GatewayProviderRegistry::getProviders()[$provider])) {
            throw new \RuntimeException('Провайдер "' . $provider . '" не зарегистрирован в модуле.');
        }

        $siteId = (string)($item['siteId'] ?? $item['SITE_ID'] ?? '');
        $existing = self::findByMatchKey($provider, $siteId, $name);

        if ($existing && $mode === self::MODE_SKIP_EXISTING) {
            return 'skipped';
        }

        $fields = [
            'NAME' => $name,
            'PROVIDER' => $provider,
            'SITE_ID' => $siteId,
            'ACTIVE' => self::toYesNo($item['active'] ?? $item['ACTIVE'] ?? true),
            'IS_DEFAULT' => self::toYesNo($item['isDefault'] ?? $item['IS_DEFAULT'] ?? false),
            'IS_TEST' => self::toYesNo($item['isTest'] ?? $item['IS_TEST'] ?? false),
            'SORT' => (int)($item['sort'] ?? $item['SORT'] ?? 500),
            'OPTIONS' => self::normalizeOptions($item['options'] ?? $item['OPTIONS'] ?? []),
        ];

        if ($preserveTestState) {
            $fields['TEST_PASSED'] = self::toYesNo($item['testPassed'] ?? $item['TEST_PASSED'] ?? false);
        } else {
            $fields['TEST_PASSED'] = 'N';
        }

        if ($fields['IS_TEST'] === 'Y') {
            $fields['IS_DEFAULT'] = 'N';
        }

        if ($existing && $mode === self::MODE_UPSERT) {
            if (!$preserveTestState) {
                $fields['TEST_PASSED_AT'] = null;
                $fields['TEST_MODULE_PAYMENT_ID'] = null;
            }
            GatewayRepository::update((int)$existing['ID'], $fields);

            return 'updated';
        }

        if ($existing && $mode === self::MODE_CREATE) {
            throw new \RuntimeException(
                'Шлюз уже существует (provider + siteId + name). Используйте режим «обновлять существующие».'
            );
        }

        GatewayRepository::add($fields);

        return 'created';
    }

    /**
     * @param mixed $value
     */
    protected static function toYesNo($value): string
    {
        if ($value === true || $value === 'Y' || $value === 'y' || $value === 1 || $value === '1') {
            return 'Y';
        }

        return 'N';
    }

    /**
     * @param mixed $options
     * @return array<string, mixed>
     */
    protected static function normalizeOptions($options): array
    {
        if (is_string($options) && $options !== '') {
            return GatewayRepository::decodeOptions($options);
        }

        return is_array($options) ? $options : [];
    }

    protected static function buildMatchKey(string $provider, string $siteId, string $name): string
    {
        return strtolower($provider) . '|' . $siteId . '|' . mb_strtolower(trim($name));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function findByMatchKey(string $provider, string $siteId, string $name): ?array
    {
        $row = GatewayTable::getList([
            'filter' => [
                '=PROVIDER' => strtolower($provider),
                '=SITE_ID' => $siteId,
                '=NAME' => $name,
            ],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }
}
