# Структура модуля zr.paidaccess

Статус: рабочий стандарт структуры для `local/modules/zr.paidaccess/`.

Документ фиксирует разрешённые каталоги, namespaces и правила добавления новых файлов. Если требуется новый слой, каталог или публичный контракт, сначала обновляется этот документ, затем код.

## Корень модуля

Модуль разрабатывается только в:

```text
local/modules/zr.paidaccess/
```

Разрешённая структура верхнего уровня:

```text
local/modules/zr.paidaccess/
├── admin/
├── classes/general/
├── docs/
├── install/
├── lang/
├── lib/
├── tests/
├── tools/
├── default_option.php
├── include.php
├── options.php
└── README.md
```

Новые каталоги верхнего уровня без обновления этого документа не создаются.

## Назначение каталогов

### `admin/`

Реальные страницы административного интерфейса модуля.

Правила:

- admin page отвечает за HTTP/admin форму, проверку запроса, вывод Bitrix UI;
- бизнес-операции выносятся в `lib/Admin/*Service.php` или доменные сервисы;
- прямой доступ к ORM/репозиториям допустим только для простых read-only экранов; новые write-сценарии должны идти через service;
- миграционные утилиты живут здесь только как admin entry point, доменная часть миграции выносится в `lib/Admin` / `lib/Utility`.

### `classes/general/`

Стандартная Bitrix-точка для классов общего доступа.

Разрешённый класс:

- `Zr\PaidAccess\PaidAccessCore` -> `classes/general/PaidAccessCore.php`.

`PaidAccessCore` отвечает только за:

- константы опций;
- чтение пользовательских настроек модуля;
- нормализацию site id;
- простую нормализацию значений настроек.

Бизнес-логика доступа, подписки, оплаты, фонда и документов здесь не размещается.

### `docs/`

Архитектурные и проектные документы.

Обязательные документы:

- `STRUCTURE.md` — структура каталогов, namespaces, правила добавления файлов;
- `BOUNDARIES.md` — границы слоёв, запреты внешних источников, допустимые зависимости.

Рекомендуемые документы для новых крупных задач:

- `REQUIREMENTS.md` — требования/ТЗ;
- `FLOWS.md` — пользовательские и системные сценарии;
- `GATEWAYS.md` — правила платёжных шлюзов, если информации в `lib/Gateway/Providers/README.md` станет недостаточно.

### `install/`

Bitrix installer и файлы, которые копируются на сайт.

Разрешённая структура:

```text
install/
├── admin/
├── assets/
├── components/zr/
├── templates/
├── themes/.default/
├── index.php
└── version.php
```

Правила:

- `install/index.php` — orchestration установки/обновления/удаления;
- schema/data/file операции по возможности выносятся в `lib/install/*Installer.php`;
- `install/admin/*.php` — stub-файлы для `/bitrix/admin/`;
- `install/components/zr/*` — публичные компоненты модуля;
- `install/templates/*` — шаблоны, которые копируются в `/local/php_interface/zr.paidaccess/`;
- `install/assets/*` и `install/themes/*` — статические файлы, используемые модулем.

### `lang/`

Файлы локализации Bitrix.

Правила:

- новые пользовательские строки в PHP/admin/component/template коде выносятся в `lang/ru/...`;
- не смешивать русские тексты в сервисах, если они относятся к UI и уже имеют lang-файл рядом;
- технические сообщения исключений допустимы в сервисах, если они нужны для логов/админки.

### `lib/`

Основная логика модуля.

Разрешённые каталоги:

```text
lib/
├── access/
├── Admin/
├── Document/
├── enum/
├── Fund/
├── Gateway/
├── install/
├── log/
├── notification/
├── options/
├── payment/
├── Public/
├── subscription/
├── tables/
├── tools/
└── Utility/
```

Новые каталоги внутри `lib/` создаются только после обновления этого документа.

## Слои `lib/`

### `lib/access/`

Namespace: `Zr\PaidAccess\Access`.

Назначение:

- блокировка доступа;
- проверка доступа пользователя;
- read-model статуса доступа участника (`SubscriberAccessService`);
- обработчики регистрации/логина;
- выбор и подключение шаблонов блокировки/согласия.

Разрешённые зависимости:

- `PaidAccessCore`;
- `Payment`, `Subscription`, `Document`;
- `Tables`/Bitrix user APIs при необходимости.

Запрещено:

- банковские provider-классы;
- прямые HTTP-вызовы банка;
- внешние источники оплат.

### `lib/subscription/`

Namespace: `Zr\PaidAccess\Subscription`.

Назначение:

- состояние подписки;
- расчёт периода оплаты;
- задолженность и напоминания;
- quote/amount breakdown.

Разрешённые зависимости:

- `PaidAccessCore`;
- `Payment` только через устойчивые сервисы/репозитории, если требуется история оплат;
- `Tables`;
- `Notification` для доменных событий уведомления.

Запрещено:

- provider-specific gateway классы;
- прямые HTTP/webhook детали банка;
- admin/public UI.

### `lib/payment/`

Namespace: `Zr\PaidAccess\Payment`.

Назначение:

