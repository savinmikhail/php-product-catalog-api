# Product Catalog API

REST API для каталога товаров и категорий. Проект реализован на plain PHP без
фреймворка и подготовлен к запуску в Docker Compose.

## Стек

- PHP 8.3-FPM и Composer 2;
- Nginx 1.27;
- MySQL 8.4 для транзакционного хранения каталога;
- Elasticsearch 8.15.3 для поиска и чтения товаров;
- PHPUnit 11 для тестов.

## Быстрый старт

Требования: Docker с Docker Compose v2.

```sh
cp .env.example .env
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist
docker compose up --build -d
curl http://localhost:8080/health
```

Ожидаемый ответ health-check:

```json
{"data":{"status":"ok"}}
```

Проверка приложения и тестов:

```sh
docker compose exec app composer check
docker compose exec app composer test
```

Порты можно изменить в `.env`: по умолчанию API доступен на `8080`, MySQL —
на `3307`, Elasticsearch — на `9200` на хосте.

## Конфигурация

Скопируйте `.env.example` в `.env`. Файл `.env` не должен коммититься.

| Переменная | Назначение | Значение по умолчанию |
| --- | --- | --- |
| `APP_ENV` | окружение приложения | `production` |
| `APP_DEBUG` | флаг debug, сейчас не меняет формат API-ответов | `0` |
| `APP_PORT` | порт Nginx на хосте | `8080` |
| `DB_HOST` | адрес MySQL внутри Compose | `mysql` |
| `DB_PORT` | порт MySQL внутри Compose | `3306` |
| `DB_DATABASE` | имя базы | `catalog` |
| `DB_USERNAME` | пользователь базы | `catalog` |
| `DB_PASSWORD` | пароль пользователя базы | `catalog` |
| `DB_ROOT_PASSWORD` | пароль root MySQL | `root` |
| `DB_FORWARD_PORT` | порт MySQL на хосте | `3307` |
| `DADATA_API_URL` | endpoint DaData suggestions API | endpoint из `.env.example` |
| `DADATA_API_TOKEN` | секретный токен DaData | обязательно для create/update товара |
| `DADATA_HTTP_TIMEOUT` | timeout запроса к DaData в секундах | `3` |
| `DADATA_INN_CACHE_TTL` | TTL in-memory кэша проверки ИНН | `3600` |
| `ELASTICSEARCH_URL` | адрес Elasticsearch внутри Compose | `http://elasticsearch:9200` |
| `ELASTICSEARCH_INDEX` | имя индекса товаров | `products` |
| `ELASTICSEARCH_TIMEOUT` | timeout запроса к Elasticsearch в секундах | `5` |
| `ELASTICSEARCH_FORWARD_PORT` | порт Elasticsearch на хосте | `9200` |

Токен DaData не хранится в репозитории: задайте его только в локальном `.env`
или в секрет-хранилище окружения. Без токена health-check, чтение и операции с
категориями доступны, но создание и обновление товара завершаются JSON-ошибкой
`inn_validation_unavailable` с HTTP 503.

## Архитектура

Границы модулей разделены по ответственности:

- `src/Http` — минимальные HTTP request/response, маршрутизация и единый JSON
  exception handler;
- `src/Catalog/Http` — контроллеры товаров и категорий;
- `src/Catalog/Application` — use cases каталога, валидация, проверка ИНН и
  синхронизация индекса после записи;
- `src/Catalog/Domain` — модели `Product`, `Category` и фильтры;
- `src/Catalog/Repository` — порты хранения, `src/Catalog/Persistence` —
  PDO/MySQL-реализации;
- `src/Catalog/Inn` — стратегия проверки ИНН, DaData client и TTL-кэш;
- `src/Catalog/Indexing` — индексатор и полный reindex товаров;
- `src/Catalog/Read` — Elasticsearch read-source и fallback на MySQL;
- `src/Elasticsearch` — транспорт и клиент Elasticsearch;
- `src/Config`, `src/Bootstrap`, `src/Health` — конфигурация, composition root
  и health-check.

Запись товара сначала выполняется в MySQL, затем документ синхронизируется с
Elasticsearch. Если синхронизация индекса не удалась, API возвращает
`indexing_failed` с HTTP 503, но транзакционная запись в MySQL уже сохранена.

## HTTP API

