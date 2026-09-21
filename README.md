# Product Catalog API

REST API каталога товаров и категорий на PHP 8.3, MySQL и Elasticsearch.

## Быстрый старт

Нужны Docker и Docker Compose v2.

```sh
make init
make up
curl http://localhost:8080/health
```

Безопасные dev defaults находятся в tracked `.env`. Локальные секреты и
переопределения добавляйте в `.env.local`; этот файл игнорируется Git.

## Команды

```text
make init      установить зависимости в app/vendor
make up        собрать и запустить Compose
make down      остановить Compose
make logs      показать логи
make install   установить Composer-зависимости
make test      запустить PHPUnit
make check     проверить синтаксис PHP
make reindex   полностью переиндексировать товары
make shell     открыть shell контейнера app
make reset     УДАЛИТЬ локальные Docker volumes
```

## API

- `GET /health`
- `GET|POST /categories`
- `GET|PUT|PATCH|DELETE /categories/{id}`
- `GET|POST /products`
- `GET|PUT|PATCH|DELETE /products/{id}`

Успешные данные возвращаются в `{"data": ...}`, ошибки — в `{"error": ...}`.
Поиск товаров поддерживает `name`, `inn`, `ean13` и `category`. Создание и
обновление товара проверяет INN через DaData, а Elasticsearch-read имеет
MySQL fallback.

Подробные границы модулей и dependency flow: [`docs/architecture.md`](docs/architecture.md).
