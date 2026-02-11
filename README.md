# 🚀 DLE News API

[![API Version](https://img.shields.io/badge/API%20Version-4.0-blue.svg)](https://github.com/yourusername/dle-api)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.3-green.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![DLE Compatible](https://img.shields.io/badge/DLE-All%20Versions-orange.svg)](https://dle-news.ru/)

**Полноценный REST API для DataLife Engine (DLE)** - работает автономно без подключения к движку DLE и предоставляет полный CRUD функционал для управления новостями через HTTP запросы.

 * Полноценный DLE News API
 * Работает БЕЗ подключения engine/init.php
 * Версия: 4.0 - DLE 18.1 Compatible
 * Совместимость: DLE 13.x - 18.1+
 * Функционал: добавление, получение, редактирование, удаление новостей
 * Поддержка: post_extras, post_extras_cats, tags, xfsearch, cache clearing

## ✨ Особенности

- 📝 **Полное управление новостями** - добавление, редактирование, удаление
- 📖 **Чтение данных** - получение списка, поиск, фильтрация
- 🗂️ **Работа с категориями** - получение и создание категорий
- 🔐 **Безопасность** - API ключи, rate limiting, валидация данных
- ⚡ **Высокая производительность** - автономная работа без загрузки DLE
- 🌐 **CORS поддержка** - для фронтенд приложений
- 📊 **Логирование и мониторинг** - детальные логи всех операций

## ⚡ Быстрый старт

### 1. Установка
```bash
# Скачайте файл API
wget https://raw.githubusercontent.com/Murkirpus/DLE-API/main/api.php

# Разместите на вашем сервере с DLE
```

### 2. Настройка
Отредактируйте параметры в файле `api.php`:
```php
define('API_SECRET_KEY', 'ваш_секретный_ключ_здесь');
define('DB_HOST', 'localhost');
define('DB_NAME', 'имя_базы_dle');
define('DB_USER', 'пользователь');
define('DB_PASS', 'пароль');
```

### 3. Тестирование
```bash
curl "https://ваш-сайт.com/api.php?action=test"
```

## 📚 Основные методы API

### Получение данных (без аутентификации)
- `GET /api.php?action=get_news` - список новостей с пагинацией
- `GET /api.php?action=get_news_by_id&news_id=123` - новость по ID
- `GET /api.php?action=search_news&query=поиск` - поиск новостей
- `GET /api.php?action=get_categories` - список категорий

### Управление контентом (требует API ключ)
- `POST /api.php` с `action=add_news` - добавление новости
- `POST /api.php` с `action=update_news` - обновление новости
- `POST /api.php` с `action=delete_news` - удаление новости
- `GET /api.php?action=get_stats` - статистика сайта

## 💡 Примеры использования

### Получение списка новостей
```bash
curl "https://ваш-сайт.com/api.php?action=get_news&limit=10&category=2"
```

### Добавление новости
```bash
curl -X POST https://ваш-сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_news",
    "api_key": "ваш_ключ",
    "title": "Заголовок новости",
    "short_story": "Краткое описание",
    "full_story": "Полный текст новости",
    "category": 2
  }'
```

### PHP клиент
```php
// Получение новостей
$url = 'https://ваш-сайт.com/api.php?action=get_news&limit=5';
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['data']['news'] as $news) {
        echo $news['title'] . "\n";
    }
}
```

### JavaScript
```javascript
// Получение новостей
fetch('https://ваш-сайт.com/api.php?action=get_news&limit=5')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      data.data.news.forEach(news => {
        console.log(news.title);
      });
    }
  });
```

## 🔐 Аутентификация

API поддерживает два способа аутентификации:

1. **API ключ (рекомендуется):**
```json
{
  "action": "add_news",
  "api_key": "ваш_секретный_ключ",
  "title": "Заголовок"
}
```

2. **Логин/пароль DLE:**
```json
{
  "action": "add_news", 
  "username": "admin",
  "password": "пароль",
  "title": "Заголовок"
}
```

## 📋 Требования

- **PHP 7.4+** (рекомендуется PHP 8.0+)
- **MySQL 5.7+** или **MariaDB 10.2+**
- **DataLife Engine** любой версии
- **PDO** и **JSON** расширения PHP

## 🛡️ Безопасность

- ✅ **Rate Limiting** - защита от флуда (100 запросов/час)
- ✅ **SQL Injection защита** - использование PDO prepared statements
- ✅ **Валидация данных** - проверка всех входных параметров
- ✅ **API ключи** - безопасная аутентификация
- ✅ **Логирование** - запись всех операций

## 📊 Мониторинг

API создает файл `api.log` с детальными логами:
```bash
# Просмотр логов
tail -f api.log

# Поиск ошибок
grep "Ошибка" api.log
```

## 🔄 Формат ответов

Все ответы в JSON формате:
```json
{
  "success": true,
  "data": {
    "news_id": 123,
    "title": "Заголовок новости"
  },
  "message": "Новость успешно добавлена",
  "timestamp": 1670000000,
  "api_version": "3.0"
}
```

## 📖 Полная документация

Подробная документация с примерами доступна в файле [doc-api-3_0.html](doc-api-3_0.html).

## 🤝 Поддержка
- 🐛 **Баги и предложения:** [Issues](https://github.com/Murkirpus/DLE-API/issues)
- 🐛 (https://dj-x.info))
- 📧 **Email:** murkir@gmail.com
- 💬 **Telegram:** @Murkir1

## 📄 Лицензия

Этот проект распространяется под лицензией MIT. Подробности в файле [LICENSE](LICENSE).

---

⭐ **Если проект оказался полезным, поставьте звёздочку!**


# DLE News API v4.0

**Полноценный REST API для DataLife Engine 13.x — 18.1+**

Работает **без подключения** `engine/init.php`. Прямое подключение к БД.
Автоопределение версии DLE, совместимость со всеми структурами таблиц.

---

## Установка

1. Скопируйте `api.php` в **корень сайта** (рядом с `index.php` DLE)
2. Откройте файл и настройте параметры:

```php
define('API_SECRET_KEY', 'ваш_секретный_ключ');  // Обязательно замените!
define('DB_HOST', 'localhost');
define('DB_NAME', 'имя_базы');
define('DB_USER', 'пользователь');
define('DB_PASS', 'пароль');
define('DB_PREFIX', 'dle_');           // Префикс таблиц
define('DLE_ROOT', '');                // Путь к корню DLE (для очистки кеша)
```

> **Важно:** Если `api.php` лежит не в корне сайта, обязательно укажите `DLE_ROOT` — иначе кеш не будет очищаться и новости не появятся на сайте.

---

## Аутентификация

Все действия **на запись** требуют `api_key`. Действия **на чтение** работают без ключа.

| Требуют api_key | Без api_key |
|---|---|
| `add_news`, `update_news`, `delete_news` | `get_news`, `get_news_by_id`, `search_news` |
| `add_category`, `get_news_status`, `get_stats` | `get_categories`, `test_connection` |

```json
{
    "action": "add_news",
    "api_key": "ваш_секретный_ключ",
    ...
}
```

---

## Формат запросов

- **Метод:** POST
- **Content-Type:** application/json
- **Лимит:** 100 запросов в час с одного IP

Формат ответа:
```json
{
    "success": true,
    "data": { ... },
    "timestamp": 1770807584,
    "api_version": "4.0",
    "message": "Описание результата"
}
```

При ошибке:
```json
{
    "success": false,
    "error": "Описание ошибки",
    "code": 400
}
```

---

## Команды API

### 1. `test_connection` — Проверка работы API

Проверяет подключение к БД, определяет версию DLE, показывает доступные таблицы.

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "test_connection"}'
```

**Ответ:**
```json
{
    "data": {
        "api_status": "working",
        "version": "4.0",
        "database_connected": true,
        "dle_version": "17+",
        "tables_found": {
            "posts": "dle_post",
            "categories": "dle_category",
            "users": "dle_users"
        },
        "dle_tables": {
            "post_extras": true,
            "post_extras_cats": true,
            "tags": true,
            "xfsearch": true
        },
        "available_actions": [...]
    }
}
```

---

### 2. `add_news` — Добавление новости

Добавляет новость + автоматический rebuild (post_extras, post_extras_cats, tags, xfsearch, кеш).

**Обязательные поля:** `title`, `short_story`, `full_story`

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_news",
    "api_key": "ваш_ключ",
    "title": "Название фильма",
    "short_story": "<p>Краткое описание для главной</p>",
    "full_story": "<p>Полное описание фильма</p>"
  }'
```

**Все параметры:**

| Параметр | Тип | По умолчанию | Описание |
|---|---|---|---|
| `title` | string | — | **Обязательно.** Заголовок новости |
| `short_story` | string | — | **Обязательно.** Краткая новость (HTML) |
| `full_story` | string | — | **Обязательно.** Полная новость (HTML) |
| `category` | string | `"1"` | ID категории. Несколько через запятую: `"3,7,12"` |
| `author` | string | `"admin"` | Имя автора (логин пользователя DLE) |
| `user_id` | int | `1` | ID пользователя |
| `approve` | int | `1` | Публикация: 1 = опубликована, 0 = на модерации |
| `allow_main` | int | `1` | Показывать на главной: 1 = да, 0 = нет |
| `allow_comments` | int | `1` | Разрешить комментарии |
| `allow_rating` | int | `1` | Разрешить оценки |
| `fixed` | int | `0` | Закрепить новость: 1 = да |
| `keywords` | string | `""` | Мета-ключевые слова (SEO) |
| `description` | string | `""` | Мета-описание (SEO) |
| `metatitle` | string | `""` | Мета-заголовок (DLE 17+) |
| `tags` | string | `""` | Теги через запятую: `"боевик, триллер, 2026"` |
| `alt_name` | string | авто | ЧПУ URL (генерируется из title если не указан) |
| `xfields` | object | `{}` | Дополнительные поля (см. раздел ниже) |

**Ответ:**
```json
{
    "data": {
        "news_id": "5",
        "title": "Название фильма",
        "alt_name": "nazvanie-filma-1770807584",
        "url": "https://сайт.com/5-nazvanie-filma-1770807584.html",
        "table_used": "dle_post",
        "fields_used": 19,
        "rebuild": "ok"
    }
}
```

---

### 3. `update_news` — Обновление новости

Обновляет только переданные поля. Автоматически вызывает rebuild.

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_news",
    "api_key": "ваш_ключ",
    "news_id": 5,
    "title": "Новое название",
    "category": "3,7",
    "tags": "драма, 2026",
    "xfields": {
        "quality": "4K",
        "year": "2026"
    }
  }'
```

**Параметры:** `news_id` (обязательно) + любые поля из `add_news`.

**Ответ:**
```json
{
    "data": {
        "news_id": 5,
        "updated_fields": 4,
        "rebuild": "ok"
    }
}
```

---

### 4. `delete_news` — Удаление новости

Удаляет новость и **все связанные записи**: post_extras, post_extras_cats, tags, xfsearch, комментарии. Также удаляет ID из related_ids других новостей.

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete_news",
    "api_key": "ваш_ключ",
    "news_id": 5
  }'
```

**Ответ:**
```json
{
    "data": {
        "news_id": 5,
        "title": "Название фильма",
        "deleted": true,
        "cleanup": true
    }
}
```

---

### 5. `get_news` — Список новостей

Получение списка новостей с пагинацией и фильтрами. **Без аутентификации.**

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_news",
    "limit": 10,
    "offset": 0,
    "category": 3,
    "order_by": "date",
    "order_direction": "DESC",
    "approved_only": 1
  }'
```

| Параметр | Тип | По умолчанию | Описание |
|---|---|---|---|
| `limit` | int | `10` | Количество (макс. 100) |
| `offset` | int | `0` | Смещение для пагинации |
| `category` | int | `0` | Фильтр по категории (0 = все) |
| `order_by` | string | `"date"` | Сортировка: `date`, `id`, `title`, `news_read`, `rating` |
| `order_direction` | string | `"DESC"` | Направление: `ASC` или `DESC` |
| `approved_only` | int | `1` | Только опубликованные (0 = все) |

**Ответ:**
```json
{
    "data": {
        "news": [
            {
                "id": 5,
                "title": "Название",
                "short_story": "Краткое описание...",
                "date": "2026-02-11 12:59:44",
                "category": "3",
                "author": "admin",
                "views": 150,
                "url": "https://сайт.com/5-nazvanie.html"
            }
        ],
        "total": 42,
        "limit": 10,
        "offset": 0,
        "has_more": true
    }
}
```

---

### 6. `get_news_by_id` — Полная новость по ID

Возвращает все поля включая `full_story` и распарсенные `xfields`. Увеличивает счётчик просмотров. **Без аутентификации.**

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_news_by_id",
    "news_id": 5
  }'
```

**Ответ:**
```json
{
    "data": {
        "id": 5,
        "title": "Название",
        "short_story": "<p>Краткое описание</p>",
        "full_story": "<p>Полное описание</p>",
        "date": "2026-02-11 12:59:44",
        "category": "3",
        "author": "admin",
        "views": 151,
        "keywords": "фильм, 2026",
        "description": "SEO описание",
        "metatitle": "SEO заголовок",
        "xfields": {
            "quality": "1080p",
            "year": "2026",
            "country": "США"
        },
        "url": "https://сайт.com/5-nazvanie.html"
    }
}
```

---

### 7. `search_news` — Поиск новостей

Поиск по `title`, `short_story`, `full_story`, `keywords`. **Без аутентификации.**

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "search_news",
    "query": "интерстеллар",
    "limit": 10,
    "offset": 0
  }'
