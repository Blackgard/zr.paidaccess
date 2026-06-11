# Модуль `zr.paidaccess`

Платный доступ и ежемесячные взносы для сайта на **1C-Bitrix**

| Параметр  | Значение                                                         |
| --------- | ---------------------------------------------------------------- |
| ID модуля | `zr.paidaccess`                                                  |
| Namespace | `Zr\PaidAccess\`                                                 |
| Версия    | 1.0.0 (2026-05-25)                                               |
| Партнёр   | ZR studio — [alexandr-zr.ru](https://alexandr-zr.ru/)            |
| Лицензия  | [MIT](LICENSE) — свободное использование с сохранением копирайта |

Модуль закрывает доступ к сайту для выбранных групп пользователей при неоплаченной подписке, принимает оплату через эквайринг **T-Bank (Тинькофф)**, ведёт учёт платежей и подписок, **учётный фонд сайта (ledger)**, предоставляет админку и публичные компоненты.

Доменная модель модуля **не использует** Sale/заказы Bitrix и legacy-инфоблоки оплат — только собственные таблицы ORM.

---

## Требования

- 1C-Bitrix 16+
- PHP 7.4+ (рекомендуется 8.x)
- Модуль `main`
- Исходящий HTTPS-доступ к `securepay.tinkoff.ru:443` (боевой API) и при тестах — к `rest-api-test.tinkoff.ru` (после добавления IP в whitelist T-Bank)
- `allow_url_fopen = On` (для HTTP-клиента Bitrix)

---

## Установка

1. Скопировать модуль в `local/modules/zr.paidaccess/`.
2. **Marketplace → Установленные решения → zr.paidaccess → Установить** (или повторно открыть модуль — сработает `ensureSchema()`).
3. Назначить права группам: **Настройки → Права доступа → zr.paidaccess**.
4. Настроить модуль: **Сервисы → Платёжный доступ → Настройки** (`settings.php?mid=zr.paidaccess`).
5. Создать платёжный шлюз T-Bank, отметить **«Использовать по умолчанию»**.
6. В личном кабинете T-Bank указать **Notification URL** (см. раздел [Webhook](#webhook)).
7. Подключить публичные компоненты на сайте (личный кабинет, кошелёк фонда — по задаче).

При установке и обновлении автоматически:

- создаются/обновляются таблицы ORM (`PaymentInstaller`, `GatewayInstaller`, `GatewayTransactionInstaller`, **`FundInstaller`**);
- для каждого активного сайта создаётся фонд по умолчанию (`CODE=default`);
- при первом появлении ledger выполняется **backfill** поступлений по уже оплаченным платежам (опция `FUND_MOVEMENTS_BACKFILLED`);
- копируются файлы админки в `/bitrix/admin/`;
- копируются компоненты в `/local/components/zr/`;
- копируется шаблон блокировки в `/local/php_interface/zr.paidaccess/template_need_paid.php`;
- регистрируются обработчики событий и агенты;
- создаются почтовые события.

---

## Структура модуля

Классическая раскладка Bitrix-модуля: `install/` — установщик, `admin/` — страницы админки, `lib/` — бизнес-логика, `classes/general/` — точка доступа к опциям.

```
local/modules/zr.paidaccess/
├── install/
│   ├── index.php               # CModule: БД, события, копирование файлов
│   ├── version.php
│   ├── components/zr/          # personal.subscription, member.payment.list, fund.wallet
│   └── templates/              # Шаблон страницы блокировки
├── admin/                      # Копируется в /bitrix/admin/
│   ├── menu.php
│   ├── zr_paidaccess_subscribers.php
│   ├── zr_paidaccess_payments.php
│   ├── zr_paidaccess_payment_edit.php
│   ├── zr_paidaccess_funds.php
│   ├── zr_paidaccess_fund_edit.php
│   ├── zr_paidaccess_fund_movement_edit.php
│   ├── zr_paidaccess_gateways.php
│   ├── zr_paidaccess_gateway_edit.php
│   ├── zr_paidaccess_gateway_import.php
│   └── zr_paidaccess_logs.php
├── classes/general/
│   └── PaidAccessCore.php      # Константы и чтение опций (_s1, _ru, …)
├── lib/
│   ├── access/                 # Блокировка доступа, шаблоны
│   ├── subscription/           # Подписка, биллинг, агенты
│   ├── payment/                # Платежи, webhook, страница оплаты
│   ├── Fund/                   # Фонд, ledger, баланс
│   ├── Gateway/                # Шлюзы и провайдеры (Tinkoff, …)
│   ├── Admin/                  # Сервисы админ-страниц
│   ├── Public/                 # Классы с namespace PublicUi\ (компоненты)
│   ├── notification/
│   ├── log/
│   ├── tables/                 # ORM DataManager
│   ├── enum/
│   ├── install/                # FundInstaller, PaymentInstaller, …
│   ├── options/
│   └── tools/
├── tools/
│   ├── webhook.php
│   └── verify_webhook_token.php
├── options.php
├── include.php
├── default_option.php
├── lang/ru/
└── tests/                      # PHPUnit (dev)
```

Подробнее о добавлении нового банка: [lib/Gateway/Providers/README.md](lib/Gateway/Providers/README.md).

---

## База данных (ORM)

| Таблица                             | Класс                     | Назначение                                        |
| ----------------------------------- | ------------------------- | ------------------------------------------------- |
| `zr_paidaccess_payment`             | `PaymentTable`            | Платёж / взнос пользователя                       |
| `zr_paidaccess_subscription`        | `SubscriptionTable`       | Состояние подписки пользователя                   |
| `zr_paidaccess_gateway`             | `GatewayTable`            | Платёжные шлюзы (провайдер + JSON-опции)          |
| `zr_paidaccess_gateway_transaction` | `GatewayTransactionTable` | Лог Init / GetQr / webhook по платежу             |
| `zr_paidaccess_event_log`           | `EventLogTable`           | Журнал ошибок и событий модуля                    |
| `zr_paidaccess_audit_log`           | `AuditLogTable`           | Аудит действий в админке                          |
| `zr_paidaccess_notification_log`    | `NotificationLogTable`    | Лог отправленных уведомлений                      |
| `zr_paidaccess_fund`                | `FundTable`               | Фонд (по умолчанию один на `SITE_ID`)             |
| `zr_paidaccess_fund_movement`       | `FundMovementTable`       | Движения средств (ledger)                         |

Ключевые поля платежа (`zr_paidaccess_payment`):

- `ORDER_ID` — идентификатор заказа для банка (`PA-{id}-{period}`);
- `BILLING_PERIOD` — расчётный период (`YYYY-MM`, `YYYY-MM-DD` или служебный `GT` для тестового шлюза);
- `GATEWAY_CODE` / `GATEWAY_ID` — привязка к шлюзу;
- `GATEWAY_PAYMENT_ID` — `PaymentId` в T-Bank;
- `GATEWAY_PAYMENT_URL` — ссылка на платёжную форму (`PaymentURL` из Init);
- `STATUS` — см. `PaymentStatus`.

Миграции схемы без переустановки: `PaymentInstaller::ensureSchema()`, `GatewayInstaller::ensureSchema()`, `GatewayTransactionInstaller::ensureSchema()`, **`FundInstaller::ensureSchema()`** (вызываются из `install/index.php`).

---

## Фонды и ledger

### Принцип

- **Баланс фонда не хранится в таблице** `zr_paidaccess_fund` — только вычисляется: **Σ income − Σ expense** по `zr_paidaccess_fund_movement`.
- В ledger попадает только **фондовый взнос** (`FUND_AMOUNT`), а не полная сумма оплаты. Клиент платит `налог + ФОТ + фондовый взнос` (например, 130 + 300 + 1000 = 1430 ₽).
- На каждый сайт (`SITE_ID`) по умолчанию один фонд: `CODE=default`, `IS_DEFAULT=Y` (`FundService::ensureDefaultFund()`).
- Привязка платежа к фонду: `FundPaymentSiteResolver` — `SITE_ID` шлюза платежа, иначе текущий/нормализованный сайт.

### Типы и источники движений

| Поле `TYPE`   | Значение    | Смысл              |
| ------------- | ----------- | ------------------ |
| `income`      | поступление | Увеличивает баланс |
| `expense`     | списание    | Уменьшает баланс   |

| Поле `SOURCE` | Когда создаётся                                      |
| ------------- | ---------------------------------------------------- |
| `payment`     | Оплаченный взнос участника                           |
| `refund`      | Возврат ранее учтённого платежа                      |
| `admin`       | Ручная операция в админке                            |
| `system`      | Служебные корректировки (через API сервиса)          |

### Единая точка записи — `FundMovementService`

| Метод                         | Назначение                                                                 |
| ----------------------------- | -------------------------------------------------------------------------- |
| `recordPaymentIncome($id)`    | Поступление от платежа (идемпотентно, skip тестовых `GT`)                  |
| `recordPaymentRefund($id)`    | Списание при возврате (если было поступление)                              |
| `recordManualIncome(...)`     | Ручное поступление (админка)                                               |
| `recordExpense(...)`          | Списание с проверкой баланса (`InsufficientFundBalanceException`)          |
| `tryRecordPaymentIncome/Refund` | Обёртки для хуков оплаты — не ломают основной поток при ошибке         |

### Когда создаются движения автоматически

| Событие                              | Сервис                         | Движение        |
| ------------------------------------ | ------------------------------ | --------------- |
| Webhook `CONFIRMED` / оплата         | `PaymentCompletionService`     | income `payment` |
| Webhook `REFUNDED`                   | `PaymentWebhookStatusService`  | expense `refund` |
| Ручная смена статуса на «Возврат»    | `PaymentAdminService`          | expense `refund` |
| Ручное подтверждение оплаты в админке| `PaymentCompletionService`     | income `payment` |
| Обновление модуля (один раз)         | `FundInstaller::backfillMovements` | income по paid |

Тестовые платежи шлюза (`BILLING_PERIOD=GT`) **не** попадают в фонд.

### Публичный кошелёк

`FundWalletService::getWalletData($siteId)` → баланс, число уникальных плательщиков, список движений для шаблона.  
Компонент **`zr:fund.wallet`** — страница «Кошелёк учредительного фонда» (баланс + история `+`/`-`).

Пример:

```php
<?php
$APPLICATION->IncludeComponent('zr:fund.wallet', '', [], false);
```

### Админка фондов

См. таблицу в разделе [Административный интерфейс](#административный-интерфейс). Ручные операции пишутся в `zr_paidaccess_audit_log` и журнал событий.

---

## События Bitrix

| Событие                    | Класс                        | Метод                 | Действие                                     |
| -------------------------- | ---------------------------- | --------------------- | -------------------------------------------- |
| `main:OnBeforeProlog`      | `AccessBlockHandler`         | `onBeforeProlog`      | Блокировка сайта, показ страницы оплаты      |
| `main:OnAfterUserRegister` | `RegistrationPaymentHandler` | `onAfterUserRegister` | Подготовка первого платежа после регистрации |
| `main:OnAfterUserLogin`    | `RegistrationPaymentHandler` | `onAfterUserLogin`    | Синхронизация долга после входа              |

Страницы, которые **не блокируются**: админка, webhook, авторизация, статика и др. (см. `AccessBlockHandler::shouldSkipRequest()`).

---

## Агенты

| Агент                            | Интервал | Назначение                                    |
| -------------------------------- | -------- | --------------------------------------------- |
| `BillingDebtAgent::run`          | 24 ч     | Перевод просроченных подписок в статус «долг» |
| `SubscriptionReminderAgent::run` | 24 ч     | Напоминание об окончании оплаченного периода  |

Регистрация: `AgentInstaller::ensureAgents()`.

---

## Административный интерфейс

Меню: **Сервисы → Платёжный доступ**.

| Страница               | URL                                                  | Описание                                                                         |
| ---------------------- | ---------------------------------------------------- | -------------------------------------------------------------------------------- |
| Подписчики             | `/bitrix/admin/zr_paidaccess_subscribers.php`        | Статусы подписок, оплата за текущий период                                       |
| Платежи                | `/bitrix/admin/zr_paidaccess_payments.php`           | Список и фильтры                                                                 |
| Редактирование платежа | `/bitrix/admin/zr_paidaccess_payment_edit.php`       | Ручные платежи, смена статуса, связь с фондом при paid/refund                    |
| Фонды                  | `/bitrix/admin/zr_paidaccess_funds.php`              | Список фондов, баланс по ledger                                                  |
| Редактирование фонда   | `/bitrix/admin/zr_paidaccess_fund_edit.php`          | Название, активность; вкладка «Движения»                                         |
| Ручное движение        | `/bitrix/admin/zr_paidaccess_fund_movement_edit.php` | Поступление или списание (источник `admin`)                                      |
| Шлюзы                  | `/bitrix/admin/zr_paidaccess_gateways.php`           | Список эквайрингов, экспорт/удаление                                             |
| Редактирование шлюза   | `/bitrix/admin/zr_paidaccess_gateway_edit.php`       | Ключи T-Bank, тестовый платёж, Notification URL                                  |
| Импорт шлюзов          | `/bitrix/admin/zr_paidaccess_gateway_import.php`     | Импорт JSON настроек                                                             |
| Журнал                 | `/bitrix/admin/zr_paidaccess_logs.php`               | Вкладки: события, аудит, запросы шлюза; **очистка** журналов и файлового лога     |
| Настройки              | `/bitrix/admin/settings.php?mid=zr.paidaccess`       | Сумма взноса, биллинг, логи, почта                                               |

---

## Публичные компоненты

Устанавливаются в `/local/components/zr/`:

| Компонент                  | Назначение                                                                 |
| -------------------------- | -------------------------------------------------------------------------- |
| `zr:personal.subscription` | Личный кабинет: статус подписки, кнопка оплаты                             |
| `zr:member.payment.list`   | Список участников и статус оплаты (для модераторов)                      |
| `zr:fund.wallet`           | Кошелёк фонда: баланс из ledger, история движений (`+` поступления / `-` списания) |

Пример подключения подписки:

```php
<?php
$APPLICATION->IncludeComponent(
    'zr:personal.subscription',
    '',
    [],
    false
);
```

---

## Страница блокировки и оплаты

Шаблон по умолчанию: `/local/php_interface/zr.paidaccess/template_need_paid.php`  
(копируется из `install/templates/` при установке).

Выбор шаблона — опция **«Шаблон страницы блокировки»** в настройках модуля.

Рендер: `PayBlockPageRenderer::render()` → создаёт/находит pending-платёж → `SubscriptionPaymentService::renderPaymentWidget()`.

Способ оплаты на сайте (опция `PAYMENT_WIDGET_MODE`):

- `qr_sbp` — QR СБП (по умолчанию);
- `payment_button` — кнопка «Перейти к оплате» (форма T-Bank).

---

## Платёжный поток

```
Пользователь без оплаты
    → AccessBlockHandler (OnBeforeProlog)
    → PayBlockPageRenderer
    → SubscriptionPaymentService::preparePayment()
        → BillingPolicy::assertCanInitPayment()
        → GatewayFactory::getDefaultGatewayRow()
        → PaymentRepository (pending за период)
        → ensureGatewayInit() → TinkoffGateway::initPayment() → Init API
    → renderPaymentWidget()
        → fetchPaymentForm() → GetState / GetQr / PaymentURL
    → HTML QR или кнопка

