<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

/**
 * Фонд — учётная сущность для движения денежных средств на сайте.
 */
class FundTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_fund';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new StringField('SITE_ID'))
                ->configureRequired()
                ->addValidator(new LengthValidator(2, 2)),

            (new StringField('CODE'))
                ->configureRequired()
                ->configureDefaultValue('default')
                ->addValidator(new LengthValidator(1, 32)),

            (new StringField('NAME'))
                ->configureRequired()
                ->addValidator(new LengthValidator(1, 255)),

            (new StringField('CURRENCY'))
                ->configureRequired()
                ->configureDefaultValue('RUB')
                ->addValidator(new LengthValidator(3, 3)),

            (new StringField('IS_DEFAULT'))
                ->configureRequired()
                ->configureDefaultValue('Y')
                ->addValidator(new LengthValidator(1, 1)),

            (new StringField('ACTIVE'))
                ->configureRequired()
                ->configureDefaultValue('Y')
                ->addValidator(new LengthValidator(1, 1)),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new OneToMany(
                'MOVEMENTS',
                FundMovementTable::class,
                'FUND'
            )),
        ];
    }
}
