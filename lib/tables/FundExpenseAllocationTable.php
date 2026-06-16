<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;

/**
 * Доля участника в списании с учредительного фонда (проект / расход).
 */
class FundExpenseAllocationTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_fund_expense_allocation';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('MOVEMENT_ID'))
                ->configureRequired(),

            (new IntegerField('FUND_ID'))
                ->configureRequired(),

            (new IntegerField('USER_ID'))
                ->configureRequired(),

            (new FloatField('AMOUNT'))
                ->configureRequired(),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new Reference(
                'MOVEMENT',
                FundMovementTable::class,
                Join::on('this.MOVEMENT_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),

            (new Reference(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_LEFT),
        ];
    }
}
