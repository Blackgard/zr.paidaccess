# Платёжные провайдеры

Этот каталог содержит адаптеры эквайрингов для модуля `zr.paidaccess`.
Каждый банк живёт в отдельной папке `lib/Gateway/Providers/{Name}/` и подключается
через общий контракт, чтобы доменная логика подписки, доступа и фонда не зависела
от конкретного API банка.

Главный принцип: **новый эквайринг добавляется как новый provider + gateway**.
Не нужно править оплату подписки, блокировку доступа, ledger фонда или старые
legacy-обработчики сайта.

## Что уже делает модуль

Платёжный поток модуля выглядит так:

1. `SubscriptionPaymentService` создаёт или находит платёж в таблице модуля.
2. `GatewayFactory` берёт активный шлюз из `zr_paidaccess_gateway`.
3. `GatewayProviderRegistry` создаёт нужный gateway по полю `PROVIDER`.
4. Gateway вызывает API банка: Init / создание счёта / создание платежа.
5. Модуль сохраняет `GATEWAY_PAYMENT_ID` и, если есть, `GATEWAY_PAYMENT_URL`.
6. Gateway отдаёт HTML для пользователя: QR СБП, кнопку оплаты или другой виджет.
7. Webhook банка приходит в `tools/webhook.php?id={gateway_id}`.
8. Gateway проверяет подпись webhook и маппит банковский статус во внутренний
   статус `PaymentStatus`.
9. Дальше модуль сам открывает доступ, закрывает pending-платёж и пишет движение
   фонда через ledger.

## Минимальная структура нового банка

Пример для условного Сбербанка:

```text
Sberbank/
  SberbankProvider.php      # метаданные провайдера, поля админки, фабрика gateway
  SberbankGateway.php       # Init / виджет оплаты / webhook
  SberbankApiClient.php     # HTTP-клиент банка
  SberbankConfig.php        # чтение OPTIONS шлюза
  SberbankStatusMapper.php  # маппинг статусов банка в PaymentStatus
```

Можно добавить дополнительные классы, если API банка требует отдельного builder для
чека, ссылок, токенов или подписи. Ориентир по текущей реализации — папка `Tinkoff/`.

## Правила автоподключения

Провайдеры обнаруживаются автоматически через `GatewayProviderLoader`.

- Папка провайдера должна лежать прямо в `lib/Gateway/Providers/`.
- Папка не должна начинаться с `_`; `_example` намеренно игнорируется.
- Имя папки и provider-класса должны совпадать: `Sberbank/SberbankProvider.php`.
- Namespace должен быть `Zr\PaidAccess\Gateway\Providers\Sberbank`.
- Provider-класс должен реализовать `GatewayProviderInterface`; обычно проще
  наследоваться от `AbstractGatewayProvider`.
- Код провайдера из `getCode()` сохраняется в `zr_paidaccess_gateway.PROVIDER`.
- Фабрику и реестр руками править не нужно.

Если provider не появился в админке, сначала проверьте имя папки, namespace, имя
файла и отсутствие подчёркивания в начале папки.

## Provider-класс

Provider отвечает за то, как банк выглядит в админке и как создать runtime-gateway.

Минимальный пример:

```php
<?php

namespace Zr\PaidAccess\Gateway\Providers\Sberbank;

use Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface;
use Zr\PaidAccess\Gateway\Provider\AbstractGatewayProvider;

class SberbankProvider extends AbstractGatewayProvider
{
    public function getCode(): string
    {
        return 'sberbank';
    }

    public function getTitle(): string
    {
        return 'Сбербанк';
    }

    public function getAdminFields()
    {
        return [
            [
                'code' => 'user_name',
                'title' => 'Логин API',
                'type' => 'text',
                'required' => true,
                'default' => '',
            ],
            [
                'code' => 'password',
                'title' => 'Пароль API',
                'type' => 'text',
                'required' => true,
                'default' => '',
            ],
            [
                'code' => 'test_mode',
                'title' => 'Тестовый режим',
                'type' => 'checkbox',
                'default' => 'Y',
            ],
        ];
    }

    public function createGateway(array $gatewayRow): PaymentGatewayInterface
    {
        return new SberbankGateway($gatewayRow);
    }
}
```

`AbstractGatewayProvider` уже умеет:

- собирать значения по умолчанию из `getAdminFields()`;
- валидировать обязательные поля с `required => true`;
- возвращать стандартный успешный ответ webhook в JSON.

Если банк требует другой ответ на webhook, переопределите:

```php
public function getWebhookOkContentType(): string
{
    return 'text/plain; charset=utf-8';
}

public function getWebhookOkBody(): string
{
    return 'OK';
}
```

## Поля настроек в админке

`getAdminFields()` описывает JSON `OPTIONS` записи шлюза. Эти значения вводятся в
админке на странице редактирования платёжного шлюза и передаются в gateway через
`$gatewayRow`.

Используемые типы:

- `text` — обычное текстовое поле;
- `checkbox` — флаг, обычно `Y` / `N`;
- `select` — выпадающий список, нужен массив `values`;
- `note` — поясняющий текст, не попадает в default options.

