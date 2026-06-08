<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Internal\EventTypeTable;
use Zr\PaidAccess\PaidAccessCore;

class MailInstaller
{
    /**
     * @return array<string, array{name: string, desc: string, subject: string, body: string}>
     */
    protected static function getEventDefinitions(): array
    {
        return [
            PaidAccessCore::MAIL_EVENT_PAYMENT_PAID => [
                'name' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_NAME',
                'desc' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_DESC',
                'subject' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_SUBJECT',
                'body' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_BODY',
            ],
            PaidAccessCore::MAIL_EVENT_PAYMENT_FAILED => [
                'name' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_NAME',
                'desc' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_DESC',
                'subject' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_SUBJECT',
                'body' => 'ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_BODY',
            ],
            PaidAccessCore::MAIL_EVENT_SUBSCRIPTION_DEBT => [
                'name' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_NAME',
                'desc' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_DESC',
                'subject' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_SUBJECT',
                'body' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_BODY',
            ],
            PaidAccessCore::MAIL_EVENT_SUBSCRIPTION_EXPIRING => [
                'name' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_NAME',
                'desc' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_DESC',
                'subject' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_SUBJECT',
                'body' => 'ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_BODY',
            ],
            PaidAccessCore::MAIL_EVENT_ADMIN_ERROR => [
                'name' => 'ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_NAME',
                'desc' => 'ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_DESC',
                'subject' => 'ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_SUBJECT',
                'body' => 'ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_BODY',
            ],
        ];
    }

    public static function ensureEvents(): void
    {
        if (!\CModule::IncludeModule('main')) {
            return;
        }

        self::loadLangMessages();

        foreach (self::getEventDefinitions() as $eventName => $definition) {
            self::registerEventType($eventName, $definition);
            self::registerEventMessages($eventName, $definition);
        }
    }

    protected static function loadLangMessages(): void
    {
        $langFile = dirname(__DIR__, 2) . '/lang/ru/install/index.php';
        if (is_file($langFile)) {
            Loc::loadMessages($langFile);
        }
    }

    /**
     * @return string[]
     */
    protected static function getLanguageIds(): array
    {
        $ids = [];
        $rs = \CLanguage::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);
        while ($row = $rs->Fetch()) {
            $ids[] = (string)$row['LID'];
        }

        return $ids !== [] ? $ids : ['ru'];
    }

    /**
     * @param array{name: string, desc: string, subject: string, body: string} $definition
     */
    protected static function registerEventType(string $eventName, array $definition): void
    {
        $name = trim((string)Loc::getMessage($definition['name']));
        if ($name === '') {
            $name = $eventName;
        }
        $description = (string)Loc::getMessage($definition['desc']);

        foreach (self::getLanguageIds() as $langId) {
            $existing = EventTypeTable::getList([
                'filter' => [
                    '=EVENT_NAME' => $eventName,
                    '=LID' => $langId,
                ],
                'limit' => 1,
            ])->fetch();

            if (is_array($existing)) {
                if (empty($existing['EVENT_TYPE'])) {
                    EventTypeTable::update((int)$existing['ID'], [
                        'EVENT_TYPE' => EventTypeTable::TYPE_EMAIL,
                    ]);
                }
                continue;
            }

            $eventType = new \CEventType();
            $eventType->Add([
                'LID' => $langId,
                'EVENT_NAME' => $eventName,
                'EVENT_TYPE' => EventTypeTable::TYPE_EMAIL,
                'NAME' => $name,
                'DESCRIPTION' => $description,
                'SORT' => 100,
            ]);
        }
    }

    /**
     * @param array{name: string, desc: string, subject: string, body: string} $definition
     */
    protected static function registerEventMessages(string $eventName, array $definition): void
    {
        $subject = trim((string)Loc::getMessage($definition['subject']));
        $body = (string)Loc::getMessage($definition['body']);
        if ($subject === '' || $body === '') {
            return;
        }

        $rsSites = \CSite::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);
        while ($site = $rsSites->Fetch()) {
            $siteId = (string)$site['LID'];

            $existing = \CEventMessage::GetList($by, $order, [
                'EVENT_NAME' => $eventName,
                'SITE_ID' => $siteId,
            ])->Fetch();

            if ($existing) {
                continue;
            }

            $message = new \CEventMessage();
            $message->Add([
                'ACTIVE' => 'Y',
                'EVENT_NAME' => $eventName,
                'LID' => [$siteId],
                'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                'EMAIL_TO' => '#EMAIL#',
                'SUBJECT' => $subject,
                'BODY_TYPE' => 'html',
                'MESSAGE' => $body,
            ]);
        }
    }

    public static function uninstallEvents(): void
    {
        if (!\CModule::IncludeModule('main')) {
            return;
        }

        foreach (array_keys(self::getEventDefinitions()) as $eventName) {
            $messages = \CEventMessage::GetList($by = 'id', $order = 'desc', ['EVENT_NAME' => $eventName]);
            while ($message = $messages->Fetch()) {
                \CEventMessage::Delete((int)$message['ID']);
            }

            $types = EventTypeTable::getList([
                'filter' => ['=EVENT_NAME' => $eventName],
                'select' => ['ID'],
            ]);
            while ($type = $types->fetch()) {
                EventTypeTable::delete((int)$type['ID']);
            }
        }
    }
}
