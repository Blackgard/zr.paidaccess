<?php

namespace Zr\PaidAccess\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

class RequiredDocumentTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_required_document';
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
                ->addValidator(new LengthValidator(1, 64)),

            (new StringField('TITLE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(1, 255)),

            (new IntegerField('SORT'))
                ->configureRequired()
                ->configureDefaultValue(500),

            (new StringField('ACTIVE'))
                ->configureRequired()
                ->configureDefaultValue('Y')
                ->addValidator(new LengthValidator(1, 1)),

            (new StringField('IS_REQUIRED'))
                ->configureRequired()
                ->configureDefaultValue('Y')
                ->addValidator(new LengthValidator(1, 1)),

            (new DatetimeField('DATE_CREATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new DatetimeField('DATE_UPDATE'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new OneToMany(
                'VERSIONS',
                RequiredDocumentVersionTable::class,
                'DOCUMENT'
            )),
        ];
    }
}