```

**Ответ:**
```json
{
    "data": {
        "news": [...],
        "total": 3,
        "query": "интерстеллар",
        "limit": 10,
        "offset": 0
    }
}
```

---

### 8. `get_news_status` — Диагностика новости

Проверяет статус новости: опубликована ли, есть ли запись в post_extras, проиндексирована ли в post_extras_cats.

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_news_status",
    "api_key": "ваш_ключ",
    "news_id": 5
  }'
```

**Ответ:**
```json
{
    "data": {
        "id": "5",
        "title": "Название",
        "approve": "1",
        "allow_main": "1",
        "date": "2026-02-11 12:59:44",
        "comments": "0",
        "has_extras": true,
        "has_related": false,
        "categories_indexed": 1
    }
}
```

> **Подсказка:** Если `has_extras: false` или `categories_indexed: 0` — новость не будет отображаться на главной. Обновите новость через `update_news` чтобы пересоздать записи.

---

### 9. `get_categories` — Список категорий

**Без аутентификации.**

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "get_categories"}'
```

**Ответ:**
```json
{
    "data": {
        "categories": [
            {"id": 1, "name": "Фильмы", "alt_name": "films", "description": "", "sort": 0},
            {"id": 2, "name": "Сериалы", "alt_name": "serials", "description": "", "sort": 1},
            {"id": 3, "name": "Мультфильмы", "alt_name": "cartoons", "description": "", "sort": 2}
        ]
    }
}
```

---

### 10. `add_category` — Добавление категории

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_category",
    "api_key": "ваш_ключ",
    "name": "Аниме",
    "alt_name": "anime",
    "description": "Японская анимация",
    "sort": 5
  }'
```

