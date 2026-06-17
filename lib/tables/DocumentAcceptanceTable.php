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

class DocumentAcceptanceTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'zr_paidaccess_document_acceptance';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('USER_ID'))
                ->configureRequired(),

            (new IntegerField('DOCUMENT_ID'))
                ->configureRequired(),

            (new IntegerField('VERSION_ID'))
                ->configureRequired(),

            (new StringField('SITE_ID'))
                ->configureRequired()
                ->addValidator(new LengthValidator(2, 2)),

            (new DatetimeField('DATE_ACCEPT'))
                ->configureRequired()
                ->configureDefaultValue(static fn () => new DateTime()),

            (new Reference(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),

            (new Reference(
                'DOCUMENT',
                RequiredDocumentTable::class,
                Join::on('this.DOCUMENT_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),

            (new Reference(
                'VERSION',
                RequiredDocumentVersionTable::class,
                Join::on('this.VERSION_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_INNER),
        ];
    }
}
