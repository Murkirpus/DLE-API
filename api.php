<?php
/**
 * Полноценный DLE News API
 * Работает БЕЗ подключения engine/init.php
 * Версия: 4.0 - DLE 18.1 Compatible
 * Совместимость: DLE 13.x - 18.1+
 * Функционал: добавление, получение, редактирование, удаление новостей
 * Поддержка: post_extras, post_extras_cats, tags, xfsearch, cache clearing
 */

// НАСТРОЙКИ - ОБЯЗАТЕЛЬНО ИЗМЕНИТЕ!
define('API_VERSION', '4.0');
define('API_SECRET_KEY', 'pass'); // ЗАМЕНИТЕ НА СВОЙ КЛЮЧ!
define('API_RATE_LIMIT', 100);

// Настройки подключения к БД (заполните своими данными)
define('DB_HOST', 'localhost');
define('DB_NAME', 'dle19');
define('DB_USER', 'dle19');
define('DB_PASS', 'dle19');
define('DB_PREFIX', 'dle_'); // Префикс таблиц DLE

// Путь к корню DLE (для очистки кеша). Оставьте пустым если не нужно
define('DLE_ROOT', ''); // например: '/var/www/html/mysite'

// Путь к папке uploads DLE (для сохранения постеров)
// Автоопределение: api.php лежит в корне DLE, uploads рядом
define('DLE_UPLOADS_DIR', __DIR__ . '/uploads/posts/');
// URL-префикс для доступа к загруженным файлам
define('DLE_UPLOADS_URL', '/uploads/posts/');

// Настройки постера
define('POSTER_FORMAT', 'webp');    // Формат: 'jpg', 'png', 'webp' или 'original' (не конвертировать)
define('POSTER_QUALITY', 85);       // Качество: 1-100 (для jpg и webp)
define('POSTER_MAX_WIDTH', 223);    // Максимальная ширина в px (0 = не ресайзить)
define('POSTER_MAX_HEIGHT', 335);   // Максимальная высота в px (0 = не ресайзить)

