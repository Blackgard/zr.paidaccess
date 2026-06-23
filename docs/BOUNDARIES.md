# Границы модуля zr.paidaccess

Статус: рабочий стандарт архитектурных границ для `local/modules/zr.paidaccess/`.

Документ фиксирует, какие зависимости разрешены, какие внешние источники запрещены, где могут жить исключения и как добавлять новые платёжные интеграции.

## Главные принципы

1. Модуль разрабатывается только внутри `local/modules/zr.paidaccess/`.
2. Runtime-домен модуля использует собственные ORM-таблицы `zr_paidaccess_*`.
3. Подписка, доступ, фонд и документы не зависят от конкретного банка.
4. Платёжный gateway является boundary между доменом модуля и API банка.
5. Внешний проектный код сайта не является источником истины для домена модуля.
6. Пользовательские опции читаются через `PaidAccessCore`.
7. Admin и PublicUi не должны зависеть друг от друга; оба зависят от domain/read-model сервисов.

## Запрещённые внешние источники

В runtime-коде модуля запрещено использовать внешние источники как источник истины для оплат, подписки или доступа.

Запрещено:

- хранить доменные платежи модуля вне таблиц `zr_paidaccess_*`;
- строить подписку или доступ по данным проектных обработчиков сайта;
- использовать Sale/заказы Bitrix для доменной модели платежей модуля;
- добавлять банковскую интеграцию вне gateway provider слоя.

Разрешённые исключения:

- read-only анализ внешних данных при миграции;
- admin utility для миграции документов из IBLOCK.

Текущее разрешённое IBLOCK-исключение:

- `lib/Utility/IblockIntrospectionService.php`;
- `lib/Admin/DocumentIblockMigrationService.php`;
- `admin/zr_paidaccess_util_document_iblock.php`.

Эти файлы могут читать IBLOCK только для миграции документов. Они не должны становиться runtime-зависимостью доступа, подписки, оплат, фонда или обязательных документов.

## Собственные источники истины

Платежи:

- таблица `zr_paidaccess_payment`;
- repository/service: `Zr\PaidAccess\Payment\*`.

Подписка:

- таблица `zr_paidaccess_subscription`;
- domain: `Zr\PaidAccess\Subscription\*`.

Платёжные шлюзы:

- таблица `zr_paidaccess_gateway`;
- таблица `zr_paidaccess_gateway_transaction`;
- gateway contracts/providers: `Zr\PaidAccess\Gateway\*`.

Фонд и ledger:

- таблица `zr_paidaccess_fund`;
- таблица `zr_paidaccess_fund_movement`;
- таблица `zr_paidaccess_fund_expense_allocation`;
- domain: `Zr\PaidAccess\Fund\*`.

Документы и согласия:

- таблица `zr_paidaccess_required_document`;
- таблица `zr_paidaccess_required_document_version`;
- таблица `zr_paidaccess_document_acceptance`;
- domain: `Zr\PaidAccess\Document\*`.

Логи:

- таблица `zr_paidaccess_event_log`;
- таблица `zr_paidaccess_audit_log`;
- таблица `zr_paidaccess_notification_log`.

## Разрешённые направления зависимостей

Общее правило: внешний слой может зависеть от внутреннего, но не наоборот.

Разрешённая схема:

```text
admin pages / components / tools
    -> Admin / PublicUi / PaymentWebhookService
        -> domain services (Access, Subscription, Payment, Fund, Document)
            -> repositories / Tables
            -> Gateway contracts/factory when needed
                -> Gateway Providers/{Bank}
```

### `Access`

Может зависеть от:

- `PaidAccessCore`;
- `Subscription`;
- `Payment`;
- `Document`;
- `Tables`/Bitrix user APIs.

Не может зависеть от:

- `Gateway\Providers\*`;
- bank API clients;
- `Admin`;
- `PublicUi`;
- внешних источников оплат.

### `Subscription`

Может зависеть от:

- `PaidAccessCore`;
- `Payment` read services/repositories;
- `Tables`;
- `Notification` для доменных уведомлений.

Не может зависеть от:

- `Gateway\Providers\*`;
- bank API clients;
- `Admin`;
- `PublicUi`;
- внешних источников.

### `Payment`

Может зависеть от:

- `Gateway` contracts/factory/repository;
- DTO из `Gateway\Dto`;
- `Subscription`;
- `Fund`;
- `Notification`;
- `Tables`.

Не может зависеть от:

- `Gateway\Providers\Tinkoff\*`;
- `Gateway\Providers\{AnyBank}\*`;
- provider-specific status mappers, URL resolvers, error parsers;
- внешних payment endpoints/tables.

Новая банковская логика вне `Gateway/Providers` запрещена. Общие payment-сервисы работают только с `Gateway` contracts/capabilities и DTO.

### `Gateway`

Может зависеть от:

- `PaymentStatus` enum;
- gateway DTO/contracts;
- `GatewayTable` / `GatewayTransactionTable`;
- `Logger`/`RequestContext`;
- provider-specific API clients внутри `Providers/{Bank}`.

