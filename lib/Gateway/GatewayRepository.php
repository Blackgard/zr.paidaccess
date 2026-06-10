<?php

namespace Zr\PaidAccess\Gateway;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\GatewayTable;

class GatewayRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public static function getById($id)
    {
        $row = GatewayTable::getByPrimary((int)$id)->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Активный шлюз по умолчанию для сайта.
     *
     * @return array<string, mixed>|null
     */
    public static function getDefaultForSite($siteId = null)
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        // Приоритет: default для сайта → default для всех → любой активный для сайта → любой активный для всех.
        // Нельзя писать '=SITE_ID' => $siteId и '=SITE_ID' => '' в одном массиве — второй ключ затирает первый.
        $candidates = [
            ['=IS_DEFAULT' => 'Y', '=SITE_ID' => $siteId],
            ['=IS_DEFAULT' => 'Y', '=SITE_ID' => ''],
            ['=SITE_ID' => $siteId],
            ['=SITE_ID' => ''],
        ];

        foreach ($candidates as $siteFilter) {
            $row = GatewayTable::getList([
                'filter' => array_merge([
                    '=ACTIVE' => 'Y',
                    '=IS_TEST' => 'N',
                ], $siteFilter),
                'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
                'limit' => 1,
            ])->fetch();

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public static function isConfigured($siteId = null)
    {
        return self::getConfigurationError($siteId) === null;
    }

    /**
     * Причина, по которой шлюз недоступен на фронте (null — всё ок).
     */
    public static function getConfigurationError($siteId = null)
    {
        $gateway = self::getDefaultForSite($siteId);
        if (!$gateway) {
            return 'Платёжный шлюз не найден. Создайте активный шлюз в админке и отметьте «Использовать по умолчанию».';
        }

        try {
            GatewayFactory::createFromRow($gateway);

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getListForAdmin($siteId = null)
    {
        $filter = [];
        if ($siteId !== null && $siteId !== '') {
            $siteId = PaidAccessCore::normalizeSiteId($siteId);
            $filter = [
                [
                    'LOGIC' => 'OR',
                    ['=SITE_ID' => $siteId],
                    ['=SITE_ID' => ''],
                ],
            ];
        }

        $result = GatewayTable::getList([
            'filter' => $filter,
            'order' => ['IS_DEFAULT' => 'DESC', 'SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        $rows = [];
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $fields NAME, PROVIDER, SITE_ID, ACTIVE, IS_DEFAULT, OPTIONS (array|string)
     */
    public static function add(array $fields)
    {
        $now = new DateTime();
        $provider = (string)$fields['PROVIDER'];
        $isTest = ($fields['IS_TEST'] ?? 'N') === 'Y';
        $options = self::encodeOptions(
            self::syncTestGatewayOptions($fields['OPTIONS'], $provider, $isTest),
            $provider
        );

        $data = [
            'NAME' => (string)$fields['NAME'],
            'PROVIDER' => $provider,
            'SITE_ID' => (string)($fields['SITE_ID'] ?? ''),
            'ACTIVE' => ($fields['ACTIVE'] ?? 'Y') === 'Y' ? 'Y' : 'N',
            'IS_DEFAULT' => ($fields['IS_DEFAULT'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'IS_TEST' => $isTest ? 'Y' : 'N',
            'TEST_PASSED' => ($fields['TEST_PASSED'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'OPTIONS' => $options,
            'SORT' => (int)($fields['SORT'] ?? 500),
            'DATE_CREATE' => $now,
            'DATE_UPDATE' => $now,
        ];

        if ($isTest) {
            $data['IS_DEFAULT'] = 'N';
        }

        $result = GatewayTable::add($data);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        $id = (int)$result->getId();
        if ($data['IS_DEFAULT'] === 'Y') {
            self::resetDefaultExcept($id, $data['SITE_ID']);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function update($id, array $fields)
    {
        $id = (int)$id;
        $existing = self::getById($id);
        if (!$existing) {
            throw new \RuntimeException('Шлюз не найден');
        }

        $provider = isset($fields['PROVIDER']) ? (string)$fields['PROVIDER'] : (string)$existing['PROVIDER'];
        $data = ['DATE_UPDATE' => new DateTime()];

        foreach (['NAME', 'PROVIDER', 'SITE_ID', 'SORT', 'TEST_PASSED_AT', 'TEST_MODULE_PAYMENT_ID'] as $key) {
            if (array_key_exists($key, $fields)) {
                $data[$key] = $fields[$key];
            }
        }

        if (array_key_exists('ACTIVE', $fields)) {
            $data['ACTIVE'] = $fields['ACTIVE'] === 'Y' ? 'Y' : 'N';
        }

        if (array_key_exists('IS_TEST', $fields)) {
            $data['IS_TEST'] = $fields['IS_TEST'] === 'Y' ? 'Y' : 'N';
        }

        $isTest = ($data['IS_TEST'] ?? $existing['IS_TEST'] ?? 'N') === 'Y';

        if (array_key_exists('IS_DEFAULT', $fields)) {
            $data['IS_DEFAULT'] = $fields['IS_DEFAULT'] === 'Y' ? 'Y' : 'N';
        }

        if ($isTest) {
            $data['IS_DEFAULT'] = 'N';
        }

        if (array_key_exists('TEST_PASSED', $fields)) {
            $data['TEST_PASSED'] = $fields['TEST_PASSED'] === 'Y' ? 'Y' : 'N';
        }

        if (array_key_exists('OPTIONS', $fields)) {
            $data['OPTIONS'] = self::encodeOptions(
                self::syncTestGatewayOptions($fields['OPTIONS'], $provider, $isTest),
                $provider
            );
        } elseif ($isTest && $provider === 'tinkoff') {
            $currentOptions = self::getOptionsForGateway($existing);
            $data['OPTIONS'] = self::encodeOptions(
                self::syncTestGatewayOptions($currentOptions, $provider, true),
                $provider
            );
        }

        $result = GatewayTable::update($id, $data);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        if (($data['IS_DEFAULT'] ?? '') === 'Y') {
            $siteId = isset($data['SITE_ID']) ? (string)$data['SITE_ID'] : (string)$existing['SITE_ID'];
            self::resetDefaultExcept($id, $siteId);
        }
    }

    public static function delete($id)
    {
        $result = GatewayTable::delete((int)$id);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @param array|string $options
     */
    public static function encodeOptions($options, $provider)
    {
        if (is_string($options)) {
            return $options;
        }

        if (!is_array($options)) {
            $options = [];
        }

        $defaults = GatewayProviderRegistry::getDefaultOptionsForProvider($provider);
        $merged = array_merge($defaults, $options);

        return Json::encode($merged, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeOptions($optionsJson)
    {
        if ($optionsJson === '' || $optionsJson === null) {
            return [];
        }

        try {
            $decoded = Json::decode($optionsJson);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getOptionsForGateway(array $gatewayRow)
    {
        $options = self::decodeOptions($gatewayRow['OPTIONS'] ?? '');
        $defaults = GatewayProviderRegistry::getDefaultOptionsForProvider((string)$gatewayRow['PROVIDER']);

        return array_merge($defaults, $options);
    }

    public static function markTestPassed(int $gatewayId, int $modulePaymentId): bool
    {
        $gateway = self::getById($gatewayId);
        if (!$gateway || ($gateway['IS_TEST'] ?? 'N') !== 'Y') {
            return false;
        }

        if (($gateway['TEST_PASSED'] ?? 'N') === 'Y') {
            return true;
        }

        self::update($gatewayId, [
            'TEST_PASSED' => 'Y',
            'TEST_PASSED_AT' => new DateTime(),
            'TEST_MODULE_PAYMENT_ID' => $modulePaymentId,
        ]);

        return true;
    }

    /**
     * Есть ли успешно пройденный тестовый шлюз (для подсказок в админке).
     */
    public static function hasPassedTestGateway($siteId = null): bool
    {
        $filter = [
            '=IS_TEST' => 'Y',
            '=TEST_PASSED' => 'Y',
        ];

        if ($siteId !== null && $siteId !== '') {
            $siteId = PaidAccessCore::normalizeSiteId($siteId);
            $filter[] = [
                'LOGIC' => 'OR',
                ['=SITE_ID' => $siteId],
                ['=SITE_ID' => ''],
            ];
        }

        $row = GatewayTable::getList([
            'filter' => $filter,
            'limit' => 1,
        ])->fetch();

        return (bool)$row;
    }

    /**
     * @param array|string $options
     * @return array<string, mixed>
     */
    private static function syncTestGatewayOptions($options, string $provider, bool $isTest): array
    {
        if (!is_array($options)) {
            $options = self::decodeOptions($options);
        }

        if ($isTest && $provider === 'tinkoff') {
            $options['test_mode'] = 'Y';
        }

        return $options;
    }

    private static function resetDefaultExcept($gatewayId, $siteId)
    {
        $gatewayId = (int)$gatewayId;
        $result = GatewayTable::getList([
            'select' => ['ID'],
            'filter' => [
                '!=ID' => $gatewayId,
                '=IS_DEFAULT' => 'Y',
                '=SITE_ID' => (string)$siteId,
            ],
        ]);

        while ($row = $result->fetch()) {
            GatewayTable::update((int)$row['ID'], ['IS_DEFAULT' => 'N', 'DATE_UPDATE' => new DateTime()]);
        }
    }
}