Оплата в банке
    → POST webhook.php?id={gateway_id}
    → PaymentWebhookService
    → TinkoffGateway::handleWebhook()
    → CONFIRMED → PaymentCompletionService
        → SubscriptionService (доступ восстановлен)
        → FundMovementService::tryRecordPaymentIncome()  // ledger
    → REFUNDED → PaymentWebhookStatusService
        → FundMovementService::tryRecordPaymentRefund()
    → AUTHORIZED и др. промежуточные → acknowledge (без смены доступа)
```

Статусы платежа (`Zr\PaidAccess\Enum\PaymentStatus`):

| Статус                              | Доступ на сайте                |
| ----------------------------------- | ------------------------------ |
| `pending`                           | Нет                            |
| `paid`                              | Да                             |
| `authorized`                        | Нет (авторизация без списания) |
| `failed` / `refunded` / `cancelled` | Нет                            |

---

## Webhook

**URL для T-Bank** (настраивается в ЛК банка и показывается в форме шлюза):

```
https://{домен}/local/modules/zr.paidaccess/tools/webhook.php?id={ID шлюза}
```

`id` — первичный ключ записи в `zr_paidaccess_gateway`.

Обработчик: `PaymentWebhookService::processRequest()`. Ответ при успехе: `OK` (`text/plain`), HTTP 200 — как в [документации по уведомлениям](https://developer.tbank.ru/eacq/intro/developer/notification).

При одностадийной оплате T-Bank шлёт **два** webhook подряд: `AUTHORIZED` и `CONFIRMED` — это нормально. Промежуточные статусы (`AUTHORIZED`, …) логируются в `PaymentWebhookStatusService::acknowledgeIntermediate()` и **не** открывают доступ и **не** меняют баланс фонда.

Проверка подписи webhook: `TinkoffApiClient::verifyNotificationToken()` — алгоритм из раздела «Проверить токен уведомлений» ([notification](https://developer.tbank.ru/eacq/intro/developer/notification), [token](https://developer.tbank.ru/eacq/intro/developer/token)). Юнит-тест сверяет хеш с официальным примером (`TinkoffApiClientTest::testBuildNotificationTokenMatchesTbankDocumentationExample`).

Диагностика: `php local/modules/zr.paidaccess/tools/verify_webhook_token.php --gateway-id=1 --file=webhook.json`

Документация T-Bank: [интеграция 1C-Bitrix](https://developer.tbank.ru/eacq/modules/1c-bitrix), [тестовая среда](https://developer.tbank.ru/eacq/intro/errors/test).

---

## Настройка T-Bank (кратко)

1. **Шлюз в админке**: TerminalKey, SecretKey, тестовый/боевой режим, чек 54-ФЗ при необходимости.
2. **ЛК T-Bank → Магазины → Терминалы → Настроить**:
    - Подключение: Универсальное;
    - Уведомления: HTTP;
    - Notification URL — см. выше.
3. **Тестовая среда** (`rest-api-test.tinkoff.ru`): IP сервера должен быть в whitelist (запрос в T-Бизнес / openapi@tbank.ru). Без whitelist — HTTP 403.
4. **Боевая среда**: снять «Тестовый API», использовать `securepay.tinkoff.ru`.

---

## Основные опции модуля

Опции хранятся с суффиксом сайта: `OPTION_NAME_s1`, `OPTION_NAME_ru`. Чтение: `PaidAccessCore::getOptionByCode()` / специализированные геттеры.

| Опция                           | Назначение                                                       |
| ------------------------------- | ---------------------------------------------------------------- |
| `MODULE_ACTIVE`                 | Включить модуль                                                  |
| `ACCESS_RESTRICTED_GROUPS`      | Группы, для которых проверяется подписка                         |
| `ACCESS_BLOCK_TEMPLATE`         | Файл шаблона блокировки                                          |
| `SUBSCRIPTION_FUND_AMOUNT`      | Фондовый взнос (руб.) — отображается в UI и попадает в ledger    |
| `SUBSCRIPTION_TAX_AMOUNT`       | Налоги (руб.) — часть счёта клиенту, не в ledger                 |
| `SUBSCRIPTION_MAINTENANCE_AMOUNT` | Содержание сайта / ФОТ (руб.) — часть счёта, не в ledger       |
| `SUBSCRIPTION_AMOUNT`           | Устаревшее поле; fallback для `SUBSCRIPTION_FUND_AMOUNT`         |
| `PAYMENT_DESCRIPTION`           | Назначение платежа в банке; плейсхолдер `{SITE_NAME}`            |
| `BILLING_PERIOD_MODE`           | `calendar_month` / `registration` / `anchor_month`                 |
| `BILLING_ANCHOR_SOURCE`         | Для `anchor_month`: день регистрации или фиксированный день      |
| `BILLING_FIXED_DAY`             | Фиксированный день месяца (1–28)                                 |
| `BILLING_SHORT_MONTH_POLICY`    | Поведение в коротких месяцах (`last_day` / `previous`)           |
| `BILLING_ENFORCE_ONE_PAYMENT`   | Один paid-платёж на период (защита от дублей)                    |
| `BILLING_GRACE_DAYS`            | Льготные дни до блокировки                                       |
| `PAYMENT_WIDGET_MODE`           | `qr_sbp` / `payment_button`                                      |
| `PAYMENT_EMAIL_NOTIFY`          | Письмо пользователю об успешной оплате                           |
| `PAYMENT_PAGE_ERROR_TEXT`       | Текст ошибки на странице оплаты                                  |
| `MAIL_NOTIFY_*`                 | Уведомления о долге, просрочке, ошибке оплаты                    |
| `MAIL_SUBSCRIPTION_EXPIRING_DAYS` | За сколько дней напоминать об окончании периода                |
| `ERROR_NOTIFY_ENABLED` / `ERROR_NOTIFY_EMAIL` | Письмо админу при критических ошибках            |
| `GATEWAY_TEST_AMOUNT`           | Сумма тестового платежа в админке шлюза                          |
| `LOGGING_ACTIVE`                | Файловое логирование                                             |
| `LOG_LEVEL`                     | `debug` / `info` / `warning` / `error`                           |
| `LOG_PATH`                      | Путь к лог-файлу (по умолчанию `/upload/logs/zr.paidaccess.log`) |

Форма настроек: `options.php` (вкладки: основные, биллинг, почта, логи).

---

## Логирование

Единый файловый лог: **`/upload/logs/zr.paidaccess.log`** (путь настраивается).

Класс: `Zr\PaidAccess\Tools\Logger`.

В один файл пишутся:

- события модуля (`ModuleEventLogService` → категория = код события);
- HTTP-запросы к T-Bank (`Logger::logHttpExchange`, категория `tinkoff`);
- прямые вызовы `Logger::info()` / `warning()` / `error()`.

Формат строки:

```
[2026-06-08 16:36:55] [WARNING] [tinkoff] Init https://securepay.tinkoff.ru/v2/Init {"method":"Init",...}
```

Секреты (`Token`, `Password`, `TerminalKey`) маскируются. Для записи HTTP-запросов включите логирование и уровень **Info** или **Debug**.

Дублирование в БД:

- `zr_paidaccess_event_log` — вкладка «События и ошибки»;
- `zr_paidaccess_gateway_transaction` — вкладка «Платёжный шлюз» (полные request/response, HTTP-код, webhook);
- `zr_paidaccess_audit_log` — вкладка «Аудит» (в т.ч. фонды и ручные движения).

Очистка журналов и файлового лога — **`LogCleanupAdminService`** (кнопки на странице журнала).

---

## Использование из кода

Подключение модуля:

```php
use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;
use Zr\PaidAccess\PublicUi\FundWalletService;
use Zr\PaidAccess\Fund\FundMovementService;