| Параметр | Тип | По умолчанию | Описание |
|---|---|---|---|
| `name` | string | — | **Обязательно.** Название категории |
| `alt_name` | string | авто | ЧПУ (транслитерация из name) |
| `description` | string | `""` | Описание |
| `sort` | int | `0` | Порядок сортировки |

---

### 11. `get_stats` — Статистика сайта

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_stats",
    "api_key": "ваш_ключ"
  }'
```

**Ответ:**
```json
{
    "data": {
        "total_news": 1542,
        "approved_news": 1530,
        "pending_news": 12,
        "total_categories": 15,
        "total_views": 2845000,
        "average_views": 1845.07,
        "total_comments": 12450,
        "popular_news": [
            {"id": 100, "title": "Самая популярная", "views": 50000},
            {"id": 200, "title": "Вторая по популярности", "views": 45000}
        ],
        "dle_version": "17+"
    }
}
```

---

## Дополнительные поля (xfields)

DLE поддерживает кастомные поля через `xfields`. Это ключ-значение пары, которые хранятся в формате `ключ|значение||ключ|значение` в БД.

### Добавление с xfields

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_news",
    "api_key": "ваш_ключ",
    "title": "Интерстеллар",
    "short_story": "<p>Когда засуха приводит человечество к продовольственному кризису, команда исследователей отправляется через червоточину в поисках нового дома.</p>",
    "full_story": "<p>Полное описание фильма...</p>",
    "category": "3,7",
    "tags": "фантастика, космос, нолан, драма",
    "xfields": {
        "quality":     "1080p",
        "year":        "2014",
        "country":     "США, Великобритания",
        "genre":       "Фантастика, Драма, Приключения",
        "director":    "Кристофер Нолан",
        "actors":      "Мэттью МакКонахи, Энн Хэтэуэй, Джессика Честейн",
        "duration":    "169 мин.",
        "rating_kp":   "8.6",
        "rating_imdb": "8.7",
        "poster":      "/uploads/posts/posters/interstellar.jpg",
        "trailer":     "https://youtube.com/watch?v=zSWdZVtXT7E",
        "translation": "Дублированный, Оригинал + субтитры",
        "age_rating":  "12+",
        "kinopoisk_id": "258687",
        "imdb_id":     "tt0816692"
    }
  }'
```

