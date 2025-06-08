<?php
/**
 * Полноценный DLE News API
 * Работает БЕЗ подключения engine/init.php
 * Версия: 3.0 - Full Edition
 * Функционал: добавление, получение, редактирование, удаление новостей
 */

// НАСТРОЙКИ - ОБЯЗАТЕЛЬНО ИЗМЕНИТЕ!
define('API_VERSION', '3.0');
define('API_SECRET_KEY', 'your_secret_key'); // ЗАМЕНИТЕ НА СВОЙ КЛЮЧ!
define('API_RATE_LIMIT', 100);

// Настройки подключения к БД (заполните своими данными)
define('DB_HOST', 'localhost');
define('DB_NAME', 'dj-x');
define('DB_USER', 'dj-x');
define('DB_PASS', 'wiNFLr6K4hVm');
define('DB_PREFIX', 'dle_'); // Префикс таблиц DLE

// Отключаем отображение ошибок в продакшене
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

class FullDLEAPI {
    private $db;
    private $db_connected = false;
    private $post_table = null;
    private $category_table = null;
    private $user_table = null;
    
    public function __construct() {
        $this->setHeaders();
        $this->logRequest();
        
        // Попытка подключения к БД
        $this->connectDatabase();
        
        // Определение таблиц
        if ($this->db_connected) {
            $this->findTables();
        }
    }
    
