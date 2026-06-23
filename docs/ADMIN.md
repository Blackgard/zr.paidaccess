# Административный интерфейс

Меню Bitrix: **Сервисы → Платёжный доступ**.

| Страница               | URL                                                     | Назначение                                   |
| ---------------------- | ------------------------------------------------------- | -------------------------------------------- |
| Подписчики             | `/bitrix/admin/zr_paidaccess_subscribers.php`           | статусы подписок и текущий период            |
| Платежи                | `/bitrix/admin/zr_paidaccess_payments.php`              | список платежей и фильтры                    |
| Редактирование платежа | `/bitrix/admin/zr_paidaccess_payment_edit.php`          | ручной платёж, смена статуса, связь с фондом |
| Фонды                  | `/bitrix/admin/zr_paidaccess_funds.php`                 | список фондов и баланс                       |
| Редактирование фонда   | `/bitrix/admin/zr_paidaccess_fund_edit.php`             | карточка фонда и движения                    |
| Ручное движение        | `/bitrix/admin/zr_paidaccess_fund_movement_edit.php`    | поступление или списание                     |
| Доли списания          | `/bitrix/admin/zr_paidaccess_fund_expense_view.php`     | участники и доли ручного списания            |
| Документы              | `/bitrix/admin/zr_paidaccess_documents.php`             | документы и текущие версии                   |
| Версия документа       | `/bitrix/admin/zr_paidaccess_document_version_edit.php` | публикация версии                            |
| Шлюзы                  | `/bitrix/admin/zr_paidaccess_gateways.php`              | список платёжных шлюзов                      |
| Редактирование шлюза   | `/bitrix/admin/zr_paidaccess_gateway_edit.php`          | ключи, тестовый платёж, Notification URL     |
| Импорт шлюзов          | `/bitrix/admin/zr_paidaccess_gateway_import.php`        | импорт JSON настроек                         |
| Журнал                 | `/bitrix/admin/zr_paidaccess_logs.php`                  | события, gateway requests, аудит             |
| Утилиты                | `/bitrix/admin/zr_paidaccess_utilities.php`             | сервисные утилиты                            |
| Импорт документов      | `/bitrix/admin/zr_paidaccess_util_document_iblock.php`  | перенос документов из инфоблока              |
| Настройки              | `/bitrix/admin/settings.php?mid=zr.paidaccess`          | параметры модуля                             |

## Страницы и сервисы

Сложная orchestration выносится из admin page в сервисы `lib/Admin`.

Пример:

- `admin/zr_paidaccess_payment_edit.php` отвечает за права, assets и Bitrix UI;
- `PaymentAdminEditService` загружает форму, обрабатывает POST и готовит redirect/save result;
- `PaymentAdminService` содержит доменные write-операции для платежей.

## Журнал

`/bitrix/admin/zr_paidaccess_logs.php` содержит вкладки:

- события и ошибки;
- аудит админки;
- запросы и ответы gateway.

Секреты в логах маскируются.

## Утилиты

Раздел утилит строится через `Utility\UtilitiesRegistry`. Новая сервисная утилита добавляется отдельной admin page и записью в реестре.

Текущая утилита:

| Код               | Назначение                                                |
| ----------------- | --------------------------------------------------------- |
| `document_iblock` | перенос элементов выбранного инфоблока в документы модуля |