### Обновление xfields

При обновлении передаётся **полный набор** xfields — старые значения перезаписываются:

```bash
curl -X POST https://сайт.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_news",
    "api_key": "ваш_ключ",
    "news_id": 5,
    "xfields": {
        "quality":     "4K UHD",
        "year":        "2014",
        "country":     "США, Великобритания",
        "rating_kp":   "8.6",
        "rating_imdb": "8.7",
        "poster":      "/uploads/posts/posters/interstellar-4k.jpg"
    }
  }'
```

### Чтение xfields

В ответе `get_news_by_id` поле xfields автоматически парсится в объект:

```json
{
    "xfields": {
        "quality": "4K UHD",
        "year": "2014",
        "country": "США, Великобритания",
        "rating_kp": "8.6"
    }
}
```

### Типичные xfields для кинопортала

| Поле | Описание | Пример |
|---|---|---|
| `quality` | Качество видео | `1080p`, `4K`, `720p` |
| `year` | Год выпуска | `2026` |
| `country` | Страна | `США, Великобритания` |
| `genre` | Жанр | `Боевик, Фантастика` |
| `director` | Режиссёр | `Кристофер Нолан` |
| `actors` | Актёры | `Том Круз, Энн Хэтэуэй` |
| `duration` | Длительность | `120 мин.` |
| `rating_kp` | Рейтинг КиноПоиск | `8.5` |
| `rating_imdb` | Рейтинг IMDB | `8.3` |
| `poster` | Путь к постеру | `/uploads/posts/posters/film.jpg` |
| `trailer` | Ссылка на трейлер | `https://youtube.com/watch?v=...` |
| `translation` | Перевод | `Дублированный` |
| `age_rating` | Возрастной рейтинг | `16+` |
| `kinopoisk_id` | ID КиноПоиска | `258687` |
| `imdb_id` | ID IMDB | `tt0816692` |