Loader::includeModule('zr.paidaccess');

// Активен ли модуль
PaidAccessCore::isModuleActive('s1');

// Нужна ли блокировка пользователю
AccessControl::mustShowBlockPage($userId);

// Текущий расчётный период
SubscriptionPaymentService::getCurrentBillingPeriod($userId, 's1');

// Создать pending-платёж и получить ID
$paymentId = SubscriptionPaymentService::preparePayment($userId, 's1');

// Данные кошелька фонда текущего сайта
$wallet = FundWalletService::getWalletData();

// Программное списание с фонда (с проверкой баланса)
FundMovementService::recordExpense($fundId, 500.0, 'Расход по решению совета', [
    'source' => 'system',
]);
```

Создание шлюза программно — через `GatewayRepository` и админ-сервисы; для оплаты на сайте используется шлюз с флагом **«По умолчанию»** (`GatewayFactory::getDefaultGatewayRow()`).

---

## Форматирование кода

PHP — **PHP-CS-Fixer** (PSR-12, удаление лишних пустых строк и пробелов в конце строк). Markdown, JSON, CSS — **Prettier**.

```bash
cd local/modules/zr.paidaccess
composer install && npm install   # один раз
composer format                   # PHP
composer format:assets            # README, JSON, CSS
composer format:all               # всё
composer format:check             # проверка без записи (PHP)
```

Файлы `install/admin/*.php` (короткий тег `<?`) в fixer не включены. В редакторе: `.editorconfig` + опционально расширение **PHP CS Fixer** (см. `.vscode/settings.json`).

---

## Тесты

```bash
cd local/modules/zr.paidaccess
composer install   # при первом запуске
composer test
# или: php vendor/phpunit/phpunit/phpunit -c phpunit.xml.dist
```

Покрытие: биллинг, статусы платежей, Tinkoff API (токен, разбор ответов), **фонд и ledger**, логгер, админ-хелперы. Тесты не требуют установленного Bitrix (stubs в `tests/Stubs/`).

---

## Принципы разработки

1. **Платёжные шлюзы расширяемы** — новый банк = папка в `lib/Gateway/Providers/`, без правки фабрики.
2. **Sale / заказы Bitrix не используются** — доменная модель модуля (`PaymentTable`, `SubscriptionTable`).
3. **Баланс фонда только из ledger** — не добавлять поле «остаток» в `FundTable`.
4. **Движения фонда** — только через `FundMovementService` (идемпотентность для платежей).
5. **Опции и мультисайтовость** — суффикс `_{SITE_ID}` в `b_option`, нормализация через `PaidAccessCore::normalizeSiteId()`.
6. **Legacy сайта** (IBLOCK 13/14, `b_payments`, старые `api/tinkoff`) — только для чтения при миграции, не для нового кода.

---

## Права доступа

Модуль регистрирует право `zr.paidaccess` (чтение / запись / полный доступ). Админ-страницы проверяют `$APPLICATION->GetGroupRight('zr.paidaccess')`.

---

## Лицензия

Модуль распространяется под лицензией **MIT** ([файл LICENSE](LICENSE)).

Вы можете свободно использовать, изменять и распространять код — в том числе в коммерческих проектах — при условии, что в копиях и производных работах **сохраняется уведомление об авторских правах** и текст лицензии:

> Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)  
> Author: Alexandr Drachenin

Рекомендуем также указывать ссылку на репозиторий и сайт разработчика в документации проекта.

---

## Контакты

- Автор: Alexandr Drachenin — [alexdrachenin98@gmail.com](mailto:alexdrachenin98@gmail.com)
- Сайт: [alexandr-zr.ru](https://alexandr-zr.ru/)
