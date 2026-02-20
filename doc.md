# DLE News API v4.0 — Документация

> **Версия API:** 4.0  
> **Совместимость:** DLE 13.x — 18.1+  
> **Формат:** JSON (UTF-8)

---

## Содержание

1. [Аутентификация](#аутентификация)
2. [Тест соединения](#1-тест-соединения)
3. [Добавление новости](#2-добавление-новости)
4. [Добавление неопубликованной новости (approve=0)](#3-добавление-неопубликованной-новости)
5. [Добавление новости с постером и xfields](#4-добавление-новости-с-постером-и-xfields)
6. [Загрузка файла (upload\_file)](#5-загрузка-файла)
7. [Загрузка файла с привязкой к новости](#6-загрузка-файла-с-привязкой-к-новости)
8. [Обновление новости](#7-обновление-новости)
9. [Удаление новости](#8-удаление-новости)
10. [Получение списка новостей](#9-получение-списка-новостей)
11. [Получение новости по ID](#10-получение-новости-по-id)
12. [Поиск новостей](#11-поиск-новостей)
13. [Получение категорий](#12-получение-категорий)
14. [Добавление категории](#13-добавление-категории)
15. [Статистика](#14-статистика)
16. [Статус новости](#15-статус-новости)
17. [Проверка дубликата по Кинопоиск ID](#16-проверка-дубликата)
18. [Полный пример: создание новости + загрузка файлов](#17-полный-пример)
19. [Коды ошибок](#коды-ошибок)

---

## Аутентификация

Все действия на запись (`add_news`, `update_news`, `delete_news`, `upload_file`, `add_category`, `get_stats`, `get_news_status`, `check_duplicate`) требуют аутентификации.

Действия на чтение (`get_news`, `get_news_by_id`, `search_news`, `get_categories`, `test`, `test_connection`) доступны **без аутентификации**.

**Способ 1 — API-ключ (рекомендуемый):**
```json
{
  "api_key": "ВАШ_СЕКРЕТНЫЙ_КЛЮЧ"
}
```

**Способ 2 — Логин/пароль пользователя DLE:**
```json
{
  "username": "admin",
  "password": "пароль"
}
```

---

## 1. Тест соединения

Проверка работоспособности API, подключения к БД, определённой версии DLE.

```bash
# Простейший GET-запрос
curl https://site.com/api.php

# POST с явным action
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "test"}'
```

**Ответ:**
```json
{
  "success": true,
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
    "available_actions": [
      "add_news", "update_news", "delete_news", "get_news_status",
      "get_news", "get_news_by_id", "search_news",
      "get_categories", "add_category",
      "get_stats", "upload_file"
    ]
  }
}
```

---

## 2. Добавление новости

**Обязательные поля:** `title`, `short_story`, `full_story`

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_news",
    "api_key": "ВАШ_КЛЮЧ",
    "title": "Заголовок новости",
    "short_story": "<p>Краткое описание новости</p>",
    "full_story": "<p>Полный текст новости с <b>HTML</b>-разметкой.</p>",
    "category": "1",
    "author": "admin",
    "tags": "тег1, тег2, тег3",
    "keywords": "ключевое слово 1, ключевое слово 2",
    "description": "Meta-описание для SEO",
    "metatitle": "SEO-заголовок страницы"
  }'
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "news_id": 155,
    "title": "Заголовок новости",
    "alt_name": "zagolovok-novosti-1737456000",
    "url": "https://site.com/155-zagolovok-novosti-1737456000.html",
    "fields_used": 22,
    "rebuild": "ok"
  },
  "message": "Новость успешно добавлена"
}
```

---

## 3. Добавление неопубликованной новости

> **Ключевой параметр:** `"approve": 0` — новость создаётся, но **не отображается** на сайте. Публикация — вручную из админки DLE.

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_news",
    "api_key": "ВАШ_КЛЮЧ",
    "title": "Ожидает модерации",
    "short_story": "<p>Краткое описание.</p>",
    "full_story": "<p>Полный текст новости.</p>",
    "category": "2",
    "author": "admin",
    "approve": 0,
    "tags": "модерация, черновик",
    "keywords": "черновик",
    "description": "Эта новость ожидает публикации"
  }'
```

### Все опциональные поля add_news

| Поле             | Тип     | По умолчанию | Описание                                |
|------------------|---------|--------------|-----------------------------------------|
| `approve`        | int     | `1`          | `0` — не опубликована, `1` — опубликована |
| `allow_comments` | int     | `1`          | Разрешить комментарии                   |
| `allow_main`     | int     | `1`          | Показывать на главной                   |
| `allow_rating`   | int     | `1`          | Разрешить голосование                   |
| `fixed`          | int     | `0`          | Закреплённая новость                    |
| `user_id`        | int     | `1`          | ID пользователя-автора                  |
| `category`       | string  | `"1"`        | ID категории                            |
| `author`         | string  | `"admin"`    | Имя автора                              |
| `alt_name`       | string  | авто         | ЧПУ-алиас (авто из заголовка)           |
| `tags`           | string  | `""`         | Теги через запятую                      |
| `keywords`       | string  | `""`         | Meta keywords                           |
| `description`    | string  | `""`         | Meta description                        |
| `metatitle`      | string  | `""`         | Meta title                              |
| `xfields`        | object  | `{}`         | Дополнительные поля (см. ниже)          |

---

## 4. Добавление новости с постером и xfields

Постер скачивается автоматически по URL из `xfields.poster` (домен должен быть в белом списке).

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_news",
    "api_key": "ВАШ_КЛЮЧ",
    "title": "Фильм: Начало (2010)",
    "short_story": "<p>Дом Кобб — талантливый вор...</p>",
    "full_story": "<p>Полное описание фильма...</p>",
    "category": "3",
    "author": "admin",
    "approve": 0,
    "tags": "фантастика, триллер, Кристофер Нолан",
    "keywords": "начало, inception, фильм 2010",
    "description": "Смотреть онлайн фильм Начало (2010)",
    "xfields": {
      "kinopoisk_id": "447301",
      "poster": "https://avatars.mds.yandex.net/get-kinopoisk-image/1234/example/orig",
      "year": "2010",
      "genre": "фантастика, боевик, триллер",
      "country": "США, Великобритания",
      "director": "Кристофер Нолан",
      "actors": "Леонардо ДиКаприо, Джозеф Гордон-Левитт",
      "quality": "HDRip",
      "duration": "148 мин.",
      "rating_kp": "8.7",
      "rating_imdb": "8.8",
      "translator": "Дублированный"
    }
  }'
```

**Ответ (с информацией о постере):**
```json
{
  "success": true,
  "data": {
    "news_id": 156,
    "title": "Фильм: Начало (2010)",
    "alt_name": "film-nachalo-2010-1737456000",
    "url": "https://site.com/156-film-nachalo-2010-1737456000.html",
    "rebuild": "ok",
    "poster": {
      "saved": true,
      "url": "/uploads/posts/2026-02/poster_156_a3f1b2c4.webp",
      "size": 28450
    }
  },
  "message": "Новость успешно добавлена"
}
```

### Разрешённые хосты для скачивания постеров

| Хост                              | Источник        |
|-----------------------------------|-----------------|
| `avatars.mds.yandex.net`         | Кинопоиск       |
| `kinopoiskapiunofficial.tech`    | KP API          |
| `st.kp.yandex.net`              | Кинопоиск       |
| `image.openmoviedb.com`         | OpenMovieDB     |
| `image.tmdb.org`                | TMDB            |
| `media.themoviedb.org`          | TMDB            |

### Настройки обработки постера (в api.php)

| Константа          | Значение  | Описание                           |
|--------------------|-----------|------------------------------------|
| `POSTER_FORMAT`    | `webp`    | Формат: `jpg`, `png`, `webp`, `original` |
| `POSTER_QUALITY`   | `85`      | Качество (1–100)                   |
| `POSTER_MAX_WIDTH` | `223`     | Макс. ширина px (0 = без ресайза)  |
| `POSTER_MAX_HEIGHT`| `335`     | Макс. высота px (0 = без ресайза)  |

---

## 5. Загрузка файла

> Используется `multipart/form-data` (не JSON!)

```bash
curl -X POST https://site.com/api.php \
  -F "action=upload_file" \
  -F "api_key=ВАШ_КЛЮЧ" \
  -F "file=@/path/to/archive.zip"
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "file_url": "/uploads/files/2026-02/archive_a3f1b2c4.zip",
    "filename": "archive_a3f1b2c4.zip",
    "original_name": "archive.zip",
    "size": 5242880,
    "size_human": "5 МБ",
    "extension": "zip",
    "news_id": null,
    "linked_to_news": false
  },
  "message": "Файл успешно загружен"
}
```

### Разрешённые расширения

`zip`, `rar`, `7z`, `tar`, `gz`, `pdf`, `doc`, `docx`, `xls`, `xlsx`, `txt`, `csv`

### Ограничения

- Максимальный размер: **100 МБ**
- PHP-файлы и исполняемые файлы **запрещены**
- Проверяется содержимое на наличие опасного кода

---

## 6. Загрузка файла с привязкой к новости

```bash
curl -X POST https://site.com/api.php \
  -F "action=upload_file" \
  -F "api_key=ВАШ_КЛЮЧ" \
  -F "file=@/path/to/document.pdf" \
  -F "news_id=156" \
  -F "description=Инструкция к фильму в PDF"
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "file_url": "/uploads/files/2026-02/document_c7d9e1f3.pdf",
    "filename": "document_c7d9e1f3.pdf",
    "original_name": "document.pdf",
    "size": 1048576,
    "size_human": "1 МБ",
    "extension": "pdf",
    "news_id": 156,
    "linked_to_news": true
  },
  "message": "Файл успешно загружен"
}
```

### Загрузка нескольких файлов к одной новости

Загрузка файлов по одному — API принимает один файл за запрос:

```bash
# Файл 1
curl -X POST https://site.com/api.php \
  -F "action=upload_file" \
  -F "api_key=ВАШ_КЛЮЧ" \
  -F "file=@subtitles.zip" \
  -F "news_id=156" \
  -F "description=Субтитры (RUS)"

# Файл 2
curl -X POST https://site.com/api.php \
  -F "action=upload_file" \
  -F "api_key=ВАШ_КЛЮЧ" \
  -F "file=@soundtrack.zip" \
  -F "news_id=156" \
  -F "description=Саундтрек (MP3)"
```

---

## 7. Обновление новости

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_news",
    "api_key": "ВАШ_КЛЮЧ",
    "news_id": 156,
    "title": "Обновлённый заголовок",
    "short_story": "<p>Обновлённое краткое описание</p>",
    "full_story": "<p>Обновлённый полный текст</p>",
    "category": "5",
    "tags": "новый тег1, новый тег2",
    "keywords": "новые ключевые слова",
    "description": "Обновлённое meta-описание"
  }'
```

### Публикация ранее неопубликованной новости через API

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_news",
    "api_key": "ВАШ_КЛЮЧ",
    "news_id": 156,
    "approve": 1
  }'
```

### Обновление xfields с новым постером

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_news",
    "api_key": "ВАШ_КЛЮЧ",
    "news_id": 156,
    "xfields": {
      "kinopoisk_id": "447301",
      "poster": "https://avatars.mds.yandex.net/get-kinopoisk-image/new-poster/orig",
      "year": "2010",
      "quality": "BDRip 1080p"
    }
  }'
```

### Все обновляемые поля

| Поле             | Описание                          |
|------------------|-----------------------------------|
| `title`          | Заголовок                         |
| `short_story`    | Краткое описание (HTML)           |
| `full_story`     | Полный текст (HTML)               |
| `category`       | ID категории                      |
| `author`         | Имя автора                        |
| `keywords`       | Meta keywords                     |
| `description`    | Meta description                  |
| `metatitle`      | Meta title                        |
| `approve`        | Статус публикации (0/1)           |
| `allow_comments` | Разрешить комментарии (0/1)       |
| `allow_main`     | Показывать на главной (0/1)       |
| `allow_rating`   | Разрешить голосование (0/1)       |
| `fixed`          | Закреплённая новость (0/1)        |
| `tags`           | Теги через запятую                |
| `xfields`        | Дополнительные поля (объект)      |

---

## 8. Удаление новости

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete_news",
    "api_key": "ВАШ_КЛЮЧ",
    "news_id": 156
  }'
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "news_id": 156,
    "title": "Фильм: Начало (2010)",
    "deleted": true,
    "cleanup": true
  },
  "message": "Новость удалена"
}
```

> При удалении очищаются: `post_extras`, `post_extras_cats`, `tags`, `xfsearch`, `comments` и ссылки из `related_ids` других новостей.

---

## 9. Получение списка новостей

**Без аутентификации.**

```bash
# Базовый запрос — 10 последних опубликованных
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "get_news"}'

# С параметрами
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_news",
    "limit": 20,
    "offset": 0,
    "category": 3,
    "order_by": "date",
    "order_direction": "DESC",
    "approved_only": 1
  }'

# Все новости включая неопубликованные
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_news",
    "approved_only": 0,
    "limit": 50
  }'
```

### Параметры

| Параметр          | Тип    | По умолчанию | Описание                           |
|-------------------|--------|--------------|-------------------------------------|
| `limit`           | int    | `10`         | Количество (макс. 100)              |
| `offset`          | int    | `0`          | Смещение для пагинации              |
| `category`        | int    | `0`          | Фильтр по ID категории (0 = все)    |
| `approved_only`   | int    | `1`          | `1` — только опубликованные          |
| `order_by`        | string | `"date"`     | Поле сортировки: `id`, `date`, `title`, `news_read`, `rating` |
| `order_direction` | string | `"DESC"`     | `ASC` или `DESC`                     |

---

## 10. Получение новости по ID

**Без аутентификации.** Увеличивает счётчик просмотров.

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "get_news_by_id", "news_id": 156}'
```

**Ответ содержит:** `id`, `title`, `short_story`, `full_story`, `date`, `category`, `author`, `views`, `comments`, `rating`, `approve`, `allow_main`, `alt_name`, `keywords`, `description`, `metatitle`, `xfields` (разобранные в объект), `url`.

---

## 11. Поиск новостей

**Без аутентификации.** Ищет по: `title`, `short_story`, `full_story`, `keywords`.

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "search_news",
    "query": "Нолан",
    "limit": 10,
    "offset": 0
  }'
```

---

## 12. Получение категорий

**Без аутентификации.**

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "get_categories"}'
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "categories": [
      {"id": 1, "name": "Фильмы", "alt_name": "films", "description": "", "sort": 0},
      {"id": 2, "name": "Сериалы", "alt_name": "serials", "description": "", "sort": 1}
    ]
  }
}
```

---

## 13. Добавление категории

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_category",
    "api_key": "ВАШ_КЛЮЧ",
    "name": "Мультфильмы",
    "alt_name": "cartoons",
    "description": "Категория мультфильмов",
    "sort": 5
  }'
```

---

## 14. Статистика

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_stats",
    "api_key": "ВАШ_КЛЮЧ"
  }'
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "total_news": 1250,
    "approved_news": 1230,
    "pending_news": 20,
    "total_categories": 8,
    "total_views": 450000,
    "average_views": 360.0,
    "total_comments": 3200,
    "popular_news": [
      {"id": 42, "title": "Популярная новость", "views": 15000}
    ],
    "dle_version": "17+"
  }
}
```

---

## 15. Статус новости

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_news_status",
    "api_key": "ВАШ_КЛЮЧ",
    "news_id": 156
  }'
```

**Ответ содержит:** `id`, `title`, `approve`, `allow_main`, `date`, `views`, `comments`, `has_extras`, `has_related`, `categories_indexed`.

---

## 16. Проверка дубликата

Проверяет наличие новости по `kinopoisk_id` (через `xfsearch` и `xfields`).

```bash
curl -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "check_duplicate",
    "api_key": "ВАШ_КЛЮЧ",
    "kinopoisk_id": "447301"
  }'
