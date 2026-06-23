# Разработка и архитектура

## Быстрый старт

```bash
cd local/modules/zr.paidaccess
composer install && npm install
composer test
composer format
composer format:assets
```

## Архитектурные документы

- `docs/STRUCTURE.md` — разрешённая структура каталогов и namespaces.
- `docs/BOUNDARIES.md` — границы слоёв, разрешённые зависимости, правила gateway.
- `lib/Gateway/Providers/README.md` — добавление нового платёжного провайдера.

## Autoload

Production:

- `autoload.production.map.php` — единый class map;
- `include.php` подключает map через `Loader::registerAutoLoadClasses`;
- gateway providers дополнительно обнаруживаются `GatewayProviderLoader`.

Tests:

- `tests/Support/ModuleClassLoader.php` = production map + `autoload.test-extra.map.php`;
- `AutoloadMapTest` проверяет существование файлов и совпадение путей.

## Слои

```text
admin pages / components / tools
    -> Admin / PublicUi / PaymentWebhookService
        -> domain services (Access, Subscription, Payment, Fund, Document)
            -> repositories / Tables
            -> Gateway contracts/factory when needed
                -> Gateway Providers/{Bank}
```

## Основные правила

1. Платёжный gateway является boundary между доменом модуля и API банка.
2. Подписка, доступ, фонд и документы не зависят от конкретного банка.
3. `PaidAccessCore` отвечает только за константы и чтение опций.
4. `PublicUi` не зависит от `Admin`.
5. Gateway providers не открывают доступ, не активируют подписку и не пишут фонд напрямую.
6. HTML виджета оплаты собирается в `PublicUi\PaymentWidgetPresenter`.
7. Новые production-классы добавляются в `autoload.production.map.php`, кроме provider-классов, которые подхватываются loader-ом.
8. Новые пользовательские опции добавляются через `PaidAccessCore::OPTION_*`, default value, getter и поле в `options.php`.

## Тесты

Актуальный прогон: **231 test / 748 assertions**.

Покрытие:

- биллинг и календарь оплаты;
- платежи, статусы, cancellation/completion;
- T-Bank API/token/webhook;
- фонд и распределение списаний;
- обязательные документы и согласия;
- public presenters;
- admin form orchestration;
- architecture boundaries;
- autoload maps.

## Форматирование

PHP:

```bash
composer format
composer format:check
```

Markdown, JSON, CSS:

```bash
composer format:assets
```

Все проверки:

```bash
composer format:all
composer test
```
