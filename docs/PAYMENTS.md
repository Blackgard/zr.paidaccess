# Платежи и T-Bank

## Платёжный поток

```text
Пользователь без оплаты
    -> AccessBlockHandler
    -> шаблон оплаты
    -> SubscriptionPaymentService::preparePayment()
    -> TinkoffGateway::initPayment()
    -> PaymentWidgetPresenter::renderFromResult()

Оплата в банке
    -> webhook.php?id={gateway_id}
    -> PaymentWebhookService
    -> TinkoffGateway::handleWebhook()
    -> PaymentCompletionService
    -> SubscriptionService
    -> FundMovementService::tryRecordPaymentIncome()
```

Gateway возвращает данные (`qrPayload`, `paymentUrl`, `autoRedirectPaymentButton`), а HTML собирает `PublicUi\PaymentWidgetPresenter`.

## Способы оплаты

| Режим            | Поведение                                                                                   |
| ---------------- | ------------------------------------------------------------------------------------------- |
| `qr_sbp`         | QR СБП. Payload приходит от T-Bank, QR-картинку загружает браузер через `api.qrserver.com`. |
| `payment_button` | Кнопка перехода на платёжную форму T-Bank.                                                  |

Если QR-картинка не отображается из-за внешнего сервиса, переключите режим на `payment_button`.

## Настройка шлюза

Раздел админки: `/bitrix/admin/zr_paidaccess_gateways.php`.

В карточке шлюза указываются:

- `TerminalKey`;
- `SecretKey`;
- тестовый или боевой режим;
- флаг **Использовать по умолчанию**;
- настройки чека 54-ФЗ, если они нужны;
- `Notification URL` для личного кабинета T-Bank.

Webhook URL:

```text
https://{domain}/local/modules/zr.paidaccess/tools/webhook.php?id={gateway_id}
```

## Статусы

| Статус       | Доступ                               |
| ------------ | ------------------------------------ |
| `pending`    | закрыт                               |
| `authorized` | закрыт, списание ещё не подтверждено |
| `paid`       | открыт                               |
| `failed`     | закрыт                               |
| `refunded`   | закрыт                               |
| `cancelled`  | закрыт                               |

При одностадийной оплате T-Bank может отправить `AUTHORIZED`, затем `CONFIRMED`. Доступ открывается после подтверждения оплаты.

## Дубликат `order_id`

Опция `PAYMENT_DUPLICATE_ORDER_POLICY` управляет поведением при ошибке банка о существующем заказе.

| Значение | Поведение                                                         |
| -------- | ----------------------------------------------------------------- |
| `fail`   | платёж переводится в статус ошибки                                |
| `ignore` | платёж остаётся в ожидании, событие пишется в журнал              |
| `reuse`  | модуль запрашивает существующий платёж и привязывает его к записи |

## Тестовый платёж

Тестирование запускается из карточки шлюза. Такие платежи получают служебный период `GT` и не попадают в фондовый ledger.

## Диагностика webhook

```bash
php local/modules/zr.paidaccess/tools/verify_webhook_token.php --gateway-id=1 --file=webhook.json
```

Журнал обмена с gateway доступен в `/bitrix/admin/zr_paidaccess_logs.php`.
