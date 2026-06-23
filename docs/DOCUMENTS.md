# Обязательные документы

Модуль может требовать от пользователя подтверждения актуальных версий обязательных документов до проверки оплаты подписки.

## Поток

```text
OnBeforeProlog
    -> DocumentConsentControl
    -> шаблон согласия с документами
    -> AccessControl
    -> шаблон оплаты при необходимости
```

## Таблицы

| Таблица                                   | Назначение                      |
| ----------------------------------------- | ------------------------------- |
| `zr_paidaccess_required_document`         | документ                        |
| `zr_paidaccess_required_document_version` | версия документа                |
| `zr_paidaccess_document_acceptance`       | согласие пользователя с версией |

## Публикация версии

1. Открыть **Сервисы → Платёжный доступ → Документы**.
2. Создать документ: код, название, сайт, активность, обязательность.
3. Открыть вкладку **Версии**.
4. Нажать **Опубликовать новую версию**.
5. Загрузить файл или заполнить HTML-текст.
6. Опубликовать версию.

При публикации новой версии предыдущая перестаёт быть текущей, а пользователи должны подтвердить новую версию.

## Сервисы

| Класс                                  | Назначение                                    |
| -------------------------------------- | --------------------------------------------- |
| `DocumentConsentService`               | список ожидающих документов и принятие версий |
| `RequiredDocumentService`              | публичный каталог опубликованных документов   |
| `DocumentVersionService`               | публикация версий и формат номера             |
| `DocumentConsentControl`               | проверка необходимости страницы согласия      |
| `PublicUi\DocumentConsentPageRenderer` | full-page UI согласия                         |
| `PublicUi\PaymentWidgetPresenter`      | HTML виджета оплаты                           |
| `DocumentListViewService`              | view-model для `zr:document.list`             |
| `DocumentConsentViewService`           | view-model для `zr:document.consent`          |
| `DocumentAdminService`                 | CRUD документов в админке                     |

## Публичный список документов

```php
<?php
$APPLICATION->IncludeComponent(
    'zr:document.list',
    '',
    [
        'ONLY_REQUIRED' => 'N',
        'DETAIL_URL' => '/documents/#CODE#/',
        'SHOW_HEADER' => 'Y',
    ],
    false
);
```

## Форма согласия

```php
<?php
$APPLICATION->IncludeComponent('zr:document.consent', '', [], false);
```

Основной сценарий блокировки использует full-page шаблон `/local/php_interface/zr.paidaccess/template_document_consent.php`.

## Утилита импорта документов

Сервисная утилита `/bitrix/admin/zr_paidaccess_util_document_iblock.php` переносит элементы выбранного инфоблока в документы модуля.

Порядок работы:

1. Выбрать инфоблок-источник.
2. Сопоставить поля и свойства с целевыми полями документа.
3. Проверить предпросмотр.
4. Запустить перенос.

Выбранный инфоблок и mapping не сохраняются в опциях модуля и применяются только в рамках текущей формы.
