<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

class NotificationLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_notification_log';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('USER_ID'))
                ->configureRequired(),

            (new StringField('NOTIFY_TYPE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 32)),

            (new StringField('CONTEXT_KEY'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 64)),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),
        ];
    }
}
