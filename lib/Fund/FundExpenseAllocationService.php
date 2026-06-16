<?php

namespace Zr\PaidAccess\Fund;

use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\Enum\FundExpenseAllocationMode;
use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Распределение суммы списания с фонда между участниками (равномерно или случайная выборка).
 */
class FundExpenseAllocationService
{
    /**
     * @param array<int, int>|null $participantIds для тестов
     *
     * @return array<int, array{USER_ID: int, AMOUNT: float}>
     */
    public static function buildAllocations(
        int $fundId,
        float $totalAmount,
        ?string $siteId = null,
        ?array $participantIds = null
    ): array {
        self::validateTotalAmount($totalAmount);

        $participantIds = $participantIds ?? FundExpenseParticipantResolver::listActiveParticipantIds($fundId);
        $participantIds = array_values($participantIds);
        if ($participantIds === []) {
            throw new \RuntimeException('Нет участников фонда с положительным вкладом для распределения списания');
        }

        $mode = PaidAccessCore::getFundExpenseAllocationMode($siteId);
        $selectedIds = self::selectParticipantIds(
            $participantIds,
            $mode,
            PaidAccessCore::getFundExpenseRandomParticipantCount($siteId)
        );

        return self::distributeAmount($totalAmount, $selectedIds);
    }

    /**
     * @param array<int, int> $participantIds
     *
     * @return array<int, int>
     */
    public static function selectParticipantIds(array $participantIds, string $mode, int $randomCount): array
    {
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));
        if ($participantIds === []) {
            return [];
        }

        if ($mode === FundExpenseAllocationMode::RANDOM) {
            $randomCount = max(1, $randomCount);
            $pool = $participantIds;
            shuffle($pool);

            return array_slice($pool, 0, min($randomCount, count($pool)));
        }

        return $participantIds;
    }

    /**
     * @param array<int, int> $userIds
     *
     * @return array<int, array{USER_ID: int, AMOUNT: float}>
     */
    public static function distributeAmount(float $totalAmount, array $userIds): array
    {
        self::validateTotalAmount($totalAmount);

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $count = count($userIds);
        if ($count === 0) {
            throw new \InvalidArgumentException('Укажите участников для распределения');
        }

        $totalKopecks = (int)round($totalAmount * 100);
        $baseKopecks = intdiv($totalKopecks, $count);
        $remainder = $totalKopecks % $count;

        $rows = [];
        foreach ($userIds as $index => $userId) {
            $kopecks = $baseKopecks + ($index < $remainder ? 1 : 0);
            $rows[] = [
                'USER_ID' => $userId,
                'AMOUNT' => $kopecks / 100,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{USER_ID: int, AMOUNT: float}>
     */
    public static function recordForMovement(int $movementId, int $fundId, float $amount, ?string $siteId = null): array
    {
        if ($movementId <= 0 || $fundId <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры списания');
        }

        $movement = FundMovementRepository::getById($movementId);
        if (!$movement) {
            throw new \RuntimeException('Движение #' . $movementId . ' не найдено');
        }

        if ((string)($movement['TYPE'] ?? '') !== FundMovementType::EXPENSE) {
            throw new \RuntimeException('Распределение доступно только для списаний');
        }

        if ((string)($movement['SOURCE'] ?? '') !== FundMovementSource::ADMIN) {
            return [];
        }

        if (FundExpenseAllocationRepository::countByMovementId($movementId) > 0) {
            return FundExpenseAllocationRepository::listByMovementId($movementId);
        }

        $allocations = self::buildAllocations($fundId, $amount, $siteId);
        FundExpenseAllocationRepository::createBatch($movementId, $fundId, $allocations);

        return $allocations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getAllocationsForMovement(int $movementId): array
    {
        return FundExpenseAllocationRepository::listByMovementId($movementId);
    }

    public static function formatAllocationSummary(array $allocations, int $maxNames = 2): string
    {
        if ($allocations === []) {
            return '—';
        }

        $parts = [];
        foreach (array_slice($allocations, 0, $maxNames) as $row) {
            $name = SubscriberAdminService::formatUserName([
                'NAME' => (string)($row['USER_NAME'] ?? ''),
                'LAST_NAME' => (string)($row['USER_LAST_NAME'] ?? ''),
            ]);
            if ($name === '') {
                $name = (string)($row['USER_LOGIN'] ?? ('#' . (int)($row['USER_ID'] ?? 0)));
            }
            $amount = FundBalanceService::formatRubles((float)($row['AMOUNT'] ?? 0));
            $parts[] = $name . ' (' . $amount . ' ₽)';
        }

        $summary = implode(', ', $parts);
        $extra = count($allocations) - $maxNames;
        if ($extra > 0) {
            $summary .= ' +' . $extra;
        }

        return $summary;
    }

    public static function validateTotalAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма списания должна быть больше нуля');
        }
    }
}