Полезные ключи поля:

- `code` — ключ в JSON `OPTIONS`;
- `title` — подпись в админке;
- `required` — обязательность для базовой валидации;
- `default` — значение по умолчанию;
- `values` — варианты для `select`;
- `show_if` — условный показ поля в админке.

Секреты банка не должны попадать в git. Они хранятся в настройках конкретного
шлюза в БД, а в коде должны быть только названия полей и безопасные значения по
умолчанию.

## Gateway-класс

Gateway реализует `PaymentGatewayInterface`:

```php
public function getCode(): string;

public function initPayment(InitPaymentRequest $request): InitPaymentResult;

public function fetchPaymentForm(
    string $gatewayPaymentId,
    InitPaymentRequest $request
): InitPaymentResult;

public function handleWebhook(array $payload): WebhookHandleResult;
```

### `initPayment()`

Создаёт платёж в банке. Аналог у разных банков может называться `Init`,
`register`, `createInvoice`, `createPayment` и т.п.

На вход приходит `InitPaymentRequest`:

- `orderId` — внутренний номер заказа модуля, его нужно передать банку;
- `amount` — сумма в рублях;
- `currency` — валюта, сейчас обычно `RUB`;
- `description` — назначение платежа;
- `userId` — пользователь Bitrix;
- `email` / `phone` — контакты пользователя, если доступны;
- `paymentUrl` — уже сохранённая ссылка, если Init выполнялся раньше;
- `paymentWidgetMode` — режим UI (`qr_sbp` / `payment_button`); задаёт вызывающий Payment-слой, gateway не читает `PaidAccessCore`.

На успехе верните `InitPaymentResult` с:

- `success = true`;
- `gatewayPaymentId` — ID платежа в банке;
- `paymentUrl` — ссылка на оплату, если банк возвращает её сразу;
- `rawResponse` — сырой ответ банка в JSON-строке для диагностики.

На ошибке используйте:

```php
return InitPaymentResult::fail('Понятная ошибка для админки/лога', $rawResponse);
```

### `fetchPaymentForm()`

Возвращает данные для отображения формы оплаты (`InitPaymentResult`).

Перед вызовом вызывающий слой (`SubscriptionPaymentService`, `GatewayTestService`) должен
заполнить `$request->paymentWidgetMode` из `PaidAccessCore::getPaymentWidgetMode($siteId)`.
Gateway-адаптер не читает опции модуля напрямую.

HTML для пользователя собирает `PublicUi\PaymentWidgetPresenter::renderFromResult()` —
gateway не должен возвращать готовую разметку.

Допустимые поля результата:

- `qrPayload` — payload СБП для QR;
- `paymentUrl` — ссылка на платёжную форму банка;
- `autoRedirectPaymentButton` — автопереход на `paymentUrl` (режим кнопки);
- `html` — только для обратной совместимости; production-path Tinkoff оставляет пустым.

Для Tinkoff в режиме QR СБП картинка строится через внешний image service `api.qrserver.com`
(запрос выполняет браузер пользователя, см. `docs/BOUNDARIES.md`). Локальная генерация
QR-изображения в модуле не используется.

Если банк не поддерживает QR СБП, можно вернуть кнопку оплаты. Если банк сначала
создаёт invoice, а ссылку отдаёт отдельным методом, вызывайте этот метод здесь.

Важно: метод может быть вызван повторно для уже созданного платежа. По возможности
используйте сохранённый `$request->paymentUrl` или `gatewayPaymentId`, чтобы не
создавать дубль платежа в банке без необходимости.

### `handleWebhook()`

Разбирает callback банка и возвращает `WebhookHandleResult`.

Gateway обязан:

1. Проверить подпись / токен / HMAC webhook.
2. Проверить, что callback относится к текущему шлюзу, если банк присылает
   идентификатор терминала или магазина.
3. Достать исходный `orderId`, банковский `gatewayPaymentId` и сырой статус банка.
4. Преобразовать банковский статус во внутренний `PaymentStatus`.
5. Вернуть `valid = false` и понятную ошибку, если подпись или данные неверные.

Внутренние статусы:

- `PaymentStatus::PENDING` — платёж создан, но не оплачен;
- `PaymentStatus::AUTHORIZED` — деньги авторизованы, но полная оплата ещё не
  подтверждена;
- `PaymentStatus::PAID` — платёж подтверждён, доступ можно открыть;
- `PaymentStatus::REFUNDED` — возврат;
- `PaymentStatus::FAILED` — ошибка оплаты;
- `PaymentStatus::CANCELLED` — отмена.

Только `PAID` открывает доступ на сайте. `AUTHORIZED` считается промежуточным
состоянием и не должен открывать доступ, если банк не гарантирует списание.

## Config и OPTIONS

Для каждого банка удобно сделать `SberbankConfig`, который принимает массив
опций шлюза:

```php
$options = GatewayRepository::getOptionsForGateway($gatewayRow);
$this->config = new SberbankConfig($options);
```

В config-классе держите:

- чтение логина, пароля, terminal/shop id;
- выбор тестового или боевого API URL;
- флаги QR, redirect, чеков и других возможностей банка;
- нормализацию пустых значений.

