<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;

/**
 * Платёж / взнос пользователя (доменная сущность модуля).
 *
 * Связь с b_user через USER_ID.
 */
class PaymentTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_payment';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('USER_ID'))
                ->configureRequired(),

            (new StringField('STATUS'))
                ->configureRequired()
                ->configureDefaultValue('pending')
                ->addValidator(new LengthValidator(null, 32)),

            (new FloatField('AMOUNT'))
                ->configureRequired(),

            /** Фондовый взнос (ledger) */
            (new FloatField('FUND_AMOUNT')),

            /** Налоги (часть счёта, не в ledger) */
            (new FloatField('TAX_AMOUNT')),

            /** ФОТ / содержание сайта (часть счёта, не в ledger) */
            (new FloatField('MAINTENANCE_AMOUNT')),

            (new StringField('CURRENCY'))
                ->configureRequired()
                ->configureDefaultValue('RUB')
                ->addValidator(new LengthValidator(3, 3)),

            /** Уникальный номер заказа модуля для шлюза (OrderId) */
            (new StringField('ORDER_ID'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 64)),

            /** Платёжный период: YYYY-MM, YYYY-MM-DD (anchor) или служебные коды (GT) */
            (new StringField('BILLING_PERIOD'))
                ->addValidator(new LengthValidator(null, 16)),

            (new StringField('GATEWAY_CODE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 32)),

            /** ID записи zr_paidaccess_gateway */
            (new IntegerField('GATEWAY_ID')),

            /** PaymentId / внешний ID в эквайринге */
            (new StringField('GATEWAY_PAYMENT_ID'))
                ->addValidator(new LengthValidator(null, 64)),

            /** Ссылка на платёжную форму T-Bank (PaymentURL из Init) */
            (new StringField('GATEWAY_PAYMENT_URL'))
                ->addValidator(new LengthValidator(null, 512)),

            (new StringField('DESCRIPTION'))
                ->addValidator(new LengthValidator(null, 255)),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new DatetimeField('DATE_UPDATE'))
                ->configureDefaultValue(static fn () => new DateTime()),

            (new DatetimeField('DATE_PAID')),

            (new Reference(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),

            (new OneToMany(
                'GATEWAY_TRANSACTIONS',
                GatewayTransactionTable::class,
                'PAYMENT'
            )),
        ];
    }
}
