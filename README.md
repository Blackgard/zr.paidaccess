# Модуль `zr.paidaccess`

Платный доступ и ежемесячные взносы для сайта на **1C-Bitrix**

| Параметр | Значение |
|----------|----------|
| ID модуля | `zr.paidaccess` |
| Namespace | `Zr\PaidAccess\` |
| Версия | 1.0.0 (2026-05-25) |
| Партнёр | ZR studio — [alexandr-zr.ru](https://alexandr-zr.ru/) |

Модуль закрывает доступ к сайту для выбранных групп пользователей при неоплаченной подписке, принимает оплату через эквайринг T-Bank (Тинькофф), ведёт учёт платежей и подписок, предоставляет админку и публичные компоненты.

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
2. **Marketplace → Установленные решения → zr.paidaccess → Установить**.
3. Назначить права группам: **Настройки → Права доступа → zr.paidaccess**.
4. Настроить модуль: **Сервисы → Платёжный доступ → Настройки** (`settings.php?mid=zr.paidaccess`).
5. Создать платёжный шлюз T-Bank, отметить **«Использовать по умолчанию»**.
6. В личном кабинете T-Bank указать **Notification URL** (см. раздел [Webhook](#webhook)).

При установке автоматически:

- создаются таблицы ORM;
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
├── install/                    # Установка / удаление
│   ├── index.php               # CModule: БД, события, копирование файлов
│   ├── version.php
│   ├── components/zr/          # Исходники компонентов (копируются в /local/components/)
│   └── templates/              # Шаблон страницы блокировки
├── admin/                      # Страницы админки (копируются в /bitrix/admin/)
│   ├── menu.php                # Пункт «Сервисы → Платёжный доступ»
│   ├── zr_paidaccess_subscribers.php
│   ├── zr_paidaccess_payments.php
│   ├── zr_paidaccess_payment_edit.php
│   ├── zr_paidaccess_gateways.php
│   ├── zr_paidaccess_gateway_edit.php
│   ├── zr_paidaccess_gateway_import.php
│   └── zr_paidaccess_logs.php
├── classes/general/
│   └── PaidAccessCore.php      # Константы и чтение опций модуля (_s1, _ru, …)
├── lib/
│   ├── access/                 # Блокировка доступа, шаблоны
│   ├── subscription/           # Подписка, биллинг, агенты
│   ├── payment/                # Платежи, webhook, страница оплаты
│   ├── Gateway/                # Шлюзы и провайдеры
│   │   ├── Contract/           # PaymentGatewayInterface, GatewayProviderInterface
│   │   ├── Dto/                # InitPaymentRequest, InitPaymentResult, …
│   │   ├── Providers/          # Tinkoff/ (и будущие банки)
│   │   └── GatewayFactory.php
│   ├── Admin/                  # Сервисы админ-страниц
│   ├── Public/                 # Данные для публичных компонентов
│   ├── notification/           # Письма пользователю и админу
│   ├── log/                    # Журнал событий (БД) и аудит
│   ├── tables/                 # ORM DataManager
│   ├── enum/                   # PaymentStatus, SubscriptionStatus, …
│   ├── install/                # Инсталляторы схемы, агентов, почты
│   ├── options/                # Данные для формы настроек
│   └── tools/
│       └── Logger.php          # Единый файловый лог
├── tools/
│   └── webhook.php             # Публичная точка webhook (без авторизации)
├── options.php                 # Вкладки настроек модуля
├── include.php                 # Автозагрузка классов модуля
├── default_option.php
├── lang/ru/
└── tests/                      # PHPUnit (dev)
```

Подробнее о добавлении нового банка: [lib/Gateway/Providers/README.md](lib/Gateway/Providers/README.md).

---

## База данных (ORM)

