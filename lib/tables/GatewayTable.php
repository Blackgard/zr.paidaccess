<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

/**
 * Платёжные шлюзы (провайдер + JSON-настройки).
 */
class GatewayTable extends DataManager
{
    public static function getTableName()
    {
        return 'zr_paidaccess_gateway';
    }

    public static function getMap()
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new StringField('NAME'))
                ->configureRequired()
                ->addValidator(new LengthValidator(1, 255)),

            /** Код провайдера: tinkoff, … */
            (new StringField('PROVIDER'))
                ->configureRequired()
                ->addValidator(new LengthValidator(1, 32)),

            /** Пусто — для всех сайтов, иначе LID (s1, …) */
            (new StringField('SITE_ID'))
                ->configureDefaultValue('')
                ->addValidator(new LengthValidator(0, 2)),

            (new StringField('IS_DEFAULT'))
                ->configureDefaultValue('N')
                ->addValidator(new LengthValidator(1, 1)),

            (new StringField('ACTIVE'))
                ->configureDefaultValue('Y')
                ->addValidator(new LengthValidator(1, 1)),

            /** Тестовый шлюз (T-Bank test API, не для боевых платежей на сайте) */
            (new StringField('IS_TEST'))
                ->configureDefaultValue('N')
                ->addValidator(new LengthValidator(1, 1)),

            /** Тестовая оплата для подключения эквайринга пройдена */
            (new StringField('TEST_PASSED'))
                ->configureDefaultValue('N')
                ->addValidator(new LengthValidator(1, 1)),

            (new DatetimeField('TEST_PASSED_AT')),

            /** ID тестового платежа модуля, подтвердившего подключение */
            (new IntegerField('TEST_MODULE_PAYMENT_ID')),

            /** JSON с параметрами провайдера */
            (new TextField('OPTIONS'))
                ->configureRequired(),

            (new IntegerField('SORT'))
                ->configureDefaultValue(500),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static function () {
                    return new DateTime();
                }),

            (new DatetimeField('DATE_UPDATE'))
                ->configureDefaultValue(static function () {
                    return new DateTime();
                }),
        ];
    }
}
