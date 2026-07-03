# kib-ev/webkassa-api

PHP-библиотека для работы с API [WebKassa](https://webkassa.by) (АИС ПКС «Цифровые Кассы», Беларусь).

Репозиторий: [github.com/kib-ev/webkassa-api](https://github.com/kib-ev/webkassa-api)

Поддерживает standalone-использование и интеграцию с **Laravel** (auto-discovery, config, Facade, Artisan-команда).

## Требования

- PHP 8.1+
- ext-curl, ext-json
- JWT-токен из личного кабинета WebKassa
- Запросы только с IP, привязанного к ключу

## Установка

### В Laravel-проект

```bash
composer require kib-ev/webkassa-api
```

Опубликуйте конфиг:

```bash
php artisan vendor:publish --tag=webkassa-config
```

`.env`:

```env
WEBKASSA_TOKEN=your-jwt-token
WEBKASSA_MERCHANT_NAME='ООО "ЭкоСпеции"'
WEBKASSA_MERCHANT_UNP=192683473
WEBKASSA_TIMEZONE=Europe/Minsk
WEBKASSA_EPOS_DEFAULT_SERVICE=Аренда
```

### Локально / path repository

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../webkassa/webkassa-api"
        }
    ],
    "require": {
        "kib-ev/webkassa-api": "*"
    }
}
```

Или из GitHub:

```bash
composer require kib-ev/webkassa-api
```

### Standalone (без Laravel)

```bash
cd webkassa-api
composer install
export WEBKASSA_TOKEN=...
php bin/export-epos --month=2026-06
```

Файл `.env` можно положить в `webkassa-api/` или в родительскую папку `webkassa/`.

## Laravel

### Facade

```php
use WebKassa\Facades\WebKassa;
use DateTimeImmutable;

$payments = WebKassa::eposPayReport(
    new DateTimeImmutable('2026-06-01'),
    new DateTimeImmutable('2026-06-30'),
);

$count = WebKassa::exportEposReport(
    new DateTimeImmutable('2026-06-01'),
    new DateTimeImmutable('2026-06-30'),
    storage_path('app/webkassa/2026-06.xlsx'),
);
```

### Dependency Injection

```php
use WebKassa\WebKassaManager;
use WebKassa\Contracts\WebKassaClientInterface;

public function __construct(
    private readonly WebKassaManager $webkassa,
) {}

public function report(): array
{
    return $this->webkassa->eposPayReport(
        new \DateTimeImmutable('first day of this month'),
        new \DateTimeImmutable('last day of this month'),
    );
}
```

### Artisan

```bash
php artisan webkassa:export-epos --month=2026-06
php artisan webkassa:export-epos --from=2026-06-01 --to=2026-06-30 --output=storage/app/2026-06.xlsx
```

## API (низкий уровень)

```php
use WebKassa\Config\WebKassaConfig;
use WebKassa\WebKassaManager;

$config = WebKassaConfig::fromArray([
    'token' => '...',
    'merchant' => ['name' => '...', 'unp' => '...'],
]);

$webkassa = new WebKassaManager($config);

// POST /epos-report/pay-report
$payments = $webkassa->eposPayReport($from, $to);

// POST /epos-invoice/list
$invoices = $webkassa->eposInvoices();

// Произвольный endpoint
$webkassa->client()->post('get-check-history', []);
```

## Excel-отчёт e-pos

Метод `exportEposReport()` формирует xlsx в формате личного кабинета («Отчёт по платежам e-pos (Общий)»), совместимый с пакетной загрузкой в 1С.

## Структура пакета

```
src/
  Client/WebKassaClient.php      HTTP-клиент API
  Config/WebKassaConfig.php      Конфигурация
  Contracts/                     Интерфейсы для DI
  Console/ExportEposCommand.php  Artisan-команда
  Export/EposReportExporter.php  Выгрузка в Excel
  Facades/WebKassa.php           Laravel Facade
  Support/PeriodResolver.php     Разбор периода
  WebKassaManager.php            Основной фасад SDK
  WebKassaServiceProvider.php    Laravel provider
config/webkassa.php
bin/export-epos                  CLI без Laravel
```

## Ошибки

```php
use WebKassa\Exceptions\WebKassaApiException;

try {
    WebKassa::client()->post('...');
} catch (WebKassaApiException $e) {
    $e->statusCode();
    $e->responseBody();
}
```

## Лицензия

MIT