| Таблица | Класс | Назначение |
|---------|-------|------------|
| `zr_paidaccess_payment` | `PaymentTable` | Платёж / взнос пользователя |
| `zr_paidaccess_subscription` | `SubscriptionTable` | Состояние подписки пользователя |
| `zr_paidaccess_gateway` | `GatewayTable` | Платёжные шлюзы (провайдер + JSON-опции) |
| `zr_paidaccess_gateway_transaction` | `GatewayTransactionTable` | Лог Init / GetQr / webhook по платежу |
| `zr_paidaccess_event_log` | `EventLogTable` | Журнал ошибок и событий модуля |
| `zr_paidaccess_audit_log` | `AuditLogTable` | Аудит действий в админке |
| `zr_paidaccess_notification_log` | `NotificationLogTable` | Лог отправленных уведомлений |

Ключевые поля платежа (`zr_paidaccess_payment`):

- `ORDER_ID` — идентификатор заказа для банка (`PA-{id}-{period}`);
- `BILLING_PERIOD` — расчётный период (`YYYY-MM`, `YYYY-MM-DD` или служебный `GT`);
- `GATEWAY_CODE` / `GATEWAY_ID` — привязка к шлюзу;
- `GATEWAY_PAYMENT_ID` — `PaymentId` в T-Bank;
- `GATEWAY_PAYMENT_URL` — ссылка на платёжную форму (`PaymentURL` из Init);
- `STATUS` — см. `PaymentStatus`.

Миграции схемы без переустановки: `PaymentInstaller::ensureSchema()`, `GatewayInstaller::ensureSchema()` (вызываются при установке/обновлении).

---

## События Bitrix

| Событие | Класс | Метод | Действие |
|---------|-------|-------|----------|
| `main:OnBeforeProlog` | `AccessBlockHandler` | `onBeforeProlog` | Блокировка сайта, показ страницы оплаты |
| `main:OnAfterUserRegister` | `RegistrationPaymentHandler` | `onAfterUserRegister` | Подготовка первого платежа после регистрации |
| `main:OnAfterUserLogin` | `RegistrationPaymentHandler` | `onAfterUserLogin` | Синхронизация долга после входа |

Страницы, которые **не блокируются**: админка, webhook, авторизация, статика и др. (см. `AccessBlockHandler::shouldSkipRequest()`).

---

## Агенты

| Агент | Интервал | Назначение |
|-------|----------|------------|
| `BillingDebtAgent::run` | 24 ч | Перевод просроченных подписок в статус «долг» |
| `SubscriptionReminderAgent::run` | 24 ч | Напоминание об окончании оплаченного периода |

Регистрация: `AgentInstaller::ensureAgents()`.

---

## Административный интерфейс

Меню: **Сервисы → Платёжный доступ**.

| Страница | URL | Описание |
|----------|-----|----------|
| Подписчики | `/bitrix/admin/zr_paidaccess_subscribers.php` | Статусы подписок, оплата за текущий период |
| Платежи | `/bitrix/admin/zr_paidaccess_payments.php` | Список и фильтры |
| Редактирование платежа | `/bitrix/admin/zr_paidaccess_payment_edit.php` | Ручные платежи, смена статуса, аудит |
| Шлюзы | `/bitrix/admin/zr_paidaccess_gateways.php` | Список эквайрингов |
| Редактирование шлюза | `/bitrix/admin/zr_paidaccess_gateway_edit.php` | Ключи T-Bank, тестовый платёж, Notification URL |
| Импорт шлюзов | `/bitrix/admin/zr_paidaccess_gateway_import.php` | Экспорт/импорт настроек |
| Журнал | `/bitrix/admin/zr_paidaccess_logs.php` | Вкладки: события, **запросы шлюза** (Init/GetState/GetQr/webhook), аудит админки |
| Настройки | `/bitrix/admin/settings.php?mid=zr.paidaccess` | Сумма взноса, биллинг, логи, почта |

---

## Публичные компоненты

Устанавливаются в `/local/components/zr/`:

| Компонент | Назначение |
|-----------|------------|
| `zr:personal.subscription` | Личный кабинет: статус подписки, кнопка оплаты |
| `zr:member.payment.list` | Список участников и статус оплаты (для модераторов) |

Пример подключения:

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
    → PaymentCompletionService
    → SubscriptionService (доступ восстановлен)
