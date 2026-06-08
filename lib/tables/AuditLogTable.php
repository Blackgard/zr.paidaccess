<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

class AuditLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_audit_log';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new StringField('ENTITY_TYPE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 32)),

            (new IntegerField('ENTITY_ID'))
                ->configureRequired(),

            (new StringField('ACTION'))
                ->configureRequired()
                ->addValidator(new LengthValidator(null, 64)),

            (new TextField('OLD_VALUE')),

            (new TextField('NEW_VALUE')),

            (new IntegerField('ADMIN_USER_ID')),

            (new StringField('IP'))
                ->addValidator(new LengthValidator(null, 45)),

            (new TextField('MESSAGE')),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),
        ];
    }
}