- платёжная запись модуля;
- подготовка оплаты подписки;
- применение webhook-результата;
- завершение/отмена платежей;
- общие read-model/action методы платежей для admin/public адаптеров (`PaymentManagementService`);
- лог gateway transactions.

Разрешённые зависимости:

- `Gateway` contracts/factory/repository;
- `Subscription`;
- `Fund` для записи ledger после изменения payment status;
- `Notification`;
- `Tables`.

Запрещено:

- прямые зависимости от `Gateway\Providers\Tinkoff\*` и других provider namespaces;
- разбор provider-specific webhook fields вне `Gateway` adapter;
- provider-specific payment URL parsers вне provider;
- прямое открытие доступа без `Subscription`/`Access` сервисов.

Provider-specific возможности оформляются через contracts/capabilities в `Gateway\Contract`, а реализация остаётся внутри `Gateway/Providers/{Name}`. Новые зависимости от `Gateway\Providers\*` вне gateway provider слоя не добавлять.

### `lib/Gateway/`

Namespace: `Zr\PaidAccess\Gateway`.

Назначение:

- contracts/DTO для платёжных шлюзов;
- repository/factory/registry;
- provider discovery;
- provider-specific adapters в `Providers/{Name}`.

Разрешённая структура:

```text
Gateway/
├── Contract/
├── Dto/
├── Provider/
├── Providers/
├── GatewayFactory.php
├── GatewayRepository.php
├── GatewayTestService.php
└── ReceiptDeliveryResolver.php
```

Правила:

- новый банк добавляется в `lib/Gateway/Providers/{Name}/`;
- `GatewayFactory` и `GatewayProviderRegistry` не правятся для каждого нового банка;
- provider-specific подпись, статусы, ошибки, URL, receipt/body builders живут внутри provider-папки;
- gateway не открывает доступ и не пишет ledger напрямую: он возвращает DTO результата, остальное делает модуль.

### `lib/Fund/`

Namespace: `Zr\PaidAccess\Fund`.

Назначение:

- фонд;
- ledger movements;
- расчёт баланса;
- распределение списаний между участниками.

Разрешённые зависимости:

- `Payment` repository/read model для исходных платежей;
- `Tables`;
- `PaidAccessCore`.

Запрещено:

- banking/gateway provider classes;
- admin/public UI;
- внешние источники данных фонда.

### `lib/Document/`

Namespace: `Zr\PaidAccess\Document`.

Назначение:

- обязательные документы;
- версии документов;
- согласия пользователей;
- repository/service для document domain.

Разрешённые зависимости:

- `Tables`;
- `PaidAccessCore`;
- Bitrix file/user APIs при необходимости.

Запрещено:

- runtime-зависимость от IBLOCK;
- payment gateway/provider classes.

Текущее отклонение: BC-алиасы `Document\DocumentConsentPageRenderer` и `Payment\PayBlockPageRenderer` остаются для обратной совместимости; реализация — в `PublicUi`.

### `lib/Admin/`

Namespace: `Zr\PaidAccess\Admin`.

Назначение:

- сервисы административных страниц;
- admin form/query orchestration;
- audit context/render helpers;
- migration services, запускаемые из админки.

Разрешённые зависимости:

- domain services/repositories;
- `Utility` для технических утилит;
- `Log`;
- Bitrix admin APIs.

Запрещено:

- использовать `Admin` из `PublicUi` как общий read-model слой;
- банковская логика provider-specific вне `Gateway`.

Сложные admin-формы:

- orchestration (load/post/redirect) — в `*AdminEditService` или аналоге;
- доменные write-операции — в `*AdminService` / domain services;
- admin page — права, assets, HTML, Bitrix UI controls.

Пример: `PaymentAdminEditService` + `admin/zr_paidaccess_payment_edit.php`.

D7 controllers (`lib/controller/`, `.settings.php`) **не используются** до отдельной задачи.

### `lib/Public/`

Namespace: `Zr\PaidAccess\PublicUi`.

Каталог называется `Public`, namespace — `PublicUi`, потому что `public` является ключевым словом PHP.

Назначение:

- view services для публичных компонентов;
- presenter/read model для шаблонов сайта;
- HTML presentation (виджеты оплаты, full-page renderers);
- hub/panel sections.

Ключевые presentation-классы:

- `PaymentWidgetPresenter` — QR СБП и кнопка оплаты (данные приходят из gateway DTO);
- `PaymentStatusPollService` — JSON-ответ для опроса статуса и редиректа после оплаты;
- `PayBlockPageRenderer`, `DocumentConsentPageRenderer` — full-page UI.

Разрешённые зависимости:

- domain services/repositories;
- `PaidAccessCore`;
- безопасные read-only helpers.

Запрещено:

- зависимости от `Zr\PaidAccess\Admin\*`;
- запись доменных данных в обход domain services;
- provider-specific gateway classes.

Общая логика для админки и публичных компонентов находится в domain/read-model сервисах (`Access\SubscriberAccessService`, `Payment\PaymentManagementService`), а не в `Admin`.

### `lib/Utility/`

Namespace: `Zr\PaidAccess\Utility`.