```

Статусы платежа (`Zr\PaidAccess\Enum\PaymentStatus`):

| Статус | Доступ на сайте |
|--------|-----------------|
| `pending` | Нет |
| `paid` | Да |
| `authorized` | Нет (авторизация без списания) |
| `failed` / `refunded` / `cancelled` | Нет |

---

## Webhook

**URL для T-Bank** (настраивается в ЛК банка и показывается в форме шлюза):

```
https://{домен}/local/modules/zr.paidaccess/tools/webhook.php?id={ID шлюза}
```

`id` — первичный ключ записи в `zr_paidaccess_gateway`.

Обработчик: `PaymentWebhookService::processRequest()`. Ответ при успехе: `OK` (`text/plain`), HTTP 200 — как в [документации по уведомлениям](https://developer.tbank.ru/eacq/intro/developer/notification).

При одностадийной оплате T-Bank шлёт **два** webhook подряд: `AUTHORIZED` и `CONFIRMED` — это нормально.

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

| Опция | Назначение |
|-------|------------|
| `MODULE_ACTIVE` | Включить модуль |
| `ACCESS_RESTRICTED_GROUPS` | Группы, для которых проверяется подписка |
| `ACCESS_BLOCK_TEMPLATE` | Файл шаблона блокировки |
| `SUBSCRIPTION_AMOUNT` | Сумма ежемесячного взноса (руб.) |
| `PAYMENT_DESCRIPTION` | Назначение платежа в банке; плейсхолдер `{SITE_NAME}` |
| `BILLING_PERIOD_MODE` | `calendar_month` / `registration` / `anchor_month` |
| `BILLING_GRACE_DAYS` | Льготные дни до блокировки |
| `PAYMENT_WIDGET_MODE` | `qr_sbp` / `payment_button` |
| `LOGGING_ACTIVE` | Файловое логирование |
| `LOG_LEVEL` | `debug` / `info` / `warning` / `error` |
| `LOG_PATH` | Путь к лог-файлу (по умолчанию `/upload/logs/zr.paidaccess.log`) |

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
- `zr_paidaccess_gateway_transaction` — вкладка «Платёжный шлюз» (полные request/response, HTTP-код, webhook). Ссылка также на форме редактирования шлюза.

---

## Использование из кода

Подключение модуля:

```php
use Bitrix\Main\Loader;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Access\AccessControl;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;

Loader::includeModule('zr.paidaccess');

// Активен ли модуль
PaidAccessCore::isModuleActive('s1');

// Нужна ли блокировка пользователю
AccessControl::mustShowBlockPage($userId);

// Текущий расчётный период
SubscriptionPaymentService::getCurrentBillingPeriod($userId, 's1');

// Создать pending-платёж и получить ID
$paymentId = SubscriptionPaymentService::preparePayment($userId, 's1');
```

Создание шлюза программно — через `GatewayRepository` и админ-сервисы; для оплаты всегда используется шлюз с флагом **«По умолчанию»** (`GatewayFactory::getDefaultGatewayRow()`).

---

## Тесты

```bash
cd local/modules/zr.paidaccess
composer install   # при первом запуске
php vendor/phpunit/phpunit/phpunit -c phpunit.xml.dist
```

Покрытие: биллинг, статусы платежей, Tinkoff API (токен, разбор ответов), логгер, админ-хелперы. Тесты не требуют установленного Bitrix (stubs в `tests/Stubs/`).

---

## Принципы разработки

1. **Платёжные шлюзы расширяемы** — новый банк = папка в `lib/Gateway/Providers/`, без правки фабрики.
2. **Sale / заказы Bitrix не используются** — доменная модель модуля (`PaymentTable`, `SubscriptionTable`).
3. **Опции и мультисайтовость** — суффикс `_{SITE_ID}` в `b_option`, нормализация через `PaidAccessCore::normalizeSiteId()`.

---

## Права доступа

Модуль регистрирует право `zr.paidaccess` (чтение / запись / полный доступ). Админ-страницы проверяют `$APPLICATION->GetGroupRight('zr.paidaccess')`.

---

## Контакты

- Автор: Alexandr Drachenin — [alexdrachenin98@gmail.com](mailto:alexdrachenin98@gmail.com)
- Сайт: [alexandr-zr.ru](https://alexandr-zr.ru/)