Все ответы имеют `Content-Type: application/json; charset=utf-8`. Успешные
ответы с данными используют форму `{"data": ...}`. Ошибки используют форму:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Validation failed",
    "details": {"inn": "INN must contain exactly 10 digits"}
  }
}
```

### Health

`GET /health` → `200`

### Categories

| Метод | URL | Успех |
| --- | --- | --- |
| `GET` | `/categories` | `200`, список категорий в `data` |
| `POST` | `/categories` | `201`, тело `{"name":"Books"}` |
| `GET` | `/categories/{id}` | `200` или `404` |
| `PUT`, `PATCH` | `/categories/{id}` | `200`, тело `{"name":"Books"}` |
| `DELETE` | `/categories/{id}` | `204`, пустое тело |

Имена категорий обязательны и должны быть непустыми. Повторное имя возвращает
`409 conflict`.

### Products

| Метод | URL | Успех |
| --- | --- | --- |
| `GET` | `/products` | `200`, список товаров в `data` |
| `POST` | `/products` | `201`, созданный товар в `data` |
| `GET` | `/products/{id}` | `200` или `404` |
| `PUT`, `PATCH` | `/products/{id}` | `200`, обновлённый товар в `data` |
| `DELETE` | `/products/{id}` | `204`, пустое тело |

Тело create/update товара:

```json
{
  "name": "API Handbook",
  "inn": "7701234567",
  "ean13": "4601234567890",
  "description": "Reference book",
  "category_ids": [1, 2]
}
```

`inn` — ровно 10 цифр и проверяется через DaData; `ean13` — ровно 13 цифр;
`category_ids` — массив существующих уникальных положительных integer ID.
При `PUT`/`PATCH` отсутствующие поля товара сохраняют текущие значения;
`category_ids` также сохраняется, если поле не передано.

Поддерживаемые фильтры `GET /products`:

- `name` — поиск по подстроке без учёта регистра;
- `inn` — точное совпадение;
- `ean13` — точное совпадение;
- `category` — положительный ID категории.

Фильтры можно комбинировать, например:

```sh
curl 'http://localhost:8080/products?name=handbook&category=1'
```

Основные ошибки: `422 validation_error`, `404 not_found`, `409 conflict`,
`503 inn_validation_unavailable` или `indexing_failed`, `504
inn_validation_timeout`. Необработанные ошибки скрываются за `500
internal_error`.

## MySQL и Elasticsearch

`database/migrations/001_catalog.sql` содержит таблицы `categories`, `products`
и `product_categories`. Compose монтирует этот файл в
`/docker-entrypoint-initdb.d`, поэтому MySQL применяет его автоматически при
первом запуске с пустым volume `mysql-data`. При уже существующем volume
инициализация повторно не выполняется; для нового чистого окружения используйте
`docker compose down -v` с пониманием, что это удалит локальные данные.

Индекс Elasticsearch создаётся при первой записи товара или при запуске
reindex. Если Elasticsearch временно недоступен или индекс содержит пустой,
отсутствующий, ошибочный или структурно устаревший результат, чтение товаров
переключается на MySQL. Поэтому поиск и `GET /products/{id}` сохраняют рабочий
fallback при проблемах с Elasticsearch. Fallback не скрывает ошибку записи:
ошибка индексации после create/update возвращается клиенту как HTTP 503.

## Полная переиндексация

CLI-команда пересоздаёт индекс, читает все товары из MySQL и индексирует их
заново:

```sh
docker compose exec app php bin/reindex-products.php
```

При успехе команда печатает, например, `Reindexed 12 products.` и завершается
с кодом `0`. При недоступном Elasticsearch или другой ошибке она пишет причину
в stderr, печатает `Product reindex failed: ...` и завершается с кодом `1`.
Команда не требует токена DaData, потому что использует уже сохранённые товары.

## Проверки и чистая поставка

Сервис `app` монтирует исходный checkout в контейнер, поэтому после чистого
checkout зависимости нужно один раз установить в `vendor/` этой командой. Каталог
`vendor/` игнорируется Git и не содержит исходных секретов.

В контейнере после этого доступны воспроизводимые проверки:

```sh
docker compose exec app composer check  # PHP syntax
docker compose exec app composer test   # PHPUnit 11
```

В репозитории не должны находиться `.env`, `vendor/`, логи, рабочие промпты или
агентские инструкции. Перед передачей задания полезно проверить состав tracked
файлов и отсутствие секретных значений:

```sh
git ls-files
git grep -n -E '(DADATA_API_TOKEN=.+|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|ghp_[A-Za-z0-9]+|sk-[A-Za-z0-9]+)' -- . ':!README.md' ':!.env.example' ':!composer.lock' || true
```

Локальные данные Docker удаляются только явной командой:

```sh
docker compose down -v
```