> **Важно:** Названия полей в `xfields` должны совпадать с теми, что настроены в админке DLE → Дополнительные поля. API не создаёт определения полей — только заполняет значения.

---

## Примеры на PHP

### Добавление фильма со всеми полями

```php
<?php
function dleApi($action, $params = []) {
    $params['action']  = $action;
    $params['api_key'] = 'ваш_секретный_ключ';

    $ch = curl_init('https://сайт.com/api.php');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $result;
}

// Добавление фильма
$result = dleApi('add_news', [
    'title'       => 'Дюна: Часть третья',
    'short_story' => '<p>Пол Атрейдес продолжает свой путь...</p>',
    'full_story'  => '<p>Полное описание фильма Дюна 3...</p>',
    'category'    => '3',
    'tags'        => 'фантастика, дюна, вильнёв',
    'keywords'    => 'Дюна 3, Dune Part Three, фантастика 2026',
    'description' => 'Смотреть Дюна: Часть третья онлайн в хорошем качестве',
    'metatitle'   => 'Дюна 3 (2026) — смотреть онлайн бесплатно',
    'xfields'     => [
        'quality'     => '1080p',
        'year'        => '2026',
        'country'     => 'США',
        'genre'       => 'Фантастика, Приключения, Драма',
        'director'    => 'Дени Вильнёв',
        'actors'      => 'Тимоти Шаламе, Зендея, Хавьер Бардем',
        'duration'    => '165 мин.',
        'rating_kp'   => '8.1',
        'rating_imdb' => '8.4',
        'poster'      => '/uploads/posts/posters/dune3.jpg',
        'trailer'     => 'https://youtube.com/watch?v=example',
        'translation' => 'Дублированный',
        'age_rating'  => '16+',
    ]
]);

if ($result['success']) {
    echo "Добавлен фильм ID: " . $result['data']['news_id'] . "\n";
    echo "URL: " . $result['data']['url'] . "\n";
    echo "Rebuild: " . $result['data']['rebuild'] . "\n";
} else {
    echo "Ошибка: " . $result['error'] . "\n";
}
```

