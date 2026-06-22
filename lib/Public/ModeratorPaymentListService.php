<?php

namespace Zr\PaidAccess\PublicUi;

use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Payment\PaymentManagementService;
use Zr\PaidAccess\Tables\PaymentTable;

/**
 * Список платежей для модератора (view-model, без привязки к URL панели).
 */
class ModeratorPaymentListService
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public static function buildViewModel(array $params = [], array $request = []): array
    {
        $pageSize = max(1, min(100, (int)($params['PAGE_SIZE'] ?? 20)));
        $page = max(1, (int)($request['PAGEN_1'] ?? 1));
        $editUrlBase = rtrim((string)($params['EDIT_URL'] ?? ''), '/');
        $listUrl = (string)($params['LIST_URL'] ?? '');

        $filterData = [
            'STATUS' => trim((string)($request['status'] ?? '')),
            'DATE_CREATE_from' => trim((string)($request['p_from'] ?? '')),
            'DATE_CREATE_to' => trim((string)($request['p_to'] ?? '')),
        ];

        $filter = PaymentManagementService::buildGridFilter($filterData);

        $userQuery = trim((string)($request['user'] ?? ''));
        $userIds = self::resolveUserIdsByQuery($userQuery);
        if ($userQuery !== '' && $userIds === []) {
            return self::emptyResult($filterData, $userQuery, $page, $pageSize, $editUrlBase, $listUrl);
        }
        if ($userIds !== []) {
            $filter['@USER_ID'] = $userIds;
        }

        $total = (int)PaymentTable::getCount($filter);
        $pageCount = max(1, (int)ceil($total / $pageSize));
        if ($page > $pageCount) {
            $page = $pageCount;
        }

        $list = PaymentTable::getList([
            'order' => ['ID' => 'DESC'],
            'filter' => $filter,
            'limit' => $pageSize,
            'offset' => ($page - 1) * $pageSize,
            'runtime' => [
                new ReferenceField(
                    'USER',
                    UserTable::class,
                    Join::on('this.USER_ID', 'ref.ID'),
                    ['join_type' => 'LEFT']
                ),
            ],
            'select' => [
                '*',
                'USER_NAME' => 'USER.NAME',
                'USER_LAST_NAME' => 'USER.LAST_NAME',
                'USER_EMAIL' => 'USER.EMAIL',
            ],
        ]);

        $items = [];
        while ($row = $list->fetch()) {
            $items[] = self::mapRow($row, $editUrlBase);
        }

        return [
            'ITEMS' => $items,
            'FILTER' => [
                'p_from' => $filterData['DATE_CREATE_from'],
                'p_to' => $filterData['DATE_CREATE_to'],
                'user' => $userQuery,
                'status' => $filterData['STATUS'],
            ],
            'STATUS_OPTIONS' => PaymentManagementService::getStatusTitles(),
            'NAV' => [
                'PAGE' => $page,
                'PAGE_SIZE' => $pageSize,
                'TOTAL' => $total,
                'PAGE_COUNT' => $pageCount,
            ],
            'EDIT_URL_BASE' => $editUrlBase,
            'LIST_URL' => $listUrl,
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function resolveUserIdsByQuery(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $ids = [];
        $parts = preg_split('/\s+/u', $query) ?: [];
        $searchTerms = array_values(array_unique(array_filter(array_merge([$query], $parts))));

        foreach ($searchTerms as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }

            if (ctype_digit($term)) {
                $ids[] = (int)$term;
            }

            $result = UserTable::getList([
                'filter' => [
                    'LOGIC' => 'OR',
                    ['%LAST_NAME' => $term],
                    ['%NAME' => $term],
                    ['%EMAIL' => $term],
                    ['%LOGIN' => $term],
                ],
                'select' => ['ID'],
                'limit' => 50,
            ]);

            while ($user = $result->fetch()) {
                $ids[] = (int)$user['ID'];
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row, string $editUrlBase): array
    {
        $id = (int)$row['ID'];
        $userName = trim((string)($row['USER_LAST_NAME'] ?? '') . ' ' . (string)($row['USER_NAME'] ?? ''));
        $status = (string)($row['STATUS'] ?? '');

        $dateCreate = '';
        if (!empty($row['DATE_CREATE']) && $row['DATE_CREATE'] instanceof DateTime) {
            $dateCreate = $row['DATE_CREATE']->format('d.m.Y H:i:s');
        } elseif (!empty($row['DATE_CREATE'])) {
            $dateCreate = (string)$row['DATE_CREATE'];
        }

        $amount = number_format((float)($row['AMOUNT'] ?? 0), 2, '.', ' ');
        $editUrl = '';
        if ($editUrlBase !== '') {
            $joiner = str_contains($editUrlBase, '?') ? '&' : '?';
            $editUrl = $editUrlBase . $joiner . 'CODE=' . $id;
        }

        return [
            'ID' => $id,
            'ORDER_ID' => (string)($row['ORDER_ID'] ?? ''),
            'DATE_LABEL' => $dateCreate,
            'AMOUNT_FORMATTED' => $amount,
            'CURRENCY' => (string)($row['CURRENCY'] ?? 'RUB'),
            'USER_NAME' => $userName,
            'USER_EMAIL' => (string)($row['USER_EMAIL'] ?? ''),
            'STATUS' => $status,
            'STATUS_LABEL' => PaymentManagementService::getStatusTitle($status),
            'EDIT_URL' => $editUrl,
        ];
    }

    /**
     * @param array<string, string> $filterData
     * @return array<string, mixed>
     */
    private static function emptyResult(
        array $filterData,
        string $userQuery,
        int $page,
        int $pageSize,
        string $editUrlBase,
        string $listUrl
    ): array {
        return [
            'ITEMS' => [],
            'FILTER' => [
                'p_from' => $filterData['DATE_CREATE_from'],
                'p_to' => $filterData['DATE_CREATE_to'],
                'user' => $userQuery,
                'status' => $filterData['STATUS'],
            ],
            'STATUS_OPTIONS' => PaymentManagementService::getStatusTitles(),
            'NAV' => [
                'PAGE' => $page,
                'PAGE_SIZE' => $pageSize,
                'TOTAL' => 0,
                'PAGE_COUNT' => 1,
            ],
            'EDIT_URL_BASE' => $editUrlBase,
            'LIST_URL' => $listUrl,
        ];
    }
}
