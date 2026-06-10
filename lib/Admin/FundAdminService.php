<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\Fund\FundBalanceService;
use Zr\PaidAccess\Fund\FundMovementRepository;
use Zr\PaidAccess\Fund\FundMovementService;
use Zr\PaidAccess\Fund\FundRepository;
use Zr\PaidAccess\Fund\FundService;
use Zr\PaidAccess\Log\AuditLogService;
use Zr\PaidAccess\PaidAccessCore;

class FundAdminService
{
    /**
     * @return array<string, string>
     */
    public static function getMovementTypeTitles(): array
    {
        return [
            FundMovementType::INCOME => 'Поступление',
            FundMovementType::EXPENSE => 'Списание',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getMovementSourceTitles(): array
    {
        return [
            FundMovementSource::PAYMENT => 'Платёж',
            FundMovementSource::REFUND => 'Возврат',
            FundMovementSource::ADMIN => 'Админка',
            FundMovementSource::SYSTEM => 'Система',
        ];
    }

    public static function getMovementTypeTitle(string $type): string
    {
        return self::getMovementTypeTitles()[$type] ?? $type;
    }

    public static function getMovementSourceTitle(string $source): string
    {
        return self::getMovementSourceTitles()[$source] ?? $source;
    }

    /**
     * @param array<string, mixed> $filterData
     * @return array<string, mixed>
     */
    public static function buildFundFilter(array $filterData): array
    {
        $filter = [];

        if (!empty($filterData['SITE_ID'])) {
            $filter['=SITE_ID'] = PaidAccessCore::normalizeSiteId((string)$filterData['SITE_ID']);
        }
        if (!empty($filterData['CODE'])) {
            $filter['%CODE'] = (string)$filterData['CODE'];
        }
        if (!empty($filterData['NAME'])) {
            $filter['%NAME'] = (string)$filterData['NAME'];
        }
        if (!empty($filterData['ACTIVE'])) {
            $filter['=ACTIVE'] = (string)$filterData['ACTIVE'];
        }

        return $filter;
    }

    /**
     * @param array<string, mixed> $filterData
     * @return array<string, mixed>
     */
    public static function buildMovementFilter(int $fundId, array $filterData): array
    {
        $filter = ['=FUND_ID' => $fundId];

        if (!empty($filterData['TYPE'])) {
            $filter['=TYPE'] = (string)$filterData['TYPE'];
        }
        if (!empty($filterData['SOURCE'])) {
            $filter['=SOURCE'] = (string)$filterData['SOURCE'];
        }

        return $filter;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    public static function validateFundForm(array $data, bool $isNew): array
    {
        $errors = [];

        $siteId = trim((string)($data['SITE_ID'] ?? ''));
        if ($siteId === '') {
            $errors[] = 'Укажите сайт (SITE_ID)';
        }

        $name = trim((string)($data['NAME'] ?? ''));
        if ($name === '') {
            $errors[] = 'Укажите название фонда';
        }

        $code = trim((string)($data['CODE'] ?? ''));
        if ($code === '') {
            $errors[] = 'Укажите код фонда';
        } elseif (!preg_match('/^[a-z0-9_\-]+$/i', $code)) {
            $errors[] = 'Код фонда: только латиница, цифры, _ и -';
        }

        if ($isNew && $siteId !== '' && $code !== '' && preg_match('/^[a-z0-9_\-]+$/i', $code)) {
            $existing = FundRepository::findBySiteAndCode($siteId, $code);
            if ($existing) {
                $errors[] = 'Фонд с таким кодом уже существует на этом сайте';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function saveFund(int $id, array $data): int
    {
        $isNew = $id <= 0;
        $errors = self::validateFundForm($data, $isNew);
        if ($errors !== []) {
            throw new \RuntimeException(implode('; ', $errors));
        }

        $siteId = PaidAccessCore::normalizeSiteId((string)$data['SITE_ID']);
        $isDefault = (($data['IS_DEFAULT'] ?? 'N') === 'Y') ? 'Y' : 'N';
        $active = (($data['ACTIVE'] ?? 'Y') === 'Y') ? 'Y' : 'N';

        $fields = [
            'SITE_ID' => $siteId,
            'CODE' => trim((string)$data['CODE']),
            'NAME' => trim((string)$data['NAME']),
            'CURRENCY' => 'RUB',
            'IS_DEFAULT' => $isDefault,
            'ACTIVE' => $active,
        ];

        if ($isNew) {
            $id = FundRepository::create($fields);
            AuditLogService::log('fund', $id, 'create', null, AuditLogService::encodeSnapshot($fields), 'Создание фонда');

            return $id;
        }

        $existing = FundRepository::getById($id);
        if (!$existing) {
            throw new \RuntimeException('Фонд не найден');
        }

        $updateFields = [
            'NAME' => $fields['NAME'],
            'IS_DEFAULT' => $fields['IS_DEFAULT'],
            'ACTIVE' => $fields['ACTIVE'],
        ];

        FundRepository::update($id, $updateFields);
        $fields = array_merge($existing, $updateFields);
        $updated = FundRepository::getById($id);
        AuditLogService::log(
            'fund',
            $id,
            'update',
            AuditLogService::encodeSnapshot($existing),
            AuditLogService::encodeSnapshot($updated),
            'Изменение фонда'
        );

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createManualMovement(int $fundId, array $data, int $adminUserId): int
    {
        if ($fundId <= 0) {
            throw new \InvalidArgumentException('Укажите фонд');
        }

        $type = (string)($data['TYPE'] ?? '');
        $amount = (float)str_replace(',', '.', (string)($data['AMOUNT'] ?? '0'));
        $description = trim((string)($data['DESCRIPTION'] ?? ''));
        $externalRef = trim((string)($data['EXTERNAL_REF'] ?? ''));

        $context = [
            'adminUserId' => $adminUserId,
            'externalRef' => $externalRef,
            'source' => FundMovementSource::ADMIN,
        ];

        if ($type === FundMovementType::EXPENSE) {
            return FundMovementService::recordExpense($fundId, $amount, $description, $context);
        }

        if ($type === FundMovementType::INCOME) {
            return FundMovementService::recordManualIncome($fundId, $amount, $description, $context);
        }

        throw new \InvalidArgumentException('Выберите тип движения: поступление или списание');
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function formatMovementDate($row): string
    {
        $dateCreate = $row['DATE_CREATE'] ?? null;
        if ($dateCreate instanceof DateTime) {
            return $dateCreate->format('d.m.Y H:i');
        }
        if ($dateCreate instanceof \DateTimeInterface) {
            return $dateCreate->format('d.m.Y H:i');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function formatMovementCounterparty(array $row): string
    {
        $type = (string)($row['TYPE'] ?? '');
        $source = (string)($row['SOURCE'] ?? '');

        if ($type === FundMovementType::INCOME && $source === FundMovementSource::PAYMENT) {
            $name = SubscriberAdminService::formatUserName([
                'NAME' => (string)($row['USER_NAME'] ?? ''),
                'LAST_NAME' => (string)($row['USER_LAST_NAME'] ?? ''),
            ]);
            if ($name !== '') {
                return $name;
            }
        }

        return (string)($row['DESCRIPTION'] ?? '');
    }

    public static function formatMovementReference(array $row): string
    {
        $orderId = trim((string)($row['ORDER_ID'] ?? ''));
        if ($orderId !== '') {
            return $orderId;
        }

        $externalRef = trim((string)($row['EXTERNAL_REF'] ?? ''));
        if ($externalRef !== '') {
            return $externalRef;
        }

        $paymentId = (int)($row['PAYMENT_ID'] ?? 0);
        if ($paymentId > 0) {
            return 'PA#' . $paymentId;
        }

        return 'FM-' . (int)($row['ID'] ?? 0);
    }

    public static function ensureDefaultFundForSite(?string $siteId = null): array
    {
        return FundService::ensureDefaultFund($siteId);
    }

    public static function getFundBalanceFormatted(int $fundId): string
    {
        return FundBalanceService::formatRubles(FundBalanceService::getAvailableBalance($fundId)) . ' ₽';
    }
}