### Массовое добавление из массива

```php
$films = [
    [
        'title' => 'Фильм 1',
        'short_story' => '<p>Описание 1</p>',
        'full_story' => '<p>Полное описание 1</p>',
        'category' => '3',
        'xfields' => ['year' => '2026', 'quality' => '1080p']
    ],
    [
        'title' => 'Фильм 2',
        'short_story' => '<p>Описание 2</p>',
        'full_story' => '<p>Полное описание 2</p>',
        'category' => '3,7',
        'xfields' => ['year' => '2025', 'quality' => '4K']
    ],
];

foreach ($films as $film) {
    $result = dleApi('add_news', $film);
    echo $film['title'] . ': ' . ($result['success'] ? 'OK (ID ' . $result['data']['news_id'] . ')' : $result['error']) . "\n";
    usleep(500000); // пауза 0.5 сек между запросами
}
```

### Получение и обновление

```php
// Получить новость
$news = dleApi('get_news_by_id', ['news_id' => 5]);
print_r($news['data']['xfields']);

// Обновить рейтинг
dleApi('update_news', [
    'news_id' => 5,
    'xfields' => [
        'rating_kp'   => '9.0',
        'rating_imdb' => '8.8',
        'quality'     => '4K UHD',
    ]
]);

// Поиск
$results = dleApi('search_news', ['query' => 'Нолан', 'limit' => 5]);
foreach ($results['data']['news'] as $item) {
    echo $item['id'] . ': ' . $item['title'] . "\n";
}

// Статистика
$stats = dleApi('get_stats');
echo "Всего новостей: " . $stats['data']['total_news'] . "\n";
```

---

## Что делает Rebuild

При добавлении/обновлении API автоматически выполняет:

1. **`dle_post_extras`** — создаёт/обновляет запись (allow_rate, related_ids, news_password)
2. **`dle_post_extras_cats`** — индексирует категории (критично для отображения на главной!)
3. **`dle_tags`** — обновляет теги (DLE 13+)
4. **`dle_xfsearch`** — индексирует дополнительные поля для поиска (DLE 15+)
5. **Очистка кеша** — удаляет `*.tmp` и `*.php` из `engine/cache/`

> Без rebuild новость существует в `dle_post`, но **не отображается** на главной странице и в списках категорий.

---

## Коды ошибок

| Код | Описание |
|---|---|
| 200 | Успех |
| 400 | Неверные параметры (отсутствует обязательное поле) |
| 401 | Ошибка аутентификации (неверный api_key) |
| 404 | Новость не найдена |
| 429 | Превышен лимит запросов (100/час) |
| 500 | Ошибка сервера / базы данных |

---

## Совместимость

| Версия DLE | Поддержка |
|---|---|
| 13.x — 14.x | ✅ post_extras, post_extras_cats, tags |
| 15.x — 16.x | ✅ + xfsearch |
| 17.x — 18.1+ | ✅ + metatitle, related_ids |

Версия определяется автоматически по структуре таблиц.

---

## Безопасность

- Смените `API_SECRET_KEY` на сложный ключ (минимум 32 символа)
- Ограничьте доступ по IP через nginx/htaccess
- Логи запросов пишутся в `api_debug.log` рядом с `api.php`
- Rate limit: 100 запросов в час с одного IP

Пример ограничения доступа в nginx:
```nginx
location = /api.php {
    allow 127.0.0.1;
    allow ВАШ_IP;
    deny all;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```