// Настройки загрузки файлов
define('FILES_UPLOAD_DIR', __DIR__ . '/uploads/files/');
define('FILES_UPLOAD_URL', '/uploads/files/');
define('FILES_MAX_SIZE', 100 * 1024 * 1024);  // Максимальный размер: 100 MB
define('FILES_ALLOWED_EXT', 'zip,rar,7z,tar,gz,pdf,doc,docx,xls,xlsx,txt,csv'); // Разрешённые расширения

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
    private $dle_version = null;
    
    public function __construct() {
        $this->setHeaders();
        $this->logRequest();
        
        $this->connectDatabase();
        
        if ($this->db_connected) {
            $this->findTables();
            $this->detectDLEVersion();
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
     * Определение версии DLE по структуре БД
     */
    private function detectDLEVersion() {
        if (!$this->db_connected) return;
        
        try {
            $has_extras_cats = $this->tableExists(DB_PREFIX . 'post_extras_cats');
            $has_xfsearch = $this->tableExists(DB_PREFIX . 'xfsearch');
            
            $has_related = false;
            if ($this->tableExists(DB_PREFIX . 'post_extras')) {
                $stmt = $this->db->query("DESCRIBE `" . DB_PREFIX . "post_extras`");
                $cols = array_column($stmt->fetchAll(), 'Field');
                $has_related = in_array('related_ids', $cols);
            }
            
            $has_metatitle = false;
            if ($this->post_table) {
                $stmt = $this->db->query("DESCRIBE `{$this->post_table}`");
                $cols = array_column($stmt->fetchAll(), 'Field');
                $has_metatitle = in_array('metatitle', $cols);
            }
            
            if ($has_metatitle && $has_related) {
                $this->dle_version = '17+';
            } elseif ($has_xfsearch) {
                $this->dle_version = '15';
            } elseif ($has_extras_cats) {
                $this->dle_version = '13';
            } else {
                $this->dle_version = '12';
            }
            
            $this->log("DLE ~{$this->dle_version} (extras_cats:" . ($has_extras_cats ? 'Y' : 'N') . 
                       " xfsearch:" . ($has_xfsearch ? 'Y' : 'N') . 
                       " related:" . ($has_related ? 'Y' : 'N') . 
                       " metatitle:" . ($has_metatitle ? 'Y' : 'N') . ")");
                       
        } catch (PDOException $e) {
            $this->log('Ошибка определения версии DLE: ' . $e->getMessage());
            $this->dle_version = 'unknown';
        }
    }
    
    /**
     * Определение названий таблиц
     */
    private function findTables() {
        $possible_prefixes = [DB_PREFIX, 'dle_', 'datalife_', ''];
        
        foreach ($possible_prefixes as $prefix) {
            foreach (['post', 'posts', 'news'] as $table_name) {
                $full_name = $prefix . $table_name;
                if ($this->tableExists($full_name)) {
                    $this->post_table = $full_name;
                    break 2;
                }
            }
        }
        
        foreach ($possible_prefixes as $prefix) {
            foreach (['category', 'categories'] as $table_name) {
                $full_name = $prefix . $table_name;
                if ($this->tableExists($full_name)) {
                    $this->category_table = $full_name;
                    break 2;
                }
            }
        }
        
        foreach ($possible_prefixes as $prefix) {
            foreach (['users', 'user'] as $table_name) {
                $full_name = $prefix . $table_name;
                if ($this->tableExists($full_name)) {
                    $this->user_table = $full_name;
                    break 2;
                }
            }
        }
        
        $this->log("Таблицы - Посты: {$this->post_table}, Категории: {$this->category_table}, Юзеры: {$this->user_table}");
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
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit;
            }
            
            $input = $this->getInputData();
            
            if (!$input) {
                return $this->sendError('Некорректные данные запроса', 400);
            }
            
            $action = $input['action'] ?? 'test';
            $read_actions = ['get_news', 'get_news_by_id', 'get_categories', 'search_news', 'test', 'test_connection'];
            
            if (!in_array($action, $read_actions)) {
                if (!$this->authenticate($input)) {
                    return $this->sendError('Ошибка аутентификации', 401);
                }
            }
            
            if (!$this->checkRateLimit()) {
                return $this->sendError('Превышен лимит запросов', 429);
            }
            
            $this->log("Действие: $action");
            
            switch ($action) {
                case 'add_news':      return $this->addNews($input);
                case 'update_news':   return $this->updateNews($input);
                case 'delete_news':   return $this->deleteNews($input);
                case 'get_news_status': return $this->getNewsStatus($input);
                case 'get_news':      return $this->getNews($input);
                case 'get_news_by_id': return $this->getNewsById($input);
                case 'search_news':   return $this->searchNews($input);
                case 'get_categories': return $this->getCategories();
                case 'add_category':  return $this->addCategory($input);
                case 'get_stats':     return $this->getStats();
                case 'upload_file':   return $this->uploadFile($input);
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
    
    private function setHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('X-API-Version: ' . API_VERSION);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
    
    private function getInputData() {
        $input = null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw_input = file_get_contents('php://input');
            $this->log('RAW INPUT [' . strlen($raw_input) . ' bytes]: ' . substr($raw_input, 0, 200));
            
            if (!empty($raw_input)) {
                $input = json_decode($raw_input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->log('JSON ошибка: ' . json_last_error_msg() . ' | Первые 500 байт: ' . substr($raw_input, 0, 500));
                    $input = null;
                } else {
                    $this->log('JSON OK, action=' . ($input['action'] ?? 'NOT SET') . ', keys=' . implode(',', array_keys($input)));
                }
            } else {
                $this->log('WARNING: php://input ПУСТОЙ! Content-Length=' . ($_SERVER['CONTENT_LENGTH'] ?? 'не указан') . ' Content-Type=' . ($_SERVER['CONTENT_TYPE'] ?? 'не указан'));
            }
        }
        
        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }
        
        if (!$input && !empty($_GET)) {
            $input = $_GET;
        }
        
        if (!$input) {
            $this->log('!!! FALLBACK: input пустой после всех проверок, возвращаю action=test');
            $input = ['action' => 'test'];
        }
        
        return $input;
    }
    
    private function authenticate($input) {
        $api_key = $input['api_key'] ?? '';
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        if (!empty($api_key)) {
            if ($api_key === API_SECRET_KEY) {
                $this->log('Auth OK по API ключу');
                return true;
            } else {
                $this->log('Неверный API ключ');
                return false;
            }
        }
        
        if (!empty($username) && !empty($password) && $this->db_connected && $this->user_table) {
            try {
                $stmt = $this->db->prepare("SELECT user_id, user_group, password FROM `{$this->user_table}` WHERE name = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $this->log('Auth OK через БД: ' . $username);
                    return true;
                }
            } catch (PDOException $e) {
                $this->log('Ошибка проверки пользователя: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
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
        
        foreach ($limit_data as $hour => $count) {
            if ($hour < date('Y-m-d H', strtotime('-1 hour'))) {
                unset($limit_data[$hour]);
            }
        }
        
        file_put_contents($cache_file, json_encode($limit_data));
        
        return $limit_data[$current_hour] <= API_RATE_LIMIT;
    }
    
    // ========================================================================
    // ТЕСТ СОЕДИНЕНИЯ
    // ========================================================================
    
    private function testConnection() {
        $response = [
            'api_status' => 'working',
            'version' => API_VERSION,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'database_connected' => $this->db_connected,
            'dle_version' => $this->dle_version ?: 'not detected',
            'tables_found' => [
                'posts' => $this->post_table,
                'categories' => $this->category_table,
                'users' => $this->user_table
            ],
            'server_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'],
            ],
            'available_actions' => [
                'add_news', 'update_news', 'delete_news', 'get_news_status',
                'get_news', 'get_news_by_id', 'search_news',
                'get_categories', 'add_category',
                'get_stats', 'upload_file'
            ]
        ];
        
        if ($this->db_connected) {
            $response['dle_tables'] = [
                'post_extras' => $this->tableExists(DB_PREFIX . 'post_extras'),
                'post_extras_cats' => $this->tableExists(DB_PREFIX . 'post_extras_cats'),
                'tags' => $this->tableExists(DB_PREFIX . 'tags'),
                'xfsearch' => $this->tableExists(DB_PREFIX . 'xfsearch'),
            ];
        }
        
        if (!$this->db_connected) {
            $response['note'] = 'БД не подключена. Проверьте настройки в api.php';
        }
        
        return $this->sendSuccess($response, 'API работает корректно');
    }
    
    // ========================================================================
    // ПОЛУЧЕНИЕ НОВОСТЕЙ
    // ========================================================================
    
    private function getNews($data) {
        if (!$this->db_connected || !$this->post_table) {
            $news = [
                ['id' => 1, 'title' => 'Тестовая новость 1', 'short_story' => 'Краткое описание...', 'date' => date('Y-m-d H:i:s'), 'category' => 1, 'author' => 'admin', 'views' => 100],
                ['id' => 2, 'title' => 'Тестовая новость 2', 'short_story' => 'Краткое описание...', 'date' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'category' => 2, 'author' => 'admin', 'views' => 50]
            ];
            return $this->sendSuccess(['news' => $news, 'total' => count($news), 'test_mode' => true], 'Тестовые новости (БД не подключена)');
        }
        
        try {
            $limit = min(intval($data['limit'] ?? 10), 100);
            $offset = intval($data['offset'] ?? 0);
            $category = intval($data['category'] ?? 0);
            $approved_only = isset($data['approved_only']) ? intval($data['approved_only']) : 1;
            $order_by = $data['order_by'] ?? 'date';
            $order_direction = strtoupper($data['order_direction'] ?? 'DESC');
            
            if (!in_array($order_direction, ['ASC', 'DESC'])) $order_direction = 'DESC';
            
            $available_fields = $this->getTableFields($this->post_table);
            
            $select_fields = ['id'];
            $field_mapping = [
                'title' => 'title', 'short_story' => ['short_story', 'excerpt'], 'full_story' => ['full_story', 'content'],
                'date' => ['date', 'created_at'], 'category' => 'category', 'author' => ['autor', 'author'],
                'views' => ['news_read', 'views'], 'comments' => ['comm_num', 'comments_count'],
                'rating' => 'rating', 'approve' => 'approve', 'allow_main' => 'allow_main',
                'alt_name' => 'alt_name', 'metatitle' => 'metatitle'
            ];
            
            foreach ($field_mapping as $alias => $field_variants) {
                if (is_array($field_variants)) {
                    foreach ($field_variants as $variant) {
                        if (in_array($variant, $available_fields)) { $select_fields[] = "$variant as $alias"; break; }
                    }
                } else {
                    if (in_array($field_variants, $available_fields)) $select_fields[] = $field_variants;
                }
            }
            
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
            
            $valid_order_fields = ['id', 'date', 'created_at', 'title', 'news_read', 'views', 'rating'];
            if (!in_array($order_by, $valid_order_fields) || !in_array($order_by, $available_fields)) $order_by = 'id';
            
            $sql = "SELECT " . implode(', ', $select_fields) . " FROM `{$this->post_table}` $where_clause ORDER BY $order_by $order_direction LIMIT $limit OFFSET $offset";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $news = $stmt->fetchAll();
            
            $count_sql = "SELECT COUNT(*) as total FROM `{$this->post_table}` $where_clause";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($bindings);
            $total = $count_stmt->fetch()['total'];
            
            foreach ($news as &$item) {
                if (isset($item['alt_name']) && $item['alt_name']) $item['url'] = $this->getNewsUrl($item['id'], $item['alt_name']);
                if (isset($item['short_story']) && strlen($item['short_story']) > 300) $item['short_story'] = mb_substr($item['short_story'], 0, 300, 'UTF-8') . '...';
                unset($item['full_story']);
            }
            
            return $this->sendSuccess(['news' => $news, 'total' => intval($total), 'limit' => $limit, 'offset' => $offset, 'has_more' => ($offset + $limit) < $total]);
            
        } catch (PDOException $e) {
            $this->log('Ошибка получения новостей: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    private function getNewsById($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        if (!$news_id) return $this->sendError('ID новости не указан', 400);
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess(['id' => $news_id, 'title' => 'Тестовая новость #' . $news_id, 'short_story' => 'Тест...', 'full_story' => 'Тест...', 'date' => date('Y-m-d H:i:s'), 'test_mode' => true]);
        }
        
        try {
            $available_fields = $this->getTableFields($this->post_table);
            
            $select_fields = ['id'];
            $field_mapping = [
                'title' => 'title', 'short_story' => ['short_story', 'excerpt'], 'full_story' => ['full_story', 'content'],
                'date' => ['date', 'created_at'], 'category' => 'category', 'author' => ['autor', 'author'],
                'views' => ['news_read', 'views'], 'comments' => ['comm_num', 'comments_count'],
                'rating' => 'rating', 'approve' => 'approve', 'allow_main' => 'allow_main',
                'alt_name' => 'alt_name', 'keywords' => 'keywords', 'description' => 'descr',
                'metatitle' => 'metatitle', 'xfields' => 'xfields'
            ];
            
            foreach ($field_mapping as $alias => $field_variants) {
                if (is_array($field_variants)) {
                    foreach ($field_variants as $variant) {
                        if (in_array($variant, $available_fields)) { $select_fields[] = "$variant as $alias"; break; }
                    }
                } else {
                    if (in_array($field_variants, $available_fields)) $select_fields[] = $field_variants;
                }
            }
            
            $sql = "SELECT " . implode(', ', $select_fields) . " FROM `{$this->post_table}` WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$news_id]);
            $news = $stmt->fetch();
            
            if (!$news) return $this->sendError('Новость не найдена', 404);
            
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
            
            if (isset($news['alt_name']) && $news['alt_name']) $news['url'] = $this->getNewsUrl($news['id'], $news['alt_name']);
            
            $this->incrementViews($news_id);
            
            return $this->sendSuccess($news);
            
        } catch (PDOException $e) {
            $this->log('Ошибка получения новости: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    private function searchNews($data) {
        $query = trim($data['query'] ?? '');
        if (empty($query)) return $this->sendError('Поисковый запрос не указан', 400);
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess(['news' => [], 'total' => 0, 'query' => $query, 'test_mode' => true], 'Поиск недоступен (БД не подключена)');
        }
        
        try {
            $limit = min(intval($data['limit'] ?? 10), 100);
            $offset = intval($data['offset'] ?? 0);
            
            $available_fields = $this->getTableFields($this->post_table);
            
            $search_fields = [];
            if (in_array('title', $available_fields)) $search_fields[] = 'title';
            if (in_array('short_story', $available_fields)) $search_fields[] = 'short_story';
            if (in_array('full_story', $available_fields)) $search_fields[] = 'full_story';
            if (in_array('keywords', $available_fields)) $search_fields[] = 'keywords';
            
            if (empty($search_fields)) return $this->sendError('Поиск недоступен', 500);
            
            $search_conditions = [];
            $bindings = [];
            foreach ($search_fields as $field) {
                $search_conditions[] = "$field LIKE ?";
                $bindings[] = "%$query%";
            }
            
            $where_clause = '(' . implode(' OR ', $search_conditions) . ')';
            if (in_array('approve', $available_fields)) { $where_clause .= ' AND approve = ?'; $bindings[] = 1; }
            
            $select_fields = ['id', 'title'];
            if (in_array('short_story', $available_fields)) $select_fields[] = 'short_story';
            if (in_array('date', $available_fields)) $select_fields[] = 'date';
            if (in_array('alt_name', $available_fields)) $select_fields[] = 'alt_name';
            
            $sql = "SELECT " . implode(', ', $select_fields) . " FROM `{$this->post_table}` WHERE $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $news = $stmt->fetchAll();
            
            $count_sql = "SELECT COUNT(*) as total FROM `{$this->post_table}` WHERE $where_clause";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($bindings);
            $total = $count_stmt->fetch()['total'];
            
            foreach ($news as &$item) {
                if (isset($item['alt_name']) && $item['alt_name']) $item['url'] = $this->getNewsUrl($item['id'], $item['alt_name']);
                if (isset($item['short_story']) && strlen($item['short_story']) > 200) $item['short_story'] = mb_substr($item['short_story'], 0, 200, 'UTF-8') . '...';
            }
            
            return $this->sendSuccess(['news' => $news, 'total' => intval($total), 'query' => $query, 'limit' => $limit, 'offset' => $offset]);
            
        } catch (PDOException $e) {
            $this->log('Ошибка поиска: ' . $e->getMessage());
            return $this->sendError('Ошибка поиска', 500);
        }
    }
    
    private function incrementViews($news_id) {
        if (!$this->db_connected || !$this->post_table) return;
        try {
            $available_fields = $this->getTableFields($this->post_table);
            $views_field = in_array('news_read', $available_fields) ? 'news_read' : (in_array('views', $available_fields) ? 'views' : null);
            if ($views_field) {
                $this->db->prepare("UPDATE `{$this->post_table}` SET $views_field = $views_field + 1 WHERE id = ?")->execute([$news_id]);
            }
        } catch (PDOException $e) {
            $this->log('Ошибка просмотров: ' . $e->getMessage());
        }
    }
    
    // ========================================================================
    // КАТЕГОРИИ
    // ========================================================================
    
    private function getCategories() {
        if (!$this->db_connected || !$this->category_table) {
            $categories = [
                ['id' => 1, 'name' => 'Основная', 'alt_name' => 'main'],
                ['id' => 2, 'name' => 'Новости', 'alt_name' => 'news'],
                ['id' => 3, 'name' => 'Статьи', 'alt_name' => 'articles'],
            ];
            return $this->sendSuccess(['categories' => $categories], 'Тестовые категории');
        }
        
        try {
            $available_fields = $this->getTableFields($this->category_table);
            
            $select_fields = [];
            if (in_array('id', $available_fields)) $select_fields[] = 'id';
            if (in_array('name', $available_fields)) $select_fields[] = 'name';
            elseif (in_array('category', $available_fields)) $select_fields[] = 'category as name';
            if (in_array('alt_name', $available_fields)) $select_fields[] = 'alt_name';
            elseif (in_array('alt', $available_fields)) $select_fields[] = 'alt as alt_name';
            if (in_array('descr', $available_fields)) $select_fields[] = 'descr as description';
            if (in_array('sort', $available_fields)) $select_fields[] = 'sort';
            
            if (empty($select_fields)) return $this->sendError('Некорректная структура таблицы категорий', 500);
            
            $order_by = in_array('sort', $available_fields) ? 'sort' : (in_array('position', $available_fields) ? 'position' : 'id');
            
            $sql = "SELECT " . implode(', ', $select_fields) . " FROM `{$this->category_table}` ORDER BY $order_by";
            $categories = $this->db->query($sql)->fetchAll();
            
            $normalized = [];
            foreach ($categories as $cat) {
                $normalized[] = [
                    'id' => $cat['id'] ?? 0,
                    'name' => $cat['name'] ?? 'Без названия',
                    'alt_name' => $cat['alt_name'] ?? 'no-name',
                    'description' => $cat['description'] ?? '',
                    'sort' => $cat['sort'] ?? 0
                ];
            }
            
            return $this->sendSuccess(['categories' => $normalized]);
            
        } catch (PDOException $e) {
            $this->log('Ошибка категорий: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    private function addCategory($data) {
        if (empty($data['name'])) return $this->sendError("Поле 'name' обязательно", 400);
        
        if (!$this->db_connected || !$this->category_table) {
            return $this->sendSuccess(['category_id' => rand(100, 999), 'name' => $data['name'], 'test_mode' => true], 'Тестовое добавление категории');
        }
        
        try {
            $name = $data['name'];
            $alt_name = $data['alt_name'] ?? $this->createAltName($name);
            $description = $data['description'] ?? '';
            $sort = intval($data['sort'] ?? 0);
            
            $available_fields = $this->getTableFields($this->category_table);
            
            $fields = []; $values = []; $bindings = [];
            
            if (in_array('name', $available_fields)) { $fields[] = 'name'; $values[] = ':name'; $bindings[':name'] = $name; }
            if (in_array('alt_name', $available_fields)) { $fields[] = 'alt_name'; $values[] = ':alt_name'; $bindings[':alt_name'] = $alt_name; }
            if (in_array('descr', $available_fields)) { $fields[] = 'descr'; $values[] = ':description'; $bindings[':description'] = $description; }
            if (in_array('sort', $available_fields)) { $fields[] = 'sort'; $values[] = ':sort'; $bindings[':sort'] = $sort; }
            
            if (empty($fields)) return $this->sendError('Нет подходящих полей', 500);
            
            $sql = "INSERT INTO `{$this->category_table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $this->db->prepare($sql)->execute($bindings);
            
            return $this->sendSuccess(['category_id' => $this->db->lastInsertId(), 'name' => $name, 'alt_name' => $alt_name], 'Категория добавлена');
            
        } catch (PDOException $e) {
            $this->log('Ошибка добавления категории: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    // ========================================================================
    // ДОБАВЛЕНИЕ НОВОСТИ (с rebuild для DLE 18.1)
    // ========================================================================
    
    private function addNews($data) {
        $required = ['title', 'short_story', 'full_story'];
        foreach ($required as $field) {
            if (empty($data[$field])) return $this->sendError("Поле '$field' обязательно", 400);
        }
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess(['news_id' => rand(1000, 9999), 'title' => $data['title'], 'test_mode' => true], 'Тестовое добавление');
        }
        
        try {
            $available_fields = $this->getTableFields($this->post_table);
            
            $title = $data['title'];
            $short_story = $data['short_story'];
            $full_story = $data['full_story'];
            $category = $data['category'] ?? '1';
            $author = $data['author'] ?? 'admin';
            $date = gmdate('Y-m-d H:i:s'); // UTC — совпадает с часовым поясом DLE
            $alt_name = $data['alt_name'] ?? $this->createAltName($title);
            
            $fields = []; $values = []; $bindings = [];
            
            // Основные поля
            $core_fields = [
                'title' => $title, 'short_story' => $short_story, 'full_story' => $full_story,
                'date' => $date, 'category' => $category, 'alt_name' => $alt_name
            ];
            
            foreach ($core_fields as $f => $v) {
                if (in_array($f, $available_fields)) {
                    $fields[] = $f; $values[] = ":$f"; $bindings[":$f"] = $v;
                }
            }
            
            // Поле автора (autor в DLE, не author)
            if (in_array('autor', $available_fields)) {
                $fields[] = 'autor'; $values[] = ':author'; $bindings[':author'] = $author;
            } elseif (in_array('author', $available_fields)) {
                $fields[] = 'author'; $values[] = ':author'; $bindings[':author'] = $author;
            }
            
            // Опциональные поля
            $optional_fields = [
                'approve' => intval($data['approve'] ?? 1),
                'allow_comm' => intval($data['allow_comments'] ?? 1),
                'allow_main' => intval($data['allow_main'] ?? 1),
                'allow_rate' => intval($data['allow_rating'] ?? 1),
                'fixed' => intval($data['fixed'] ?? 0),
                'keywords' => $data['keywords'] ?? '',
                'descr' => $data['description'] ?? '',
                'metatitle' => $data['metatitle'] ?? '',
                'tags' => $data['tags'] ?? '',
                'comm_num' => 0, 'rating' => 0, 'vote_num' => 0, 'news_read' => 0,
                'user_id' => intval($data['user_id'] ?? 1),
                // Поля DLE без DEFAULT значений
                'editdate' => '', 'editor' => '', 'reason' => '',
                'view_edit' => 0, 'allow_br' => 1, 'break_archive' => 0,
                'symbol' => '', 'flag' => 0,
                'images' => '', 'files' => '',
                'groession' => '', 'access' => '',
                'editreason' => '',
            ];
            
            foreach ($optional_fields as $f => $v) {
                if (in_array($f, $available_fields)) {
                    $fields[] = $f; $values[] = ":$f"; $bindings[":$f"] = $v;
                }
            }
            
            // xfields (поле обязательное в DLE, не имеет DEFAULT)
            if (in_array('xfields', $available_fields)) {
                $xf_value = '';
                if (!empty($data['xfields']) && is_array($data['xfields'])) {
                    $xf_array = [];
                    foreach ($data['xfields'] as $f => $v) { $xf_array[] = "$f|$v"; }
                    $xf_value = implode('||', $xf_array);
                }
                $fields[] = 'xfields'; $values[] = ':xfields'; $bindings[':xfields'] = $xf_value;
            }
            
            $sql = "INSERT INTO `{$this->post_table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $this->db->prepare($sql)->execute($bindings);
            $news_id = $this->db->lastInsertId();
            
            if ($news_id) {
                // REBUILD: критически важно для отображения на главной
                $rebuild_ok = $this->rebuildNewsEntry($news_id, $title, $short_story, $full_story);
                
                // Теги
                if (!empty($data['tags'])) $this->updateNewsTags($news_id, $data['tags']);
                
                // Индекс xfsearch
                if (!empty($data['xfields'])) $this->updateXfSearch($news_id, $data['xfields']);
                
                // Скачивание постера (если есть URL в xfields)
                $poster_result = null;
                if (!empty($data['xfields']['poster']) && filter_var($data['xfields']['poster'], FILTER_VALIDATE_URL)) {
                    $poster_result = $this->downloadPoster($data['xfields']['poster'], $news_id);
                    
                    if ($poster_result['success']) {
                        // Обновляем xfield poster на локальный путь
                        $data['xfields']['poster'] = $poster_result['local_url'];
                        
                        // Пересобираем xfields строку и обновляем в БД
                        $xf_array = [];
                        foreach ($data['xfields'] as $f => $v) { $xf_array[] = "$f|$v"; }
                        $xf_value = implode('||', $xf_array);
                        
                        $this->db->prepare("UPDATE `{$this->post_table}` SET xfields = ? WHERE id = ?")
                            ->execute([$xf_value, $news_id]);
                        
                        // Обновляем поле images в dle_post (DLE использует его для галереи)
                        $post_fields = $this->getTableFields($this->post_table);
                        if (in_array('images', $post_fields)) {
                            $this->db->prepare("UPDATE `{$this->post_table}` SET images = ? WHERE id = ?")
                                ->execute([$poster_result['local_url'], $news_id]);
                        }
                        
                        $this->log("Постер привязан к новости ID $news_id: " . $poster_result['local_url']);
                    }
                }
                
                // Очистка кеша
                $this->clearDLECache();
                
                $response_data = [
                    'news_id' => $news_id, 'title' => $title, 'alt_name' => $alt_name,
                    'url' => $this->getNewsUrl($news_id, $alt_name),
                    'table_used' => $this->post_table, 'fields_used' => count($fields),
                    'rebuild' => $rebuild_ok ? 'ok' : 'failed'
                ];
                
                if ($poster_result) {
                    $response_data['poster'] = $poster_result['success'] 
                        ? ['saved' => true, 'url' => $poster_result['local_url'], 'size' => $poster_result['size']]
                        : ['saved' => false, 'error' => $poster_result['error']];
                }
                
                return $this->sendSuccess($response_data, 'Новость успешно добавлена');
            }
            
            return $this->sendError('Не удалось получить ID записи', 500);
            
        } catch (PDOException $e) {
            $this->log('Ошибка добавления новости: ' . $e->getMessage());
            return $this->sendError('Ошибка БД: ' . $e->getMessage(), 500);
        }
    }
    
    // ========================================================================
    // ОБНОВЛЕНИЕ НОВОСТИ (с rebuild для DLE 18.1)
    // ========================================================================
    
    private function updateNews($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        if (!$news_id) return $this->sendError('ID новости не указан', 400);
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess(['news_id' => $news_id, 'updated_fields' => array_keys($data), 'test_mode' => true], 'Тестовое обновление');
        }
        
        try {
            // Получаем текущие данные для rebuild
            $check_stmt = $this->db->prepare("SELECT id, title, short_story, full_story FROM `{$this->post_table}` WHERE id = ?");
            $check_stmt->execute([$news_id]);
            $existing = $check_stmt->fetch();
            if (!$existing) return $this->sendError('Новость не найдена', 404);
            
            $available_fields = $this->getTableFields($this->post_table);
            
            $update_fields = []; $bindings = [];
            
            $field_mapping = [
                'title' => 'title', 'short_story' => 'short_story', 'full_story' => 'full_story',
                'category' => 'category', 'author' => ['autor', 'author'],
                'keywords' => 'keywords', 'description' => 'descr', 'metatitle' => 'metatitle',
                'approve' => 'approve', 'allow_comments' => 'allow_comm',
                'allow_main' => 'allow_main', 'allow_rating' => 'allow_rate',
                'fixed' => 'fixed', 'tags' => 'tags'
            ];
            
            foreach ($field_mapping as $input_key => $db_field) {
                if (!isset($data[$input_key])) continue;
                if (is_array($db_field)) {
                    foreach ($db_field as $variant) {
                        if (in_array($variant, $available_fields)) {
                            $update_fields[] = "$variant = :$input_key"; $bindings[":$input_key"] = $data[$input_key]; break;
                        }
                    }
                } else {
                    if (in_array($db_field, $available_fields)) {
                        $update_fields[] = "$db_field = :$input_key"; $bindings[":$input_key"] = $data[$input_key];
                    }
                }
            }
            
            if (isset($data['title']) && in_array('alt_name', $available_fields)) {
                $alt_name = $data['alt_name'] ?? $this->createAltName($data['title']);
                $update_fields[] = "alt_name = :alt_name"; $bindings[":alt_name"] = $alt_name;
            }
            
            if (isset($data['xfields']) && in_array('xfields', $available_fields)) {
                $xf_array = [];
                foreach ($data['xfields'] as $f => $v) { $xf_array[] = "$f|$v"; }
                $update_fields[] = "xfields = :xfields"; $bindings[":xfields"] = implode('||', $xf_array);
            }
            
            if (empty($update_fields)) return $this->sendError('Нет полей для обновления', 400);
            
            $bindings[':news_id'] = $news_id;
            $sql = "UPDATE `{$this->post_table}` SET " . implode(', ', $update_fields) . " WHERE id = :news_id";
            $upd_stmt = $this->db->prepare($sql);
            $upd_stmt->execute($bindings);
            $affected_rows = $upd_stmt->rowCount();
            
            // Rebuild
            $title = $data['title'] ?? $existing['title'];
            $short_story = $data['short_story'] ?? $existing['short_story'];
            $full_story = $data['full_story'] ?? $existing['full_story'];
            $rebuild_ok = $this->rebuildNewsEntry($news_id, $title, $short_story, $full_story);
            
            if (!empty($data['tags'])) $this->updateNewsTags($news_id, $data['tags']);
            if (!empty($data['xfields'])) $this->updateXfSearch($news_id, $data['xfields']);
            
            // Скачивание постера при обновлении (если есть URL)
            $poster_result = null;
            if (!empty($data['xfields']['poster']) && filter_var($data['xfields']['poster'], FILTER_VALIDATE_URL)) {
                $poster_result = $this->downloadPoster($data['xfields']['poster'], $news_id);
                
                if ($poster_result['success']) {
                    $data['xfields']['poster'] = $poster_result['local_url'];
                    
                    $xf_array = [];
                    foreach ($data['xfields'] as $f => $v) { $xf_array[] = "$f|$v"; }
                    $xf_value = implode('||', $xf_array);
                    
                    $this->db->prepare("UPDATE `{$this->post_table}` SET xfields = ? WHERE id = ?")
                        ->execute([$xf_value, $news_id]);
                    
                    $post_fields = $this->getTableFields($this->post_table);
                    if (in_array('images', $post_fields)) {
                        $this->db->prepare("UPDATE `{$this->post_table}` SET images = ? WHERE id = ?")
                            ->execute([$poster_result['local_url'], $news_id]);
                    }
                    
                    $this->log("Постер обновлён для ID $news_id: " . $poster_result['local_url']);
                }
            }
            
            $this->clearDLECache();
            
            $response_data = [
                'news_id' => $news_id, 'updated_fields' => count($update_fields),
                'rebuild' => $rebuild_ok ? 'ok' : 'failed'
            ];
            
            if ($poster_result) {
                $response_data['poster'] = $poster_result['success'] 
                    ? ['saved' => true, 'url' => $poster_result['local_url'], 'size' => $poster_result['size']]
                    : ['saved' => false, 'error' => $poster_result['error']];
            }
            
            return $this->sendSuccess($response_data, 'Новость обновлена');
            
        } catch (PDOException $e) {
            $this->log('Ошибка обновления: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    // ========================================================================
    // УДАЛЕНИЕ НОВОСТИ (с очисткой всех связанных таблиц)
    // ========================================================================
    
    private function deleteNews($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        if (!$news_id) return $this->sendError('ID новости не указан', 400);
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess(['news_id' => $news_id, 'test_mode' => true], 'Тестовое удаление');
        }
        
        try {
            $check_stmt = $this->db->prepare("SELECT id, title FROM `{$this->post_table}` WHERE id = ?");
            $check_stmt->execute([$news_id]);
            $news = $check_stmt->fetch();
            if (!$news) return $this->sendError('Новость не найдена', 404);
            
            // Очищаем ВСЕ связанные таблицы
            $this->cleanupRelatedTables($news_id);
            
            // Удаляем новость
            $this->db->prepare("DELETE FROM `{$this->post_table}` WHERE id = ?")->execute([$news_id]);
            
            $this->clearDLECache();
            
            return $this->sendSuccess([
                'news_id' => $news_id, 'title' => $news['title'], 'deleted' => true, 'cleanup' => true
            ], 'Новость удалена');
            
        } catch (PDOException $e) {
            $this->log('Ошибка удаления: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    /**
     * Очистка связанных таблиц при удалении
     */
    private function cleanupRelatedTables($news_id) {
        $tables_to_clean = [
            'post_extras' => 'news_id',
            'post_extras_cats' => 'news_id',
            'tags' => 'news_id',
            'xfsearch' => 'news_id',
            'comments' => 'post_id',
        ];
        
        foreach ($tables_to_clean as $suffix => $id_field) {
            $table = DB_PREFIX . $suffix;
            if ($this->tableExists($table)) {
                try {
                    $this->db->prepare("DELETE FROM `$table` WHERE $id_field = ?")->execute([$news_id]);
                } catch (PDOException $e) {
                    $this->log("Ошибка очистки $table: " . $e->getMessage());
                }
            }
        }
        
        // Удаляем из related_ids других новостей
        $this->removeFromRelatedIds($news_id);
        
        $this->log("Cleanup OK для новости ID: $news_id");
    }
    
    /**
     * Удаление ID из related_ids других новостей
     */
    private function removeFromRelatedIds($news_id) {
        $extras_table = DB_PREFIX . 'post_extras';
        if (!$this->tableExists($extras_table)) return;
        
        try {
            $ext_fields = $this->getTableFields($extras_table);
            if (!in_array('related_ids', $ext_fields)) return;
            
            $stmt = $this->db->prepare("SELECT news_id, related_ids FROM `$extras_table` WHERE related_ids LIKE ?");
            $stmt->execute(["%$news_id%"]);
            
            foreach ($stmt->fetchAll() as $row) {
                $ids = array_filter(explode(',', $row['related_ids']), function($id) use ($news_id) {
                    return intval(trim($id)) !== intval($news_id) && intval(trim($id)) > 0;
                });
                $this->db->prepare("UPDATE `$extras_table` SET related_ids = ? WHERE news_id = ?")
                    ->execute([implode(',', $ids), $row['news_id']]);
            }
        } catch (PDOException $e) {
            $this->log('Ошибка related_ids cleanup: ' . $e->getMessage());
        }
    }
    
    // ========================================================================
    // REBUILD - ЭМУЛЯЦИЯ DLE rebuild
    // Совместимость: DLE 13.x - 18.1+
    // Обновляет: post_extras, post_extras_cats, full_search, meta description
    // Без этой функции новости НЕ появляются на главной!
    // ========================================================================
    
    private function rebuildNewsEntry($news_id, $title, $short_story, $full_story) {
        if (!$this->db_connected || !$this->post_table) return false;
        
        try {
            $stmt = $this->db->prepare("SELECT category, approve FROM {$this->post_table} WHERE id = ?");
            $stmt->execute([$news_id]);
            $news = $stmt->fetch();
            if (!$news) { $this->log("Rebuild: новость ID $news_id не найдена"); return false; }
            
            // === 1. dle_post_extras ===
            $extras_table = DB_PREFIX . 'post_extras';
            if ($this->tableExists($extras_table)) {
                $ext_check = $this->db->prepare("SELECT news_id FROM `$extras_table` WHERE news_id = ?");
                $ext_check->execute([$news_id]);
                
                if (!$ext_check->fetch()) {
                    $ext_fields = $this->getTableFields($extras_table);
                    
                    $ins_cols = ['news_id'];
                    $ins_vals = [$news_id];
                    $ins_placeholders = ['?'];
                    
                    if (in_array('allow_rate', $ext_fields)) {
                        $ins_cols[] = 'allow_rate'; $ins_vals[] = 1; $ins_placeholders[] = '?';
                    }
                    if (in_array('related_ids', $ext_fields)) {
                        $ins_cols[] = 'related_ids'; $ins_vals[] = ''; $ins_placeholders[] = '?';
                    }
                    if (in_array('news_password', $ext_fields)) {
                        $ins_cols[] = 'news_password'; $ins_vals[] = ''; $ins_placeholders[] = '?';
                    }
                    
                    $sql = "INSERT INTO `$extras_table` (" . implode(', ', $ins_cols) . ") VALUES (" . implode(', ', $ins_placeholders) . ")";
                    $this->db->prepare($sql)->execute($ins_vals);
                    $this->log("post_extras создан для ID: $news_id");
                }
            }
            
            // === 2. dle_post_extras_cats (DLE 13+) ===
            $extras_cats_table = DB_PREFIX . 'post_extras_cats';
            if ($this->tableExists($extras_cats_table)) {
                $this->db->prepare("DELETE FROM `$extras_cats_table` WHERE news_id = ?")->execute([$news_id]);
                
                if ($news['category'] && $news['approve']) {
                    $cat_ids = array_filter(array_map('intval', explode(',', $news['category'])), function($id) { return $id > 0; });
                    
                    if (!empty($cat_ids)) {
                        $cat_values = array_map(function($cat_id) use ($news_id) { return "($news_id, $cat_id)"; }, $cat_ids);
                        $this->db->query("INSERT INTO `$extras_cats_table` (news_id, cat_id) VALUES " . implode(', ', $cat_values));
                        $this->log("post_extras_cats: $news_id -> " . count($cat_ids) . " категорий");
                    }
                }
            }
            
            // === 3. Поисковый индекс и мета в dle_post ===
            $search_text = strip_tags($title . ' ' . $short_story . ' ' . $full_story);
            $search_text = preg_replace('/\s+/', ' ', $search_text);
            $search_text = mb_substr($search_text, 0, 5000, 'UTF-8');
            
            $post_fields = $this->getTableFields($this->post_table);
            $update_parts = []; $bindings = [];
            
            if (in_array('full_search', $post_fields)) {
                $update_parts[] = "full_search = ?"; $bindings[] = $search_text;
            }
            if (in_array('descr', $post_fields)) {
                $meta = mb_substr(strip_tags($short_story), 0, 200, 'UTF-8');
                $update_parts[] = "descr = IF(descr = '' OR descr IS NULL, ?, descr)"; $bindings[] = $meta;
            }
            if (in_array('metatitle', $post_fields)) {
                $update_parts[] = "metatitle = IF(metatitle = '' OR metatitle IS NULL, ?, metatitle)";
                $bindings[] = mb_substr($title, 0, 200, 'UTF-8');
            }
            
            if (!empty($update_parts)) {
                $bindings[] = $news_id;
                $this->db->prepare("UPDATE `{$this->post_table}` SET " . implode(', ', $update_parts) . " WHERE id = ?")
                    ->execute($bindings);
            }
            
            $this->log("Rebuild OK: ID $news_id");
            return true;
            
        } catch (PDOException $e) {
            $this->log('Rebuild FAIL: ' . $e->getMessage());
            return false;
        }
    }
    
    // ========================================================================
    // ТЕГИ И XFSEARCH
    // ========================================================================
    
    /**
     * Обновление тегов новости (DLE 13+)
     */
    private function updateNewsTags($news_id, $tags_string) {
        $tags_table = DB_PREFIX . 'tags';
        if (!$this->tableExists($tags_table)) return;
        
        try {
            $this->db->prepare("DELETE FROM `$tags_table` WHERE news_id = ?")->execute([$news_id]);
            
            $tags = array_filter(array_map('trim', explode(',', $tags_string)));
            if (empty($tags)) return;
            
            $tag_fields = $this->getTableFields($tags_table);
            
            foreach ($tags as $tag) {
                $ins = ['news_id' => $news_id];
                if (in_array('tag', $tag_fields)) $ins['tag'] = mb_strtolower($tag, 'UTF-8');
                
                $cols = implode(', ', array_keys($ins));
                $placeholders = implode(', ', array_fill(0, count($ins), '?'));
                $this->db->prepare("INSERT INTO `$tags_table` ($cols) VALUES ($placeholders)")->execute(array_values($ins));
            }
            
            $this->log("Теги обновлены для ID $news_id: " . count($tags));
        } catch (PDOException $e) {
            $this->log('Ошибка тегов: ' . $e->getMessage());
        }
    }
    
    /**
     * Обновление индекса xfsearch (DLE 15+)
     */
    private function updateXfSearch($news_id, $xfields) {
        $xf_table = DB_PREFIX . 'xfsearch';
        if (!$this->tableExists($xf_table) || !is_array($xfields)) return;
        
        try {
            $this->db->prepare("DELETE FROM `$xf_table` WHERE news_id = ?")->execute([$news_id]);
            
            $xf_fields = $this->getTableFields($xf_table);
            
            foreach ($xfields as $name => $value) {
                if (empty($value)) continue;
                // Пропускаем поля с длинным контентом — они не нужны в поисковом индексе
                if (in_array($name, ['story', 'poster', 'youtube'])) continue;
                
                $ins = ['news_id' => $news_id];
                if (in_array('tagname', $xf_fields)) $ins['tagname'] = $name;
                if (in_array('tagvalue', $xf_fields)) {
                    $val = is_array($value) ? implode(', ', $value) : (string)$value;
                    $ins['tagvalue'] = mb_substr($val, 0, 100, 'UTF-8');
                }
                
                if (count($ins) > 1) {
                    $cols = implode(', ', array_keys($ins));
                    $placeholders = implode(', ', array_fill(0, count($ins), '?'));
                    $this->db->prepare("INSERT INTO `$xf_table` ($cols) VALUES ($placeholders)")->execute(array_values($ins));
                }
            }
            
            $this->log("xfsearch обновлён для ID $news_id");
        } catch (PDOException $e) {
            $this->log('Ошибка xfsearch: ' . $e->getMessage());
        }
    }
    
    // ========================================================================
    // ОЧИСТКА КЕША DLE
    // ========================================================================
    
    // ========================================================================
    // ЗАГРУЗКА ФАЙЛОВ (ZIP, RAR, PDF и др.)
    // ========================================================================
    
    /**
     * Загрузка файла через multipart/form-data
     * 
     * curl -X POST https://site.com/api.php \
     *   -F "action=upload_file" \
     *   -F "api_key=YOUR_KEY" \
     *   -F "file=@archive.zip" \
     *   -F "news_id=32" \
     *   -F "description=Доп. материалы"
     */
    private function uploadFile($data) {
        // Проверяем наличие файла
        if (empty($_FILES['file'])) {
            return $this->sendError('Файл не загружен. Используйте multipart/form-data с полем "file"', 400);
        }
        
        $file = $_FILES['file'];
        
        // Проверяем ошибки загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'Файл превышает upload_max_filesize в php.ini',
                UPLOAD_ERR_FORM_SIZE  => 'Файл превышает MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
                UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
                UPLOAD_ERR_NO_TMP_DIR => 'Нет временной папки',
                UPLOAD_ERR_CANT_WRITE => 'Ошибка записи на диск',
                UPLOAD_ERR_EXTENSION  => 'Загрузка остановлена расширением PHP',
            ];
            $err_msg = $upload_errors[$file['error']] ?? "Ошибка #{$file['error']}";
            return $this->sendError("Ошибка загрузки: $err_msg", 400);
        }
        
        // Проверяем размер
        if ($file['size'] > FILES_MAX_SIZE) {
            $max_mb = round(FILES_MAX_SIZE / 1024 / 1024, 1);
            return $this->sendError("Файл слишком большой. Максимум: {$max_mb} MB", 400);
        }
        
        if ($file['size'] === 0) {
            return $this->sendError('Файл пустой', 400);
        }
        
        // Получаем и проверяем расширение
        $original_name = basename($file['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_ext = array_map('trim', explode(',', strtolower(FILES_ALLOWED_EXT)));
        
        if (empty($ext) || !in_array($ext, $allowed_ext)) {
            return $this->sendError("Расширение .$ext не разрешено. Допустимые: " . FILES_ALLOWED_EXT, 400);
        }
        
        // Защита от опасных файлов
        $dangerous_ext = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'phps', 'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'dll', 'js', 'vbs', 'wsf', 'htaccess'];
        if (in_array($ext, $dangerous_ext)) {
            $this->log("Загрузка: заблокирован опасный файл .$ext — {$original_name}");
            return $this->sendError("Файлы с расширением .$ext запрещены", 403);
        }
        
        // Проверяем содержимое на PHP-код (двойное расширение и т.д.)
        $file_content_start = file_get_contents($file['tmp_name'], false, null, 0, 4096);
        if ($file_content_start !== false) {
            $dangerous_patterns = ['<?php', '<?=', '<script', '#!/', 'eval(', 'base64_decode('];
            foreach ($dangerous_patterns as $pattern) {
                if (stripos($file_content_start, $pattern) !== false) {
                    $this->log("Загрузка: обнаружен опасный контент в файле {$original_name}");
                    return $this->sendError('Файл содержит потенциально опасный контент', 403);
                }
            }
        }
        
        // Создаём подпапку по дате
        $subdir = date('Y-m');
        $upload_dir = rtrim(FILES_UPLOAD_DIR, '/') . '/' . $subdir . '/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return $this->sendError('Не удалось создать папку для загрузки', 500);
            }
        }
        
        // Генерируем безопасное имя файла
        $safe_name = preg_replace('/[^a-z0-9_\-]/i', '_', pathinfo($original_name, PATHINFO_FILENAME));
        $safe_name = substr($safe_name, 0, 50); // Ограничиваем длину имени
        $unique_id = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $filename = $safe_name . '_' . $unique_id . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        // Перемещаем файл
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return $this->sendError('Не удалось сохранить файл', 500);
        }
        
        // Безопасные права (не исполняемый)
        chmod($filepath, 0644);
        
        $file_url = rtrim(FILES_UPLOAD_URL, '/') . '/' . $subdir . '/' . $filename;
        $file_size = filesize($filepath);
        
        $this->log("Файл загружен: $file_url ({$file_size} байт, оригинал: {$original_name})");
        
        // Привязка к новости (если указан news_id)
        $news_id = intval($data['news_id'] ?? 0);
        $linked = false;
        
        if ($news_id > 0 && $this->db_connected && $this->post_table) {
            try {
                $post_fields = $this->getTableFields($this->post_table);
                
                if (in_array('files', $post_fields)) {
                    // Получаем текущие файлы
                    $stmt = $this->db->prepare("SELECT files FROM `{$this->post_table}` WHERE id = ?");
                    $stmt->execute([$news_id]);
                    $row = $stmt->fetch();
                    
                    if ($row) {
                        // DLE хранит файлы как: filename.ext|описание\nfilename2.ext|описание2
                        $description = $data['description'] ?? $original_name;
                        $file_entry = $filename . '|' . $description;
                        
                        $current_files = trim($row['files'] ?? '');
                        $new_files = $current_files ? $current_files . "\n" . $file_entry : $file_entry;
                        
                        $this->db->prepare("UPDATE `{$this->post_table}` SET files = ? WHERE id = ?")
                            ->execute([$new_files, $news_id]);
                        
                        $linked = true;
                        $this->log("Файл привязан к новости ID $news_id");
                    } else {
                        $this->log("Новость ID $news_id не найдена для привязки файла");
                    }
                }
            } catch (PDOException $e) {
                $this->log("Ошибка привязки файла к новости: " . $e->getMessage());
            }
        }
        
        return $this->sendSuccess([
            'file_url' => $file_url,
            'filename' => $filename,
            'original_name' => $original_name,
            'size' => $file_size,
            'size_human' => $this->formatFileSize($file_size),
            'extension' => $ext,
            'news_id' => $news_id ?: null,
            'linked_to_news' => $linked,
        ], 'Файл успешно загружен');
    }
    
    /**
     * Форматирование размера файла
     */
    private function formatFileSize($bytes) {
        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
    
    // ========================================================================
    // БЕЗОПАСНОЕ СКАЧИВАНИЕ ПОСТЕРА
    // ========================================================================
    
    /**
     * Скачивает постер по URL и сохраняет локально
     * @param string $url URL картинки
     * @param int $news_id ID новости (для уникального имени файла)
     * @return array ['success' => bool, 'local_path' => string, 'local_url' => string]
     */
    private function downloadPoster($url, $news_id) {
        if (empty($url) || empty(DLE_UPLOADS_DIR)) {
            return ['success' => false, 'error' => 'URL или путь загрузок не указан'];
        }
        
        // Проверяем URL
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if (!$url) {
            return ['success' => false, 'error' => 'Невалидный URL'];
        }
        
        // Разрешённые хосты для скачивания
        $allowed_hosts = [
            'avatars.mds.yandex.net',
            'kinopoiskapiunofficial.tech',
            'st.kp.yandex.net',
            'image.openmoviedb.com',
            'image.tmdb.org',
            'media.themoviedb.org',
        ];
        
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $host_allowed = false;
        foreach ($allowed_hosts as $ah) {
            if ($host === $ah || str_ends_with($host, '.' . $ah)) {
                $host_allowed = true;
                break;
            }
        }
        if (!$host_allowed) {
            $this->log("Постер: хост $host не в белом списке");
            return ['success' => false, 'error' => "Хост $host не разрешён"];
        }
        
        // Создаём подпапку по дате: YYYY-MM
        $subdir = date('Y-m');
        $upload_dir = rtrim(DLE_UPLOADS_DIR, '/') . '/' . $subdir . '/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return ['success' => false, 'error' => 'Не удалось создать папку ' . $upload_dir];
            }
        }
        
        // Скачиваем картинку
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; DLE-API/4.0)',
            // Ограничение размера: 5 MB максимум
            CURLOPT_MAXFILESIZE => 5 * 1024 * 1024,
        ]);
        
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error || $http_code !== 200 || empty($image_data)) {
            $this->log("Постер: ошибка скачивания ($http_code) $curl_error");
            return ['success' => false, 'error' => "HTTP $http_code: $curl_error"];
        }
        
        // Проверяем Content-Type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $ct_clean = strtolower(explode(';', $content_type)[0]);
        if (!in_array($ct_clean, $allowed_types)) {
            $this->log("Постер: недопустимый тип $ct_clean");
            return ['success' => false, 'error' => "Недопустимый тип: $ct_clean"];
        }
        
        // Проверяем что это реально картинка (magic bytes)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_mime = $finfo->buffer($image_data);
        if (!in_array($real_mime, $allowed_types)) {
            $this->log("Постер: содержимое не картинка ($real_mime)");
            return ['success' => false, 'error' => "Файл не является картинкой: $real_mime"];
        }
        
        // Обработка: ресайз и/или конвертация формата
        $ext_map = ['image/jpeg' => '.jpg', 'image/jpg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
        $target_format = strtolower(POSTER_FORMAT);
        $need_resize = (POSTER_MAX_WIDTH > 0 || POSTER_MAX_HEIGHT > 0);
        $need_convert = ($target_format !== 'original');
        
        if (($need_resize || $need_convert) && extension_loaded('gd')) {
            $processed = $this->processImage($image_data, $real_mime);
            if ($processed['success']) {
                $image_data = $processed['data'];
                $ext = $processed['ext'];
            } else {
                $this->log("Постер: GD ошибка — " . $processed['error'] . ", сохраняем оригинал");
                $ext = $ext_map[$real_mime] ?? '.jpg';
            }
        } else {
            $ext = $ext_map[$real_mime] ?? '.jpg';
            if (!extension_loaded('gd') && ($need_resize || $need_convert)) {
                $this->log("Постер: GD не установлен, сохраняем оригинал");
            }
        }
        
        // Генерируем безопасное имя: news_{id}_{hash}.ext
        $filename = 'news_' . intval($news_id) . '_' . substr(md5($url . time()), 0, 8) . $ext;
        $filepath = $upload_dir . $filename;
        
        // Сохраняем
        if (file_put_contents($filepath, $image_data) === false) {
            return ['success' => false, 'error' => 'Не удалось сохранить файл'];
        }
        
        // Устанавливаем безопасные права
        chmod($filepath, 0644);
        
        $local_url = rtrim(DLE_UPLOADS_URL, '/') . '/' . $subdir . '/' . $filename;
        
        $this->log("Постер сохранён: $local_url (" . strlen($image_data) . " байт)");
        
        return [
            'success' => true,
            'local_path' => $filepath,
            'local_url' => $local_url,
            'filename' => $filename,
            'size' => strlen($image_data)
        ];
    }
    
    /**
     * Обработка изображения: ресайз и конвертация формата
     */
    private function processImage($image_data, $source_mime) {
        // Создаём GD ресурс из бинарных данных
        $src = @imagecreatefromstring($image_data);
        if (!$src) {
            return ['success' => false, 'error' => 'Не удалось открыть изображение'];
        }
        
        $orig_w = imagesx($src);
        $orig_h = imagesy($src);
        $new_w = $orig_w;
        $new_h = $orig_h;
        
        // Ресайз с сохранением пропорций
        $max_w = POSTER_MAX_WIDTH > 0 ? POSTER_MAX_WIDTH : $orig_w;
        $max_h = POSTER_MAX_HEIGHT > 0 ? POSTER_MAX_HEIGHT : $orig_h;
        
        if ($orig_w > $max_w || $orig_h > $max_h) {
            $ratio_w = $max_w / $orig_w;
            $ratio_h = $max_h / $orig_h;
            $ratio = min($ratio_w, $ratio_h);
            $new_w = (int)round($orig_w * $ratio);
            $new_h = (int)round($orig_h * $ratio);
        }
        
        // Создаём новое изображение если нужен ресайз
        if ($new_w !== $orig_w || $new_h !== $orig_h) {
            $dst = imagecreatetruecolor($new_w, $new_h);
            
            // Сохраняем прозрачность для PNG и WebP
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
            imagedestroy($src);
            $src = $dst;
            
            $this->log("Постер: ресайз {$orig_w}x{$orig_h} → {$new_w}x{$new_h}");
        }
        
        // Конвертация в целевой формат
        $target = strtolower(POSTER_FORMAT);
        $quality = intval(POSTER_QUALITY);
        
        ob_start();
        switch ($target) {
            case 'webp':
                if (!function_exists('imagewebp')) {
                    ob_end_clean();
                    imagedestroy($src);
                    return ['success' => false, 'error' => 'WebP не поддерживается в GD'];
                }
                imagewebp($src, null, $quality);
                $ext = '.webp';
                break;
            case 'png':
                // PNG quality: 0-9 (0 = без сжатия, 9 = максимальное)
                $png_quality = (int)round((100 - $quality) / 11.1);
                imagepng($src, null, min(9, max(0, $png_quality)));
                $ext = '.png';
                break;
            case 'jpg':
            case 'jpeg':
            default:
                imagejpeg($src, null, $quality);
                $ext = '.jpg';
                break;
        }
        $output = ob_get_clean();
        imagedestroy($src);
        
        if (empty($output)) {
            return ['success' => false, 'error' => 'GD вернул пустой результат'];
        }
        
        $this->log("Постер: конвертация → $target, качество $quality%, размер " . strlen($output) . " байт");
        
        return ['success' => true, 'data' => $output, 'ext' => $ext, 'width' => $new_w, 'height' => $new_h];
    }
    
    private function clearDLECache() {
        $cache_dirs = [];
        
        if (DLE_ROOT && is_dir(DLE_ROOT)) {
            $cache_dirs[] = DLE_ROOT . '/engine/cache/';
            $cache_dirs[] = DLE_ROOT . '/engine/cache/system/';
        }
        
        // Автопоиск кеша относительно api.php
        $dir = dirname(__FILE__);
        foreach ([$dir, dirname($dir), dirname(dirname($dir))] as $root) {
            if (is_dir($root . '/engine/cache/')) {
                $cache_dirs[] = $root . '/engine/cache/';
                $cache_dirs[] = $root . '/engine/cache/system/';
                break;
            }
        }
        
        $cleared = 0;
        foreach (array_unique($cache_dirs) as $dir) {
            if (!is_dir($dir)) continue;
            foreach (['*.tmp', '*.php'] as $pattern) {
                $files = glob($dir . $pattern);
                if ($files) foreach ($files as $file) { if (is_file($file) && @unlink($file)) $cleared++; }
            }
        }
        
        if ($cleared > 0) $this->log("Кеш DLE: очищено $cleared файлов");
    }
    
    // ========================================================================
    // СТАТУС И СТАТИСТИКА
    // ========================================================================
    
    private function getNewsStatus($data) {
        $news_id = intval($data['news_id'] ?? $data['id'] ?? 0);
        if (!$news_id) return $this->sendError('ID новости не указан', 400);
        
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess(['news_id' => $news_id, 'title' => 'Тест #' . $news_id, 'approved' => 1, 'test_mode' => true]);
        }
        
        try {
            $available_fields = $this->getTableFields($this->post_table);
            
            $select_fields = ['id', 'title'];
            if (in_array('approve', $available_fields)) $select_fields[] = 'approve';
            if (in_array('allow_main', $available_fields)) $select_fields[] = 'allow_main';
            if (in_array('date', $available_fields)) $select_fields[] = 'date';
            if (in_array('news_read', $available_fields)) $select_fields[] = 'news_read as views';
            if (in_array('comm_num', $available_fields)) $select_fields[] = 'comm_num as comments';
            
            $stmt = $this->db->prepare("SELECT " . implode(', ', $select_fields) . " FROM `{$this->post_table}` WHERE id = ?");
            $stmt->execute([$news_id]);
            $news = $stmt->fetch();
            if (!$news) return $this->sendError('Новость не найдена', 404);
            
            // Инфо из extras
            $extras_table = DB_PREFIX . 'post_extras';
            if ($this->tableExists($extras_table)) {
                $ext_stmt = $this->db->prepare("SELECT * FROM `$extras_table` WHERE news_id = ?");
                $ext_stmt->execute([$news_id]);
                $ext = $ext_stmt->fetch();
                $news['has_extras'] = (bool)$ext;
                if ($ext && isset($ext['related_ids'])) $news['has_related'] = !empty($ext['related_ids']);
            }
            
            // Кол-во категорий в индексе
            $cats_table = DB_PREFIX . 'post_extras_cats';
            if ($this->tableExists($cats_table)) {
                $cat_stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM `$cats_table` WHERE news_id = ?");
                $cat_stmt->execute([$news_id]);
                $news['categories_indexed'] = intval($cat_stmt->fetch()['cnt']);
            }
            
            return $this->sendSuccess($news);
            
        } catch (PDOException $e) {
            $this->log('Ошибка статуса: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    private function getStats() {
        if (!$this->db_connected || !$this->post_table) {
            return $this->sendSuccess(['total_news' => 150, 'approved_news' => 140, 'pending_news' => 10, 'total_categories' => 5, 'test_mode' => true], 'Тестовая статистика');
        }
        
        try {
            $stats = [];
            $available_fields = $this->getTableFields($this->post_table);
            
            $stats['total_news'] = $this->db->query("SELECT COUNT(*) as c FROM `{$this->post_table}`")->fetch()['c'];
            
            if (in_array('approve', $available_fields)) {
                $stats['approved_news'] = $this->db->query("SELECT COUNT(*) as c FROM `{$this->post_table}` WHERE approve = 1")->fetch()['c'];
                $stats['pending_news'] = $stats['total_news'] - $stats['approved_news'];
            }
            
            if ($this->category_table) {
                $stats['total_categories'] = $this->db->query("SELECT COUNT(*) as c FROM `{$this->category_table}`")->fetch()['c'];
            }
            
            if (in_array('news_read', $available_fields)) {
                $v = $this->db->query("SELECT SUM(news_read) as s, AVG(news_read) as a FROM `{$this->post_table}`")->fetch();
                $stats['total_views'] = intval($v['s']);
                $stats['average_views'] = round($v['a'], 2);
            }
            
            if (in_array('comm_num', $available_fields)) {
                $stats['total_comments'] = intval($this->db->query("SELECT SUM(comm_num) as c FROM `{$this->post_table}`")->fetch()['c']);
            }
            
            if (in_array('news_read', $available_fields)) {
                $stats['popular_news'] = $this->db->query("SELECT id, title, news_read as views FROM `{$this->post_table}` ORDER BY news_read DESC LIMIT 5")->fetchAll();
            }
            
            $stats['dle_version'] = $this->dle_version ?: 'unknown';
            
            return $this->sendSuccess($stats);
            
        } catch (PDOException $e) {
            $this->log('Ошибка статистики: ' . $e->getMessage());
            return $this->sendError('Ошибка базы данных', 500);
        }
    }
    
    // ========================================================================
    // УТИЛИТЫ
    // ========================================================================
    
    /**
     * Получение полей таблицы (с кешированием в рамках запроса)
     */
    private $table_fields_cache = [];
    
    private function getTableFields($table) {
        if (isset($this->table_fields_cache[$table])) {
            return $this->table_fields_cache[$table];
        }
        
        try {
            $stmt = $this->db->query("DESCRIBE `$table`");
            $fields = array_column($stmt->fetchAll(), 'Field');
            $this->table_fields_cache[$table] = $fields;
            return $fields;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    private function createAltName($title) {
        $alt_name = mb_strtolower($title, 'UTF-8');
        
        // Транслитерация (кириллица + украинские буквы)
        $from = ['а','б','в','г','ґ','д','е','ё','є','ж','з','и','і','ї','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'];
        $to   = ['a','b','v','g','g','d','e','yo','ye','zh','z','i','i','yi','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'];
        $alt_name = str_replace($from, $to, $alt_name);
        
        $alt_name = preg_replace('/[^a-z0-9\-_]/', '-', $alt_name);
        $alt_name = preg_replace('/-+/', '-', $alt_name);
        $alt_name = trim($alt_name, '-');
        
        if (strlen($alt_name) > 50) {
            $alt_name = substr($alt_name, 0, 50);
            $alt_name = rtrim($alt_name, '-');
        }
        
        $alt_name .= '-' . time();
        return $alt_name;
    }
    
    private function getNewsUrl($id, $alt_name) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host/$id-$alt_name.html";
    }
    
    private function sendSuccess($data = [], $message = null) {
        $response = ['success' => true, 'data' => $data, 'timestamp' => time(), 'api_version' => API_VERSION];
        if ($message) $response['message'] = $message;
        $this->log('OK: ' . ($message ?: 'success'));
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        $response = ['success' => false, 'error' => $message, 'code' => $code, 'timestamp' => time(), 'api_version' => API_VERSION];
        $this->log("ERR ($code): $message");
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    private function log($message) {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
        foreach (['api.log', sys_get_temp_dir() . '/dle_api.log'] as $f) {
            if (@file_put_contents($f, $log_entry, FILE_APPEND | LOCK_EX)) break;
        }
    }
    
    private function logRequest() {
        $this->log('REQ: ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' [' . ($_SERVER['REMOTE_ADDR'] ?? '') . ']');
    }
}

// Запуск
try {
    $api = new FullDLEAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Init error: ' . $e->getMessage(), 'code' => 500, 'timestamp' => time()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
