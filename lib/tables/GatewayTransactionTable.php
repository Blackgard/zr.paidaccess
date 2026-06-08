<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;

/**
 * Лог взаимодействий с платёжным шлюзом (Init, GetQr, webhook, проверка статуса).
 *
 * Одна запись Payment — много GatewayTransaction.
 * Сырые REQUEST_DATA / RESPONSE_DATA — JSON для аудита и отладки.
 */
class GatewayTransactionTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_gateway_transaction';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('PAYMENT_ID'))
                ->configureDefaultValue(0),

            /** ID записи zr_paidaccess_gateway */
            (new IntegerField('GATEWAY_ID'))
                ->configureDefaultValue(0),

            (new StringField('GATEWAY_CODE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 32)),

            /** @see \Zr\PaidAccess\Enum\GatewayEventType */
            (new StringField('EVENT_TYPE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 32)),

            /** Статус, как вернул шлюз (CONFIRMED, REJECTED, …) */
            (new StringField('GATEWAY_STATUS'))
                ->addValidator(new LengthValidator(null, 64)),

            /** Смапленный статус модуля после обработки */
            (new StringField('INTERNAL_STATUS'))
                ->addValidator(new LengthValidator(null, 32)),

            (new TextField('REQUEST_DATA')),

            (new TextField('RESPONSE_DATA')),

            (new IntegerField('HTTP_CODE')),

            (new StringField('SUCCESS'))
                ->configureDefaultValue('N')
                ->addValidator(new LengthValidator(1, 1)),

            (new StringField('ERROR_MESSAGE'))
                ->addValidator(new LengthValidator(null, 512)),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new Reference(
                'PAYMENT',
                PaymentTable::class,
                Join::on('this.PAYMENT_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),
        ];
    }
}
