<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;

/**
 * Подписка пользователя (состояние доступа, не отдельный платёж).
 *
 * USER_ID — один пользователь = одна актуальная запись (обновляется при оплатах).
 * PERIOD_END — до какой даты оплачен доступ.
 * LAST_PAYMENT_ID — последний успешный платёж.
 */
class SubscriptionTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_subscription';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('USER_ID'))
                ->configureRequired(),

            /** @see \Zr\PaidAccess\Enum\SubscriptionStatus */
            (new StringField('STATUS'))
                ->configureRequired()
                ->configureDefaultValue('debt')
                ->addValidator(new LengthValidator(null, 32)),

            /** День месяца продления (1–31), от DATE_REGISTER */
            (new IntegerField('BILLING_DAY'))
                ->configureRequired(),

            (new DatetimeField('PERIOD_END')),

            (new IntegerField('LAST_PAYMENT_ID')),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new DatetimeField('DATE_UPDATE'))
                ->configureDefaultValue(static fn () => new DateTime()),

            (new Reference(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),

            (new Reference(
                'LAST_PAYMENT',
                PaymentTable::class,
                Join::on('this.LAST_PAYMENT_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_LEFT),
        ];
    }
}