Не может:

- открывать доступ пользователю напрямую;
- активировать подписку напрямую;
- писать движения фонда напрямую;
- обращаться к внешним payment sources;
- хранить секреты в PHP-файлах;
- читать пользовательские опции модуля (`PaidAccessCore::getPaymentWidgetMode` и аналоги) для UI-режима оплаты.

Режим виджета (`qr_sbp` / `payment_button`) передаётся в `InitPaymentRequest::paymentWidgetMode` из Payment-слоя с корректным `siteId`.

Gateway возвращает результат, а доменное применение результата выполняют `Payment`, `Subscription`, `Fund` и `Access`.

### `Fund`

Может зависеть от:

- `Payment` read model/repository;
- `Tables`;
- `PaidAccessCore`.

Не может зависеть от:

- конкретных банков;
- gateway provider classes;
- `Admin`;
- `PublicUi`;
- внешних IBLOCK-источников.

### `Document`

Может зависеть от:

- `Tables`;
- `PaidAccessCore`;
- Bitrix file/user APIs.

Не может зависеть от:

- payment gateways;
- IBLOCK в runtime.
- `Admin`;
- `PublicUi`.

Исключение: миграция из IBLOCK находится не в runtime `Document`, а в `Admin/Utility`.

### `Admin`

Может зависеть от:

- domain services/repositories;
- `Utility`;
- `Log`;
- Bitrix admin APIs.

Не может быть зависимостью для:

- `PublicUi`;
- domain services;
- gateway providers.

Если логика нужна и в админке, и на публичной стороне, она выносится в domain/read-model service, а не импортируется из `Admin`.

### `PublicUi`

Может зависеть от:

- domain services/repositories;
- `PaidAccessCore`;
- read-model/presenter helpers, не привязанных к админке.

Не может зависеть от:

- `Zr\PaidAccess\Admin\*`;
- Bitrix admin UI classes;
- gateway providers;
- direct write operations в обход domain services.

Общие read-model/action сервисы для админки и публичного UI:

- `Access\SubscriberAccessService` — статусы доступа, загрузка подписок/платежей пользователей, display period;
- `Payment\PaymentManagementService` — общие платежные фильтры, labels и ручные операции над платежом.

### `Utility`

Может зависеть от:

- Bitrix APIs, если это техническая introspection/migration задача;
- domain value objects/helpers, если утилита не меняет runtime-инварианты.

Не может:

- становиться местом для бизнес-логики оплаты, подписки, доступа, фонда;
- писать во внешние источники данных;
- использоваться как обход слоя `Admin`/`PublicUi`.

## Платёжные шлюзы

Новый банк добавляется только как provider + gateway:

```text
lib/Gateway/Providers/{Name}/
├── {Name}Provider.php
├── {Name}Gateway.php
├── {Name}ApiClient.php
├── {Name}Config.php
└── {Name}StatusMapper.php
```

При добавлении банка нельзя править:

- `Access`;
- `Subscription`;
- `Fund`;
- проектные обработчики сайта;
- `GatewayFactory` / `GatewayProviderRegistry`, если provider discovery уже подходит.

Можно править общие gateway contracts только если новая возможность действительно общая:

- отмена/возврат через API;
- duplicate order recovery;
- receipt capability;
- особый webhook OK response;
- provider-specific test mode normalization.

Такие возможности оформляются как contract/capability, например:

- `GatewayCancellableInterface`;
- `DuplicateOrderRecoverableGatewayInterface`;
- `GatewayPaymentUrlExtractorInterface`;
- provider hook для нормализации options.

## Webhook boundary

Endpoint:

```text
tools/webhook.php?id={gateway_id}
```

Правила:

- endpoint только принимает HTTP-запрос и делегирует в сервис;
- подпись/HMAC/token проверяет gateway provider;
- соответствие terminal/shop id проверяет gateway provider;
- bank status -> `PaymentStatus` маппится внутри provider;
- общий payment layer работает с `WebhookHandleResult`, а не с raw bank fields;
- повторный webhook должен быть идемпотентным;
- gateway не активирует подписку, не открывает доступ и не пишет фонд напрямую.

Запрещено:

- использовать `TinkoffStatusMapper` или другой provider mapper в `Payment`;
- искать платёж по provider-specific raw fields, если эти поля уже нормализованы в DTO;
- добавлять special case для банка в `PaymentWebhookService`.

## Опции модуля

Пользовательские настройки:

- константа `PaidAccessCore::OPTION_*`;
- default value в `PaidAccessCore` / `default_option.php`;
- getter в `PaidAccessCore`;
- поле в `options.php` через `ModuleOptionsProvider` / `ModuleOptionsStructure`.

Запрещено:

- прямой `Option::get` в обработчиках доступа, подписки, платежей, фонда, документов;
- ad-hoc option keys без констант для пользовательских настроек;
- хранение секретов банка в git.

Разрешённые исключения:

- `PaidAccessCore` как единая точка чтения пользовательских опций;
- `options.php` как форма настроек;
- installer classes для schema flags/backfill flags.