```

**Если фильм уже есть:**
```json
{
  "success": true,
  "data": {
    "exists": true,
    "news_id": 156,
    "title": "Фильм: Начало (2010)",
    "alt_name": "film-nachalo-2010-1737456000",
    "url": "https://site.com/156-film-nachalo-2010-1737456000.html",
    "kinopoisk_id": "447301"
  },
  "message": "Фильм уже существует в базе"
}
```

**Если фильма нет:**
```json
{
  "success": true,
  "data": {
    "exists": false,
    "kinopoisk_id": "447301"
  }
}
```

---

## 17. Полный пример

Сценарий: проверка дубликата → создание неопубликованной новости → загрузка файлов.

### Шаг 1. Проверка дубликата

```bash
curl -s -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "check_duplicate",
    "api_key": "ВАШ_КЛЮЧ",
    "kinopoisk_id": "447301"
  }' | jq '.data.exists'
```

### Шаг 2. Создание неопубликованной новости с постером

```bash
NEWS_RESPONSE=$(curl -s -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_news",
    "api_key": "ВАШ_КЛЮЧ",
    "title": "Начало / Inception (2010)",
    "short_story": "<p>Дом Кобб — талантливый вор, лучший из лучших в опасном искусстве извлечения.</p>",
    "full_story": "<p>Полный текст описания фильма со всеми деталями...</p>",
    "category": "3",
    "author": "admin",
    "approve": 0,
    "allow_comments": 1,
    "allow_main": 1,
    "allow_rating": 1,
    "fixed": 0,
    "tags": "фантастика, триллер, боевик, Нолан, 2010",
    "keywords": "начало, inception, смотреть онлайн",
    "description": "Смотреть онлайн Начало (Inception) 2010 в хорошем качестве",
    "metatitle": "Начало (2010) — смотреть онлайн бесплатно",
    "xfields": {
      "kinopoisk_id": "447301",
      "imdb_id": "tt1375666",
      "poster": "https://avatars.mds.yandex.net/get-kinopoisk-image/1234/example/orig",
      "year": "2010",
      "genre": "фантастика, боевик, триллер, детектив",
      "country": "США, Великобритания",
      "director": "Кристофер Нолан",
      "actors": "Леонардо ДиКаприо, Джозеф Гордон-Левитт, Эллиот Пейдж",
      "quality": "BDRip 1080p",
      "duration": "148 мин.",
      "rating_kp": "8.7",
      "rating_imdb": "8.8",
      "age": "12+",
      "translator": "Дублированный, Многоголосый"
    }
  }')

