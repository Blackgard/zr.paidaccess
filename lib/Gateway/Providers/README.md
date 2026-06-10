# Платёжные провайдеры

Каждый банк — отдельная папка в `lib/Gateway/Providers/`.

## Как добавить Сбербанк (пример)

1. Создайте папку `Sberbank/`
2. Добавьте файлы:
    - `SberbankProvider.php` — implements `GatewayProviderInterface` (или extends `AbstractGatewayProvider`)
    - `SberbankGateway.php` — implements `PaymentGatewayInterface`
    - вспомогательные классы (ApiClient, Config, …)
3. В `SberbankProvider`:
    - `getCode()` → `'sberbank'` (код в БД `zr_paidaccess_gateway.PROVIDER`)
    - `getTitle()` → название в админке
    - `getAdminFields()` → поля для JSON OPTIONS
    - `createGateway($row)` → `return new SberbankGateway($row);`
    - при онлайн-кассе: `implements GatewayReceiptCapableInterface` + `getReceiptDeliveryInfo()`
4. Готово — **фабрику и реестр править не нужно**.

## Правила автоподключения

- Папка не должна начинаться с `_` (пример: `_example` игнорируется)
- Обязателен класс `{Папка}Provider.php` в namespace `Zr\PaidAccess\Gateway\Providers\{Папка}`
- Класс должен реализовать `GatewayProviderInterface`

## Структура Tinkoff (образец)

```
Tinkoff/
  TinkoffProvider.php      ← точка входа для реестра
  TinkoffGateway.php       ← Init / GetQr / webhook
  TinkoffApiClient.php
  TinkoffConfig.php
  TinkoffReceiptBuilder.php
  TinkoffStatusMapper.php
  TinkoffFieldOptions.php  ← списки для select в админке
```