Правила для миграционных утилит:

- миграционные формы не должны сохранять временное состояние в `Option`;
- если миграционной утилите всё же нужен persistent state, сначала нужно описать storage и срок жизни данных в этом документе;
- `DocumentIblockMigrationService` работает stateless: iblock и mapping берутся из текущего request.

## Секреты и внешние сервисы

Секреты:

- не хранить terminal key, secret key, passwords, tokens в PHP, README, docs, tests snapshots;
- секреты gateway вводятся через admin UI и хранятся в `zr_paidaccess_gateway.OPTIONS`;
- логи должны маскировать token/password/secret/authorization/terminal key и аналогичные поля.

Внешние HTTP-зависимости:

- банковские API вызываются только из provider API clients;
- любая внешняя зависимость не банка должна быть описана в docs/README и иметь fallback или обоснование отсутствия fallback.

### QR image rendering (api.qrserver.com)

Статус: **принятая внешняя зависимость** (локальная генерация QR-изображений не используется).

Когда применяется:

- опция `PAYMENT_WIDGET_MODE=qr_sbp` (режим по умолчанию);
- provider `TinkoffGateway::fetchQrForm()` получает payload СБП от T-Bank (`GetQr`);
- HTML-виджет строится в `PublicUi\PaymentWidgetPresenter` через `<img src="...">`.

Сервис:

- URL: `https://api.qrserver.com/v1/create-qr-code/`;
- параметры: `size=280x280`, `data={urlencoded SBP payload}`;
- константа в коде: `PaymentWidgetPresenter::QR_IMAGE_SERVICE_URL`.

Кто обращается к сервису:

- **браузер пользователя**, а не PHP-сервер модуля: `<img>` загружается клиентом при открытии страницы оплаты;
- сервер модуля к `api.qrserver.com` не ходит.

Данные:

- в query string `data` передаётся SBP payload, полученный от T-Bank;
- payload уходит третьей стороне (QR Server / goQR.me) — это осознанный trade-off вместо локальной генерации изображения.

Поведение при недоступности:

- отдельного server-side fallback нет;
- при недоступности `api.qrserver.com` пользователь увидит битую картинку QR, оплата через СБП станет недоступна до восстановления сервиса или смены режима на `payment_button`.

Альтернатива без внешнего QR-сервиса:

- `PAYMENT_WIDGET_MODE=payment_button` — кнопка перехода на платёжную форму T-Bank, без `api.qrserver.com`.

Смена сервиса или локальная генерация — отдельная задача: обновить `PaymentWidgetPresenter`, README, этот раздел и тест `ModuleBoundaryTest::testQrImageServiceIsDocumented`.

## Установка и обновление

Правила:

- `doInstall()` и `DoUpdate()` должны быть идемпотентны;
- schema migrations живут в `lib/install/*Installer.php`;
- файлы, копируемые в `/bitrix/admin/`, `/local/components/zr/`, `/bitrix/themes/`, должны обновляться согласованно;
- пользовательски изменяемые шаблоны из `/local/php_interface/zr.paidaccess/` нельзя тихо перезатирать без политики backup/diff.

Текущая политика:

- managed-файлы обновляются через `Install\FileInstaller::ensureFiles()` и при установке, и при обновлении;
- admin pages, components и theme CSS перезаписываются из поставки модуля;
- активные пользовательские templates в `/local/php_interface/zr.paidaccess/` не перезаписываются, а новая версия поставки сохраняется рядом как `.dist`, если файл был изменён.

## Admin stubs и путь установки

Основной поддерживаемый путь установки:

```text
local/modules/zr.paidaccess/
```

Если требуется поддержка `bitrix/modules/zr.paidaccess/`, нужно:

- убрать hardcoded `/local/modules/` из `install/admin/*.php`;
- обновить installer;
- зафиксировать новый путь в `STRUCTURE.md` и README.

До отдельной задачи модуль считается local-only.

## Тестовые границы

Архитектурные правила желательно закрепить тестами:

- `docs/STRUCTURE.md` и `docs/BOUNDARIES.md` существуют;
- все файлы из `include.php` существуют;
- `lib/Public` не импортирует `Zr\PaidAccess\Admin`;
- `lib/payment`, `lib/subscription`, `lib/access`, `lib/Fund`, `lib/Document` не импортируют `Gateway\Providers`;
- `CIBlock*` используется только в разрешённых migration utility файлах;
- `Option::get` вне `PaidAccessCore`, installers, `options.php` и разрешённых storage-классов запрещён;
- gateway providers не импортируют `Subscription`, `Access`, `Fund` для изменения доменного состояния.

## Как менять границы

1. Описать причину изменения в задаче/ТЗ.
2. Обновить `STRUCTURE.md` и/или `BOUNDARIES.md`.
3. Добавить tests на новое правило, если оно проверяемо автоматически.
4. Только после этого менять PHP-код.

Если изменение нужно временно, оно фиксируется как "текущее отклонение" с планом удаления.