Не читайте `Option::get` напрямую из gateway. Настройки конкретного эквайринга
должны приходить из `zr_paidaccess_gateway.OPTIONS`.

## HTTP-клиент банка

HTTP-клиент лучше держать отдельно от gateway:

- gateway знает контракты модуля и DTO;
- api client знает URL, формат подписи, HTTP-методы и ошибки банка;
- status mapper знает только соответствие статусов.

Так проще тестировать и менять API банка, не трогая общий платёжный поток.

Если пишете логи HTTP-обмена, маскируйте секреты: token, password, secret,
terminal key, authorization headers и похожие поля.

## Онлайн-касса и чеки

Если эквайринг умеет формировать фискальные чеки через свою онлайн-кассу,
provider может реализовать `GatewayReceiptCapableInterface`:

```php
use Zr\PaidAccess\Gateway\Contract\GatewayReceiptCapableInterface;
use Zr\PaidAccess\Gateway\Dto\ReceiptDeliveryInfo;

class SberbankProvider extends AbstractGatewayProvider implements GatewayReceiptCapableInterface
{
    public function getReceiptDeliveryInfo(array $gatewayOptions, ?string $customerEmail = null): ReceiptDeliveryInfo
    {
        return new ReceiptDeliveryInfo(
            $this->getCode(),
            true,
            ReceiptDeliveryInfo::ISSUER_GATEWAY,
            $customerEmail !== null && $customerEmail !== '',
            'Фискальный чек формирует банк при успешной оплате.'
        );
    }
}
```

Модуль сам фискальные чеки не выбивает. Он только передаёт данные в gateway, если
конкретный банк это поддерживает.

## Webhook URL

Для любого банка endpoint один:

```text
https://{домен}/local/modules/zr.paidaccess/tools/webhook.php?id={ID шлюза}
```

`id` — ID записи в `zr_paidaccess_gateway`, а не код провайдера. Благодаря этому
на одном сайте можно держать несколько шлюзов с разными настройками.

Успешное тело ответа и content type берутся из provider:

- по умолчанию JSON `{"success": true}`;
- для T-Bank переопределено `text/plain` + `OK`.

## Тестовый платёж

Админка умеет создавать тестовый платёж шлюза через `GatewayTestService`.
Чтобы новый банк работал с этой проверкой, достаточно корректно реализовать:

- `initPayment()` — создать платёж в банке;
- `fetchPaymentForm()` — вернуть `qrPayload` / `paymentUrl` (или непустой `html` для legacy);
- `handleWebhook()` — вернуть `PaymentStatus::PAID` или `AUTHORIZED` на успешный
  callback банка.

Тестовые платежи имеют служебный период `GT` и не попадают в ledger фонда.

## Чеклист добавления нового эквайринга

1. Скопировать `_example` или создать новую папку `Providers/{Name}/`.
2. Создать `{Name}Provider.php` с namespace
   `Zr\PaidAccess\Gateway\Providers\{Name}`.
3. Задать стабильный `getCode()` латиницей в нижнем регистре, например
   `sberbank`.
4. Описать поля `getAdminFields()` без секретов в коде.
5. Создать `{Name}Gateway.php` и реализовать `PaymentGatewayInterface`.
6. Вынести HTTP-запросы в `{Name}ApiClient.php`.
7. Вынести чтение настроек в `{Name}Config.php`.
8. Вынести маппинг статусов в `{Name}StatusMapper.php`.
9. Реализовать проверку подписи webhook в gateway/api client.
10. Вернуть внутренние статусы только из `PaymentStatus`.
11. Если нужны чеки, реализовать `GatewayReceiptCapableInterface`.
12. Создать шлюз в админке и выбрать нового provider.
13. Заполнить настройки, включить тестовый режим, сделать тестовый платёж.
14. Прописать webhook URL в личном кабинете банка.
15. Проверить оплату, повторный webhook и ошибочную подпись.

## Что не трогать при добавлении банка

- Не использовать legacy-инфоблоки оплат, `payments/check.php`, `api/tinkoff/*`
  или таблицу `b_payments`.
- Не добавлять банковскую логику в `Subscription`, `Access` или `Fund`.
- Не править `GatewayFactory` и `GatewayProviderRegistry` без отдельной причины.
- Не хранить секреты в PHP-файлах, README или миграциях.
- Не открывать доступ напрямую из gateway: gateway только возвращает результат,
  а доступ и ledger обрабатывают сервисы модуля.

## Частые ошибки

- Provider не появился в админке: неверное имя папки, namespace или имя класса.
- Webhook приходит, но платёж не находится: банк не возвращает исходный `OrderId`
  или gateway неверно заполняет `WebhookHandleResult::orderId`.
- Доступ открылся слишком рано: банковский статус авторизации ошибочно замаплен в
  `PaymentStatus::PAID`.
- Тестовый платёж создался, но форма пустая: `fetchPaymentForm()` вернул успех без
  `html`.
- Повторный webhook меняет данные некорректно: статус должен быть идемпотентным,
  а gateway не должен создавать новые платежи при обработке callback.
