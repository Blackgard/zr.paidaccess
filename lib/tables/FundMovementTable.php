<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;

/**
 * Движение средств фонда (ledger).
 */
class FundMovementTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_fund_movement';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('FUND_ID'))
                ->configureRequired(),

            (new StringField('TYPE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 16)),

            (new FloatField('AMOUNT'))
                ->configureRequired(),

            (new StringField('CURRENCY'))
                ->configureRequired()
                ->configureDefaultValue('RUB')
                ->addValidator(new LengthValidator(3, 3)),

            (new StringField('DESCRIPTION'))
                ->addValidator(new LengthValidator(null, 512)),

            (new StringField('SOURCE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 16)),

            (new IntegerField('PAYMENT_ID')),

            (new IntegerField('USER_ID')),

            (new StringField('ORDER_ID'))
                ->addValidator(new LengthValidator(null, 64)),

            (new IntegerField('ADMIN_USER_ID')),

            (new StringField('EXTERNAL_REF'))
                ->addValidator(new LengthValidator(null, 64)),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new Reference(
                'FUND',
                FundTable::class,
                Join::on('this.FUND_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),

            (new Reference(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_LEFT),
        ];
    }
}
