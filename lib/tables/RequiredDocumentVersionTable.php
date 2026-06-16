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
use Bitrix\Main\UserTable;

class RequiredDocumentVersionTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_required_document_version';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('DOCUMENT_ID'))
                ->configureRequired(),

            (new StringField('VERSION'))
                ->configureRequired()
                ->addValidator(new LengthValidator(1, 32)),

            (new IntegerField('FILE_ID'))
                ->configureNullable(),

            (new TextField('BODY_HTML'))
                ->configureNullable(),

            (new StringField('IS_CURRENT'))
                ->configureRequired()
                ->configureDefaultValue('N')
                ->addValidator(new LengthValidator(1, 1)),

            (new DatetimeField('DATE_PUBLISH'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new IntegerField('CREATED_BY'))
                ->configureNullable(),

            (new Reference(
                'DOCUMENT',
                RequiredDocumentTable::class,
                Join::on('this.DOCUMENT_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),

            (new Reference(
                'CREATOR',
                UserTable::class,
                Join::on('this.CREATED_BY', 'ref.ID')
            ))->configureJoinType(Join::TYPE_LEFT),
        ];
    }
}
