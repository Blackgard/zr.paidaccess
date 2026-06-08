<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

class EventLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_event_log';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new StringField('LEVEL'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 16)),

            (new StringField('CODE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 64)),

            (new TextField('MESSAGE'))
                ->configureRequired(),

            (new TextField('CONTEXT')),

            (new IntegerField('PAYMENT_ID')),

            (new IntegerField('USER_ID')),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),
        ];
    }
}