    /**
     * Подключение к базе данных
     */
    private function connectDatabase() {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            $this->db_connected = true;
            $this->log('База данных подключена успешно');
            
        } catch (PDOException $e) {
            $this->db_connected = false;
            $this->log('Ошибка подключения к БД: ' . $e->getMessage());
        }
    }
    
    /**
     * Определение названий таблиц
     */
    private function findTables() {
        $possible_prefixes = [DB_PREFIX, 'dle_', 'datalife_', ''];
        
        // Поиск таблицы постов
        foreach ($possible_prefixes as $prefix) {
            foreach (['post', 'posts', 'news'] as $table_name) {
                $full_name = $prefix . $table_name;
                if ($this->tableExists($full_name)) {
                    $this->post_table = $full_name;
                    break 2;
                }
            }
        }
        
        // Поиск таблицы категорий
        foreach ($possible_prefixes as $prefix) {
            foreach (['category', 'categories'] as $table_name) {
                $full_name = $prefix . $table_name;
                if ($this->tableExists($full_name)) {
                    $this->category_table = $full_name;
                    break 2;
                }
            }
        }
        
        // Поиск таблицы пользователей
        foreach ($possible_prefixes as $prefix) {
            foreach (['users', 'user'] as $table_name) {
                $full_name = $prefix . $table_name;
                if ($this->tableExists($full_name)) {
                    $this->user_table = $full_name;
                    break 2;
                }
            }
        }
        
        $this->log("Найденные таблицы - Посты: {$this->post_table}, Категории: {$this->category_table}, Пользователи: {$this->user_table}");
    }
    
    /**
     * Проверка существования таблицы
     */
    private function tableExists($table_name) {
        try {
            $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table_name]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Обработка входящих запросов
     */
    public function handleRequest() {
        try {
            // Обработка OPTIONS (CORS)
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit;
            }
            
            // Получение данных
            $input = $this->getInputData();
            
            if (!$input) {
                return $this->sendError('Некорректные данные запроса', 400);
            }
            
            // Аутентификация (не требуется для получения новостей)
            $action = $input['action'] ?? 'test';
            $read_actions = ['get_news', 'get_news_by_id', 'get_categories', 'search_news', 'test', 'test_connection'];
            
            if (!in_array($action, $read_actions)) {
                if (!$this->authenticate($input)) {
                    return $this->sendError('Ошибка аутентификации', 401);
                }
            }
            
            // Лимит запросов
            if (!$this->checkRateLimit()) {
                return $this->sendError('Превышен лимит запросов', 429);
            }
            
            // Выполнение действия
            $this->log("Выполняется действие: $action");
            
            switch ($action) {
                // Управление новостями
                case 'add_news':
                    return $this->addNews($input);
                case 'update_news':
                    return $this->updateNews($input);
                case 'delete_news':
                    return $this->deleteNews($input);
                case 'get_news_status':
                    return $this->getNewsStatus($input);
                
                // Получение новостей
                case 'get_news':
                    return $this->getNews($input);
                case 'get_news_by_id':
                    return $this->getNewsById($input);
                case 'search_news':
                    return $this->searchNews($input);
                
                // Категории
                case 'get_categories':
                    return $this->getCategories();
                case 'add_category':
                    return $this->addCategory($input);
                
                // Статистика
                case 'get_stats':
                    return $this->getStats();
                
                // Тест
                case 'test':
                case 'test_connection':
                default:
                    return $this->testConnection();
            }
            
        } catch (Exception $e) {
            $this->log('Критическая ошибка: ' . $e->getMessage());
            return $this->sendError('Внутренняя ошибка сервера: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Установка заголовков
     */
    private function setHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('X-API-Version: ' . API_VERSION);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
    
    /**
     * Получение входных данных
     */
    private function getInputData() {
        $input = null;
        
        // POST JSON
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw_input = file_get_contents('php://input');
            if (!empty($raw_input)) {
                $input = json_decode($raw_input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->log('JSON ошибка: ' . json_last_error_msg());
                    $input = null;
                }
            }
        }
        
        // POST данные
        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }
        
        // GET параметры
        if (!$input && !empty($_GET)) {
            $input = $_GET;
        }
        
        // Тестовые данные по умолчанию
        if (!$input) {
            $input = ['action' => 'test'];
        }
        
        return $input;
    }
    
    /**
     * Аутентификация
     */
    private function authenticate($input) {
        $api_key = $input['api_key'] ?? '';
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        // Проверка API ключа
        if (!empty($api_key)) {
            if ($api_key === API_SECRET_KEY) {
                $this->log('Аутентификация по API ключу успешна');
                return true;
            } else {
                $this->log('Неверный API ключ');
                return false;
            }
        }
        
        // Проверка логина/пароля через БД
        if (!empty($username) && !empty($password) && $this->db_connected && $this->user_table) {
            try {
                $stmt = $this->db->prepare("SELECT user_id, user_group, password FROM `{$this->user_table}` WHERE name = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $this->log('Аутентификация через БД успешна для пользователя: ' . $username);
                    return true;
                }
            } catch (PDOException $e) {
                $this->log('Ошибка проверки пользователя: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Проверка лимита запросов
     */
    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $cache_file = sys_get_temp_dir() . '/dle_api_limit_' . md5($ip) . '.json';
        
        $limit_data = [];
        if (file_exists($cache_file)) {
            $content = file_get_contents($cache_file);
            $limit_data = json_decode($content, true) ?: [];
        }
        
        $current_hour = date('Y-m-d H');
        $limit_data[$current_hour] = ($limit_data[$current_hour] ?? 0) + 1;
        
        // Очистка старых записей
        foreach ($limit_data as $hour => $count) {
            if ($hour < date('Y-m-d H', strtotime('-1 hour'))) {
                unset($limit_data[$hour]);
            }
        }
        
        file_put_contents($cache_file, json_encode($limit_data));
        
        return $limit_data[$current_hour] <= API_RATE_LIMIT;
    }
    
    /**
     * Тест соединения
     */
    private function testConnection() {
        $response = [
            'api_status' => 'working',
            'version' => API_VERSION,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'database_connected' => $this->db_connected,
            'tables_found' => [
                'posts' => $this->post_table,
                'categories' => $this->category_table,
                'users' => $this->user_table
            ],
            'server_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ],
            'available_actions' => [
                'add_news', 'update_news', 'delete_news', 'get_news_status',
                'get_news', 'get_news_by_id', 'search_news',
                'get_categories', 'add_category',
                'get_stats'
            ]
        ];
        
        if (!$this->db_connected) {
            $response['note'] = 'БД не подключена. Проверьте настройки подключения в api.php';
        }
        
        return $this->sendSuccess($response, 'API работает корректно');
    }
    
    /**
     * Получение списка новостей
     */
    private function getNews($data) {
        if (!$this->db_connected || !$this->post_table) {
            // Тестовые новости
            $news = [
                [
                    'id' => 1,
                    'title' => 'Тестовая новость 1',
                    'short_story' => 'Краткое описание первой новости...',
                    'date' => date('Y-m-d H:i:s'),
                    'category' => 1,
                    'author' => 'admin',
                    'views' => 100
                ],
                [
                    'id' => 2,
                    'title' => 'Тестовая новость 2',
                    'short_story' => 'Краткое описание второй новости...',
                    'date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'category' => 2,
                    'author' => 'admin',
                    'views' => 50
                ]
            ];
            
            return $this->sendSuccess([
                'news' => $news,
                'total' => count($news),
                'test_mode' => true
            ], 'Тестовые новости (БД не подключена)');
        }
        
        try {
            // Параметры запроса
            $limit = intval($data['limit'] ?? 10);
            $offset = intval($data['offset'] ?? 0);
            $category = intval($data['category'] ?? 0);
            $approved_only = isset($data['approved_only']) ? intval($data['approved_only']) : 1;
            $order_by = $data['order_by'] ?? 'date';
            $order_direction = strtoupper($data['order_direction'] ?? 'DESC');
            
            // Ограничения
            $limit = min($limit, 100); // Максимум 100 записей
            if (!in_array($order_direction, ['ASC', 'DESC'])) {
                $order_direction = 'DESC';
            }
            
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            // Формирование SELECT
            $select_fields = ['id'];
            $field_mapping = [
                'title' => 'title',
                'short_story' => ['short_story', 'excerpt'],
                'full_story' => ['full_story', 'content'],
                'date' => ['date', 'created_at'],
                'category' => 'category',
                'author' => ['autor', 'author'],
                'views' => ['news_read', 'views'],
                'comments' => ['comm_num', 'comments_count'],
                'rating' => 'rating',
                'approve' => 'approve',
                'allow_main' => 'allow_main',
                'alt_name' => 'alt_name'
            ];
            
            foreach ($field_mapping as $alias => $field_variants) {
                if (is_array($field_variants)) {
                    foreach ($field_variants as $variant) {
                        if (in_array($variant, $available_fields)) {
                            $select_fields[] = "$variant as $alias";
                            break;
                        }
                    }
                } else {
                    if (in_array($field_variants, $available_fields)) {
                        $select_fields[] = $field_variants;
                    }
                }
            }
            
            // WHERE условия
            $where_conditions = [];
            $bindings = [];
            
            if ($approved_only && in_array('approve', $available_fields)) {
                $where_conditions[] = 'approve = ?';
                $bindings[] = 1;
            }
            
            if ($category > 0 && in_array('category', $available_fields)) {
                $where_conditions[] = 'category = ?';
                $bindings[] = $category;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // ORDER BY
            $valid_order_fields = ['id', 'date', 'created_at', 'title', 'news_read', 'views', 'rating'];
            if (!in_array($order_by, $valid_order_fields) || !in_array($order_by, $available_fields)) {
                $order_by = 'id';
            }
            
            // Запрос на получение новостей
            $sql = "SELECT " . implode(', ', $select_fields) . " 
                   FROM `{$this->post_table}` 
                   $where_clause 
                   ORDER BY $order_by $order_direction 
                   LIMIT $limit OFFSET $offset";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $news = $stmt->fetchAll();
            
            // Запрос на получение общего количества
            $count_sql = "SELECT COUNT(*) as total FROM `{$this->post_table}` $where_clause";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($bindings);
            $total = $count_stmt->fetch()['total'];
            
            // Обработка результатов
            foreach ($news as &$item) {
                // Добавляем URL новости
                if (isset($item['alt_name']) && $item['alt_name']) {
                    $item['url'] = $this->getNewsUrl($item['id'], $item['alt_name']);
                }
                
                // Обрезаем длинные тексты для списка
                if (isset($item['short_story']) && strlen($item['short_story']) > 300) {
                    $item['short_story'] = mb_substr($item['short_story'], 0, 300, 'UTF-8') . '...';
                }
                
                // Удаляем full_story из списка (оставляем только для детального просмотра)
                unset($item['full_story']);
            }
            
            $this->log("Получено новостей: " . count($news) . " из $total");
            
            return $this->sendSuccess([
                'news' => $news,
                'total' => intval($total),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]);
            
        } catch (PDOException $e) {
            $this->log('Ошибка получения новостей: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Получение новости по ID
     */
    private function getNewsById($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        
        if (!$news_id) {
            return $this->sendError('ID новости не указан', 400);
        }
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess([
                'id' => $news_id,
                'title' => 'Тестовая новость #' . $news_id,
                'short_story' => 'Краткое описание тестовой новости...',
                'full_story' => 'Полный текст тестовой новости. Здесь был бы полный контент новости.',
                'date' => date('Y-m-d H:i:s'),
                'test_mode' => true
            ]);
        }
        
        try {
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            // Формирование SELECT с маппингом полей
            $select_fields = ['id'];
            $field_mapping = [
                'title' => 'title',
                'short_story' => ['short_story', 'excerpt'],
                'full_story' => ['full_story', 'content'],
                'date' => ['date', 'created_at'],
                'category' => 'category',
                'author' => ['autor', 'author'],
                'views' => ['news_read', 'views'],
                'comments' => ['comm_num', 'comments_count'],
                'rating' => 'rating',
                'approve' => 'approve',
                'allow_main' => 'allow_main',
                'alt_name' => 'alt_name',
                'keywords' => 'keywords',
                'description' => 'descr',
                'xfields' => 'xfields'
            ];
            
            foreach ($field_mapping as $alias => $field_variants) {
                if (is_array($field_variants)) {
                    foreach ($field_variants as $variant) {
                        if (in_array($variant, $available_fields)) {
                            $select_fields[] = "$variant as $alias";
                            break;
                        }
                    }
                } else {
                    if (in_array($field_variants, $available_fields)) {
                        $select_fields[] = $field_variants;
                    }
                }
            }
            
            $sql = "SELECT " . implode(', ', $select_fields) . " FROM `{$this->post_table}` WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$news_id]);
            $news = $stmt->fetch();
            
            if (!$news) {
                return $this->sendError('Новость не найдена', 404);
            }
            
            // Обработка дополнительных полей
            if (isset($news['xfields']) && $news['xfields']) {
                $xfields = [];
                $pairs = explode('||', $news['xfields']);
                foreach ($pairs as $pair) {
                    if (strpos($pair, '|') !== false) {
                        list($key, $value) = explode('|', $pair, 2);
                        $xfields[$key] = $value;
                    }
                }
                $news['xfields'] = $xfields;
            }
            
            // Добавляем URL
            if (isset($news['alt_name']) && $news['alt_name']) {
                $news['url'] = $this->getNewsUrl($news['id'], $news['alt_name']);
            }
            
            // Увеличиваем счетчик просмотров
            $this->incrementViews($news_id);
            
            return $this->sendSuccess($news);
            
        } catch (PDOException $e) {
            $this->log('Ошибка получения новости: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Поиск новостей
     */
    private function searchNews($data) {
        $query = trim($data['query'] ?? '');
        
        if (empty($query)) {
            return $this->sendError('Поисковый запрос не указан', 400);
        }
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess([
                'news' => [],
                'total' => 0,
                'query' => $query,
                'test_mode' => true
            ], 'Поиск недоступен (БД не подключена)');
        }
        
        try {
            $limit = intval($data['limit'] ?? 10);
            $offset = intval($data['offset'] ?? 0);
            $limit = min($limit, 100);
            
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            // Поля для поиска
            $search_fields = [];
            if (in_array('title', $available_fields)) $search_fields[] = 'title';
            if (in_array('short_story', $available_fields)) $search_fields[] = 'short_story';
            if (in_array('full_story', $available_fields)) $search_fields[] = 'full_story';
            if (in_array('keywords', $available_fields)) $search_fields[] = 'keywords';
            
            if (empty($search_fields)) {
                return $this->sendError('Поиск недоступен - нет подходящих полей', 500);
            }
            
            // Формирование WHERE для поиска
            $search_conditions = [];
            $bindings = [];
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "$field LIKE ?";
                $bindings[] = "%$query%";
            }
            
            $where_clause = '(' . implode(' OR ', $search_conditions) . ')';
            
            // Добавляем условие для одобренных новостей
            if (in_array('approve', $available_fields)) {
                $where_clause .= ' AND approve = ?';
                $bindings[] = 1;
            }
            
            // SELECT поля
            $select_fields = ['id', 'title'];
            if (in_array('short_story', $available_fields)) $select_fields[] = 'short_story';
            if (in_array('date', $available_fields)) $select_fields[] = 'date';
            if (in_array('alt_name', $available_fields)) $select_fields[] = 'alt_name';
            
            $sql = "SELECT " . implode(', ', $select_fields) . " 
                   FROM `{$this->post_table}` 
                   WHERE $where_clause 
                   ORDER BY id DESC 
                   LIMIT $limit OFFSET $offset";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $news = $stmt->fetchAll();
            
            // Подсчет общего количества
            $count_sql = "SELECT COUNT(*) as total FROM `{$this->post_table}` WHERE $where_clause";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($bindings);
            $total = $count_stmt->fetch()['total'];
            
            // Добавляем URL к результатам
            foreach ($news as &$item) {
                if (isset($item['alt_name']) && $item['alt_name']) {
                    $item['url'] = $this->getNewsUrl($item['id'], $item['alt_name']);
                }
                
                // Обрезаем текст для поиска
                if (isset($item['short_story']) && strlen($item['short_story']) > 200) {
                    $item['short_story'] = mb_substr($item['short_story'], 0, 200, 'UTF-8') . '...';
                }
            }
            
            return $this->sendSuccess([
                'news' => $news,
                'total' => intval($total),
                'query' => $query,
                'limit' => $limit,
                'offset' => $offset
            ]);
            
        } catch (PDOException $e) {
            $this->log('Ошибка поиска: ' . $e->getMessage());
            return $this->sendError('Ошибка поиска', 500);
        }
    }
    
    /**
     * Увеличение счетчика просмотров
     */
    private function incrementViews($news_id) {
        if (!$this->db_connected || !$this->post_table) {
            return;
        }
        
        try {
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            $views_field = null;
            if (in_array('news_read', $available_fields)) {
                $views_field = 'news_read';
            } elseif (in_array('views', $available_fields)) {
                $views_field = 'views';
            }
            
            if ($views_field) {
                $sql = "UPDATE `{$this->post_table}` SET $views_field = $views_field + 1 WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$news_id]);
            }
            
        } catch (PDOException $e) {
            $this->log('Ошибка увеличения счетчика просмотров: ' . $e->getMessage());
        }
    }
    
    /**
     * Получение категорий
     */
    private function getCategories() {
        if (!$this->db_connected || !$this->category_table) {
            // Тестовые категории
            $categories = [
                ['id' => 1, 'name' => 'Основная', 'alt_name' => 'main'],
                ['id' => 2, 'name' => 'Новости', 'alt_name' => 'news'],
                ['id' => 3, 'name' => 'Статьи', 'alt_name' => 'articles'],
                ['id' => 4, 'name' => 'Технологии', 'alt_name' => 'tech']
            ];
            
            return $this->sendSuccess(['categories' => $categories], 'Тестовые категории (БД не подключена)');
        }
        
        try {
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->category_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            $this->log("Доступные поля категорий: " . implode(', ', $available_fields));
            
            // Формирование SELECT
            $select_fields = [];
            
            if (in_array('id', $available_fields)) {
                $select_fields[] = 'id';
            }
            if (in_array('name', $available_fields)) {
                $select_fields[] = 'name';
            } elseif (in_array('category', $available_fields)) {
                $select_fields[] = 'category as name';
            }
            if (in_array('alt_name', $available_fields)) {
                $select_fields[] = 'alt_name';
            } elseif (in_array('alt', $available_fields)) {
                $select_fields[] = 'alt as alt_name';
            }
            if (in_array('descr', $available_fields)) {
                $select_fields[] = 'descr as description';
            }
            if (in_array('sort', $available_fields)) {
                $select_fields[] = 'sort';
            }
            
            if (empty($select_fields)) {
                return $this->sendError('Некорректная структура таблицы категорий', 500);
            }
            
            // ORDER BY
            $order_by = 'id';
            if (in_array('sort', $available_fields)) {
                $order_by = 'sort';
            } elseif (in_array('position', $available_fields)) {
                $order_by = 'position';
            }
            
            $sql = "SELECT " . implode(', ', $select_fields) . " FROM `{$this->category_table}` ORDER BY $order_by";
            $stmt = $this->db->query($sql);
            $categories = $stmt->fetchAll();
            
            // Нормализация данных
            $normalized_categories = [];
            foreach ($categories as $cat) {
                $normalized_categories[] = [
                    'id' => $cat['id'] ?? 0,
                    'name' => $cat['name'] ?? 'Без названия',
                    'alt_name' => $cat['alt_name'] ?? 'no-name',
                    'description' => $cat['description'] ?? '',
                    'sort' => $cat['sort'] ?? 0
                ];
            }
            
            return $this->sendSuccess(['categories' => $normalized_categories]);
            
        } catch (PDOException $e) {
            $this->log('Ошибка получения категорий: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Добавление категории
     */
    private function addCategory($data) {
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->sendError("Поле '$field' обязательно", 400);
            }
        }
        
        if (!$this->db_connected || !$this->category_table) {
            return $this->sendSuccess([
                'category_id' => rand(100, 999),
                'name' => $data['name'],
                'test_mode' => true
            ], 'Тестовое добавление категории');
        }
        
        try {
            $name = $data['name'];
            $alt_name = $data['alt_name'] ?? $this->createAltName($name);
            $description = $data['description'] ?? '';
            $sort = intval($data['sort'] ?? 0);
            
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->category_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            // Формирование INSERT
            $fields = [];
            $values = [];
            $bindings = [];
            
            if (in_array('name', $available_fields)) {
                $fields[] = 'name';
                $values[] = ':name';
                $bindings[':name'] = $name;
            }
            
            if (in_array('alt_name', $available_fields)) {
                $fields[] = 'alt_name';
                $values[] = ':alt_name';
                $bindings[':alt_name'] = $alt_name;
            }
            
            if (in_array('descr', $available_fields)) {
                $fields[] = 'descr';
                $values[] = ':description';
                $bindings[':description'] = $description;
            }
            
            if (in_array('sort', $available_fields)) {
                $fields[] = 'sort';
                $values[] = ':sort';
                $bindings[':sort'] = $sort;
            }
            
            if (empty($fields)) {
                return $this->sendError('Не найдены подходящие поля для добавления категории', 500);
            }
            
            $sql = "INSERT INTO `{$this->category_table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            
            $category_id = $this->db->lastInsertId();
            
            return $this->sendSuccess([
                'category_id' => $category_id,
                'name' => $name,
                'alt_name' => $alt_name
            ], 'Категория успешно добавлена');
            
        } catch (PDOException $e) {
            $this->log('Ошибка добавления категории: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Добавление новости
     */
    private function addNews($data) {
        // Валидация
        $required = ['title', 'short_story', 'full_story'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->sendError("Поле '$field' обязательно", 400);
            }
        }
        
        if (!$this->db_connected || !$this->post_table) {
            $fake_id = rand(1000, 9999);
            return $this->sendSuccess([
                'news_id' => $fake_id,
                'title' => $data['title'],
                'test_mode' => true
            ], 'Тестовое добавление (БД не подключена)');
        }
        
        try {
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            // Подготовка данных
            $title = $data['title'];
            $short_story = $data['short_story'];
            $full_story = $data['full_story'];
            $category = intval($data['category'] ?? 1);
            $author = $data['author'] ?? 'admin';
            $date = date('Y-m-d H:i:s');
            $alt_name = $data['alt_name'] ?? $this->createAltName($title);
            
            // Формирование INSERT
            $fields = [];
            $values = [];
            $bindings = [];
            
            // Обязательные поля
            if (in_array('title', $available_fields)) {
                $fields[] = 'title';
                $values[] = ':title';
                $bindings[':title'] = $title;
            }
            
            if (in_array('short_story', $available_fields)) {
                $fields[] = 'short_story';
                $values[] = ':short_story';
                $bindings[':short_story'] = $short_story;
            }
            
            if (in_array('full_story', $available_fields)) {
                $fields[] = 'full_story';
                $values[] = ':full_story';
                $bindings[':full_story'] = $full_story;
            }
            
            if (in_array('date', $available_fields)) {
                $fields[] = 'date';
                $values[] = ':date';
                $bindings[':date'] = $date;
            }
            
            if (in_array('autor', $available_fields)) {
                $fields[] = 'autor';
                $values[] = ':author';
                $bindings[':author'] = $author;
            } elseif (in_array('author', $available_fields)) {
                $fields[] = 'author';
                $values[] = ':author';
                $bindings[':author'] = $author;
            }
            
            if (in_array('category', $available_fields)) {
                $fields[] = 'category';
                $values[] = ':category';
                $bindings[':category'] = $category;
            }
            
            if (in_array('alt_name', $available_fields)) {
                $fields[] = 'alt_name';
                $values[] = ':alt_name';
                $bindings[':alt_name'] = $alt_name;
            }
            
            // Дополнительные поля
            $optional_fields = [
                'approve' => intval($data['approve'] ?? 1),
                'allow_comm' => intval($data['allow_comments'] ?? 1),
                'allow_main' => intval($data['allow_main'] ?? 1),
                'allow_rate' => intval($data['allow_rating'] ?? 1),
                'fixed' => intval($data['fixed'] ?? 0),
                'keywords' => $data['keywords'] ?? '',
                'descr' => $data['description'] ?? '',
                'comm_num' => 0,
                'rating' => 0,
                'vote_num' => 0,
                'news_read' => 0,
                'user_id' => intval($data['user_id'] ?? 1)
            ];
            
            foreach ($optional_fields as $field => $value) {
                if (in_array($field, $available_fields)) {
                    $fields[] = $field;
                    $values[] = ":$field";
                    $bindings[":$field"] = $value;
                }
            }
            
            // Дополнительные поля (xfields)
            if (in_array('xfields', $available_fields) && !empty($data['xfields'])) {
                $xf_array = [];
                foreach ($data['xfields'] as $field => $value) {
                    $xf_array[] = $field . '|' . $value;
                }
                $fields[] = 'xfields';
                $values[] = ':xfields';
                $bindings[':xfields'] = implode('||', $xf_array);
            }
            
            $sql = "INSERT INTO `{$this->post_table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            
            $news_id = $this->db->lastInsertId();
            
            if ($news_id) {
                $response = [
                    'news_id' => $news_id,
                    'title' => $title,
                    'alt_name' => $alt_name,
                    'url' => $this->getNewsUrl($news_id, $alt_name),
                    'table_used' => $this->post_table,
                    'fields_used' => count($fields)
                ];
                
                return $this->sendSuccess($response, 'Новость успешно добавлена');
            } else {
                return $this->sendError('Не удалось получить ID новой записи', 500);
            }
            
        } catch (PDOException $e) {
            $this->log('Ошибка добавления новости: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Обновление новости
     */
    private function updateNews($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        
        if (!$news_id) {
            return $this->sendError('ID новости не указан', 400);
        }
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess([
                'news_id' => $news_id,
                'updated_fields' => array_keys($data),
                'test_mode' => true
            ], 'Тестовое обновление');
        }
        
        try {
            // Проверяем существование новости
            $check_stmt = $this->db->prepare("SELECT id FROM `{$this->post_table}` WHERE id = ?");
            $check_stmt->execute([$news_id]);
            
            if (!$check_stmt->fetch()) {
                return $this->sendError('Новость не найдена', 404);
            }
            
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            // Поля для обновления
            $update_fields = [];
            $bindings = [];
            
            $field_mapping = [
                'title' => 'title',
                'short_story' => 'short_story',
                'full_story' => 'full_story',
                'category' => 'category',
                'author' => ['autor', 'author'],
                'keywords' => 'keywords',
                'description' => 'descr',
                'approve' => 'approve',
                'allow_comments' => 'allow_comm',
                'allow_main' => 'allow_main',
                'allow_rating' => 'allow_rate',
                'fixed' => 'fixed'
            ];
            
            foreach ($field_mapping as $input_key => $db_field) {
                if (isset($data[$input_key])) {
                    if (is_array($db_field)) {
                        foreach ($db_field as $field_variant) {
                            if (in_array($field_variant, $available_fields)) {
                                $update_fields[] = "$field_variant = :$input_key";
                                $bindings[":$input_key"] = $data[$input_key];
                                break;
                            }
                        }
                    } else {
                        if (in_array($db_field, $available_fields)) {
                            $update_fields[] = "$db_field = :$input_key";
                            $bindings[":$input_key"] = $data[$input_key];
                        }
                    }
                }
            }
            
            // Обновление alt_name при изменении заголовка
            if (isset($data['title']) && in_array('alt_name', $available_fields)) {
                $alt_name = $data['alt_name'] ?? $this->createAltName($data['title']);
                $update_fields[] = "alt_name = :alt_name";
                $bindings[":alt_name"] = $alt_name;
            }
            
            // Дополнительные поля
            if (isset($data['xfields']) && in_array('xfields', $available_fields)) {
                $xf_array = [];
                foreach ($data['xfields'] as $field => $value) {
                    $xf_array[] = $field . '|' . $value;
                }
                $update_fields[] = "xfields = :xfields";
                $bindings[":xfields"] = implode('||', $xf_array);
            }
            
            if (empty($update_fields)) {
                return $this->sendError('Нет полей для обновления', 400);
            }
            
            $bindings[':news_id'] = $news_id;
            
            $sql = "UPDATE `{$this->post_table}` SET " . implode(', ', $update_fields) . " WHERE id = :news_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            
            $affected_rows = $stmt->rowCount();
            
            return $this->sendSuccess([
                'news_id' => $news_id,
                'updated_fields' => count($update_fields),
                'affected_rows' => $affected_rows
            ], 'Новость успешно обновлена');
            
        } catch (PDOException $e) {
            $this->log('Ошибка обновления новости: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Удаление новости
     */
    private function deleteNews($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        
        if (!$news_id) {
            return $this->sendError('ID новости не указан', 400);
        }
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess([
                'news_id' => $news_id,
                'test_mode' => true
            ], 'Тестовое удаление');
        }
        
        try {
            // Проверяем существование новости
            $check_stmt = $this->db->prepare("SELECT id, title FROM `{$this->post_table}` WHERE id = ?");
            $check_stmt->execute([$news_id]);
            $news = $check_stmt->fetch();
            
            if (!$news) {
                return $this->sendError('Новость не найдена', 404);
            }
            
            // Удаление
            $delete_stmt = $this->db->prepare("DELETE FROM `{$this->post_table}` WHERE id = ?");
            $delete_stmt->execute([$news_id]);
            
            $affected_rows = $delete_stmt->rowCount();
            
            if ($affected_rows > 0) {
                return $this->sendSuccess([
                    'news_id' => $news_id,
                    'title' => $news['title'],
                    'deleted' => true
                ], 'Новость успешно удалена');
            } else {
                return $this->sendError('Не удалось удалить новость', 500);
            }
            
        } catch (PDOException $e) {
            $this->log('Ошибка удаления новости: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Получение статуса новости
     */
    private function getNewsStatus($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        
        if (!$news_id) {
            return $this->sendError('ID новости не указан', 400);
        }
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess([
                'news_id' => $news_id,
                'title' => 'Тестовая новость #' . $news_id,
                'approved' => 1,
                'test_mode' => true
            ]);
        }
        
        try {
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            $select_fields = ['id', 'title'];
            if (in_array('approve', $available_fields)) $select_fields[] = 'approve';
            if (in_array('allow_main', $available_fields)) $select_fields[] = 'allow_main';
            if (in_array('date', $available_fields)) $select_fields[] = 'date';
            if (in_array('news_read', $available_fields)) $select_fields[] = 'news_read as views';
            if (in_array('comm_num', $available_fields)) $select_fields[] = 'comm_num as comments';
            
            $sql = "SELECT " . implode(', ', $select_fields) . " FROM `{$this->post_table}` WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$news_id]);
            $news = $stmt->fetch();
            
            if (!$news) {
                return $this->sendError('Новость не найдена', 404);
            }
            
            return $this->sendSuccess($news);
            
        } catch (PDOException $e) {
            $this->log('Ошибка получения статуса новости: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Получение статистики
     */
    private function getStats() {
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess([
                'total_news' => 150,
                'approved_news' => 140,
                'pending_news' => 10,
                'total_categories' => 5,
                'test_mode' => true
            ], 'Тестовая статистика');
        }
        
        try {
            $stats = [];
            
            // Общее количество новостей
            $total_stmt = $this->db->query("SELECT COUNT(*) as total FROM `{$this->post_table}`");
            $stats['total_news'] = $total_stmt->fetch()['total'];
            
            // Получение структуры таблицы
            $structure = $this->db->query("DESCRIBE `{$this->post_table}`");
            $columns = $structure->fetchAll();
            $available_fields = array_column($columns, 'Field');
            
            // Одобренные новости
            if (in_array('approve', $available_fields)) {
                $approved_stmt = $this->db->query("SELECT COUNT(*) as approved FROM `{$this->post_table}` WHERE approve = 1");
                $stats['approved_news'] = $approved_stmt->fetch()['approved'];
                $stats['pending_news'] = $stats['total_news'] - $stats['approved_news'];
            }
            
            // Количество категорий
            if ($this->category_table) {
                $cat_stmt = $this->db->query("SELECT COUNT(*) as total FROM `{$this->category_table}`");
                $stats['total_categories'] = $cat_stmt->fetch()['total'];
            }
            
            // Статистика просмотров
            if (in_array('news_read', $available_fields)) {
                $views_stmt = $this->db->query("SELECT SUM(news_read) as total_views, AVG(news_read) as avg_views FROM `{$this->post_table}`");
                $views_data = $views_stmt->fetch();
                $stats['total_views'] = intval($views_data['total_views']);
                $stats['average_views'] = round($views_data['avg_views'], 2);
            }
            
            // Статистика комментариев
            if (in_array('comm_num', $available_fields)) {
                $comments_stmt = $this->db->query("SELECT SUM(comm_num) as total_comments FROM `{$this->post_table}`");
                $stats['total_comments'] = intval($comments_stmt->fetch()['total_comments']);
            }
            
            // Популярные новости
            if (in_array('news_read', $available_fields)) {
                $popular_stmt = $this->db->prepare("SELECT id, title, news_read as views FROM `{$this->post_table}` ORDER BY news_read DESC LIMIT 5");
                $popular_stmt->execute();
                $stats['popular_news'] = $popular_stmt->fetchAll();
            }
            
            return $this->sendSuccess($stats);
            
        } catch (PDOException $e) {
            $this->log('Ошибка получения статистики: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Создание alt_name
     */
    private function createAltName($title) {
        $alt_name = mb_strtolower($title, 'UTF-8');
        
        // Транслитерация
        $ru = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'];
        $en = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'];
        $alt_name = str_replace($ru, $en, $alt_name);
        
        // Очистка
        $alt_name = preg_replace('/[^a-z0-9\-_]/', '-', $alt_name);
        $alt_name = preg_replace('/-+/', '-', $alt_name);
        $alt_name = trim($alt_name, '-');
        
        // Ограничение длины
        if (strlen($alt_name) > 50) {
            $alt_name = substr($alt_name, 0, 50);
            $alt_name = rtrim($alt_name, '-');
        }
        
        // Уникальность
        $alt_name .= '-' . time();
        
        return $alt_name;
    }
    
    /**
     * Получение URL новости
     */
    private function getNewsUrl($id, $alt_name) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host/$id-$alt_name.html";
    }
    
    /**
     * Успешный ответ
     */
    private function sendSuccess($data = [], $message = null) {
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => time(),
            'api_version' => API_VERSION
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        $this->log('Успешный ответ: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Ответ с ошибкой
     */
    private function sendError($message, $code = 400) {
        http_response_code($code);
        
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => time(),
            'api_version' => API_VERSION
        ];
        
        $this->log("Ошибка ($code): $message");
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Логирование
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        
        // Пробуем записать лог
        $log_files = [
            'api.log',
            sys_get_temp_dir() . '/dle_api.log'
        ];
        
        foreach ($log_files as $log_file) {
            if (@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX)) {
                break;
            }
        }
    }
    
    /**
     * Логирование запроса
     */
    private function logRequest() {
        $request_info = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'time' => date('Y-m-d H:i:s')
        ];
        
        $this->log('Новый запрос: ' . json_encode($request_info, JSON_UNESCAPED_UNICODE));
    }
}

// Запуск API
try {
    $api = new FullDLEAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Критическая ошибка инициализации API: ' . $e->getMessage(),
        'code' => 500,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
