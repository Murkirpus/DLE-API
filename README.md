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