Назначение:

- технические утилиты;
- introspection;
- mapping/value resolver для миграций;
- registry утилит.

Разрешённые исключения:

- read-only introspection IBLOCK для миграции документов.

Запрещено:

- runtime-бизнес-логика оплаты/подписки/доступа;
- запись во внешние источники данных;
- использование Utility как свалки для доменных сервисов.

### `lib/install/`

Namespace: `Zr\PaidAccess\Install`.

Назначение:

- idempotent schema migrations;
- install/update helpers;
- managed file installer для admin/components/theme CSS/templates (`FileInstaller`);
- mail/events/agents installers.

Правила:

- installer может использовать `Option::get/set` для внутренних schema flags;
- каждый installer должен быть безопасен при повторном запуске;
- если класс зарегистрирован в `include.php`, файл должен существовать.
- классы, зарегистрированные в `include.php`, должны указывать на существующие файлы;
- `doInstall()` и `DoUpdate()` должны использовать общий `FileInstaller::ensureFiles()` для копируемых файлов;
- пользовательские templates из `/local/php_interface/zr.paidaccess/` не перезаписываются без явной политики: новая версия поставки сохраняется как `.dist`;
- регистрация событий выполняется через `EventInstaller`, а методы `InstallEvents()` / `UnInstallEvents()` в `install/index.php` остаются совместимыми wrapper-методами.

### `lib/options/`

Namespace: `Zr\PaidAccess\Options`.

Назначение:

- структура формы настроек;
- списки значений;
- provider данных для `options.php`.

Правила:

- новые пользовательские опции добавляются через `PaidAccessCore::OPTION_*`, default и getter;
- `options.php` и `Options` отвечают за UI/структуру настроек, не за бизнес-решения.

### `lib/tables/`

Namespace: `Zr\PaidAccess\Tables`.

Назначение:

- Bitrix ORM `DataManager`;
- таблицы модуля;
- `TableInstaller`.

Правила:

- в `DataManager` не размещается бизнес-логика;
- schema changes выполняются installer-классами;
- новые таблицы фиксируются в README и этом документе.

### `lib/enum/`

Namespace: `Zr\PaidAccess\Enum`.

Назначение:

- стабильные внутренние статусы/типы;
- helper-методы для статусов, если они не требуют внешних зависимостей.

### `lib/notification/`

Namespace: `Zr\PaidAccess\Notification`.

Назначение:

- отправка уведомлений;
- notification log;
- почтовые события модуля.

### `lib/log/`

Namespace: `Zr\PaidAccess\Log`.

Назначение:

- event log;
- audit log;
- cleanup/admin helpers для логов.

### `lib/tools/`

Namespace: `Zr\PaidAccess\Tools`.

Назначение:

- технические helpers: logging, request context, sanitizers.

Не использовать для доменных сервисов.

## `tools/`

Публичные endpoint/tools модуля.

Разрешённые файлы:

- `webhook.php`;
- `payment_status.php` — JSON-опрос статуса платежа для авторедиректа после оплаты;
- `verify_webhook_token.php`;
- `diagnose_tinkoff_init.php` — диагностика Init T-Bank (IP, traceroute, пробный запрос).

Правила:

- endpoint должен быть тонким entry point;
- обработка переносится в `lib/*` сервисы;
- provider-specific diagnostic tools должны быть явно названы/описаны как provider-specific.

## `tests/`

PHPUnit tests.

Разрешённая структура:

```text
tests/
├── Stubs/
├── Support/
└── Unit/
```

Правила:

- новые доменные изменения сопровождаются unit-тестами пропорционально риску;
- архитектурные правила из `STRUCTURE.md` и `BOUNDARIES.md` желательно фиксировать тестами;
- test autoload должен соответствовать production autoload или генерироваться из общего источника.

## Autoload

Production autoload:

- `autoload.production.map.php` — единый источник class map;
- `include.php` подключает map через `Loader::registerAutoLoadClasses`;
- gateway providers дополнительно обнаруживаются `GatewayProviderLoader`.

Test autoload:

- `tests/Support/ModuleClassLoader.php` = production map + `autoload.test-extra.map.php`;
- `AutoloadMapTest` проверяет существование файлов и совпадение путей.

Правила:

- каждый класс в production map должен указывать на существующий файл;
- новый класс в `lib/Gateway/Providers/{Name}` не требует ручной регистрации, если соблюдены правила provider loader;
- для остальных production-классов — запись в `autoload.production.map.php`;
- test-extra map содержит только классы, нужные PHPUnit без Bitrix Loader (provider adapters).

## Правила добавления новых файлов

1. Проверить, есть ли подходящий существующий слой.
2. Если слоя нет, сначала обновить `docs/STRUCTURE.md`.
3. Если меняются зависимости между слоями, обновить `docs/BOUNDARIES.md`.
4. Добавить класс в `include.php`, если он не попадает под provider auto-discovery.
5. Добавить или обновить tests, если файл содержит бизнес-логику или новый контракт.
6. Не добавлять зависимости от внешних источников данных без явного разрешения в `BOUNDARIES.md`.