# Извлекаем ID созданной новости
NEWS_ID=$(echo "$NEWS_RESPONSE" | jq -r '.data.news_id')
echo "Создана новость ID: $NEWS_ID"
```

### Шаг 3. Загрузка файлов к новости

```bash
# Загрузка субтитров
curl -X POST https://site.com/api.php \
  -F "action=upload_file" \
  -F "api_key=ВАШ_КЛЮЧ" \
  -F "file=@subtitles_rus.zip" \
  -F "news_id=$NEWS_ID" \
  -F "description=Субтитры (Русские)"

# Загрузка доп. материалов
curl -X POST https://site.com/api.php \
  -F "action=upload_file" \
  -F "api_key=ВАШ_КЛЮЧ" \
  -F "file=@bonus_materials.rar" \
  -F "news_id=$NEWS_ID" \
  -F "description=Бонусные материалы"
```

### Шаг 4. Проверка статуса

```bash
curl -s -X POST https://site.com/api.php \
  -H "Content-Type: application/json" \
  -d "{
    \"action\": \"get_news_status\",
    \"api_key\": \"ВАШ_КЛЮЧ\",
    \"news_id\": $NEWS_ID
  }" | jq .
```

> Теперь новость ждёт в админке DLE → **Публикация материалов** → выбрать → **Опубликовать**.

---

## Коды ошибок

| HTTP-код | Описание                      |
|----------|-------------------------------|
| `200`    | Успешный запрос               |
| `400`    | Некорректные данные / пустые обязательные поля |
| `401`    | Ошибка аутентификации         |
| `403`    | Файл запрещён (опасное расширение/содержимое) |
| `404`    | Новость не найдена            |
| `429`    | Превышен лимит запросов       |
| `500`    | Внутренняя ошибка сервера / БД |

### Формат ошибки

```json
{
  "success": false,
  "error": "Описание ошибки",
  "code": 400,
  "timestamp": 1737456000,
  "api_version": "4.0"
}
```

### Формат успешного ответа

```json
{
  "success": true,
  "data": { ... },
  "message": "Описание (опционально)",
  "timestamp": 1737456000,
  "api_version": "4.0"
}
```

---

## Лимиты

- **Rate limit:** 500 запросов в час с одного IP
- **Максимум новостей за запрос:** 100
- **Максимальный размер файла:** 100 МБ
- **Максимальный размер постера:** 5 МБ (скачивание по URL)
