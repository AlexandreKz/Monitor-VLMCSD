<?php
// ============================================
// ФАЙЛ: vlmcinc/ajax.php
// ВЕРСИЯ: 3.5.3
// ДАТА: 2026-11-07
// @description: Все AJAX обработчики с проверкой прав
// ============================================

// Сессия может быть уже запущена (в vlmcconf.php) или намеренно закрыта им после проверки прав
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/paths.php';
if (session_status() === PHP_SESSION_NONE && !defined('VLMC_SESSION_CLOSED')) {
    vlmcSessionStart();
}
require_once __DIR__ . '/users.php';

// CSRF при прямом обращении к ajax.php (например, из vlmc.php). Запросы через vlmcconf.php
// уже проверены там (VLMC_CSRF_CHECKED). check_permission только читает и вызывается и для гостей.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !defined('VLMC_CSRF_CHECKED')
    && ($_POST['ajax'] ?? '') !== 'check_permission') {
    csrfVerifyOrDie();
}

// ============================================
// ЕДИНАЯ ПРОВЕРКА ПРАВ ДЛЯ AJAX-ДЕЙСТВИЙ
// ============================================
/**
 * Какое право нужно для каждого действия.
 *   'public' — доступно без входа (только чтение)
 *   0        — достаточно быть вошедшим пользователем панели
 *   int      — нужны все биты маски
 *   array    — достаточно любого из перечисленных прав
 * Действия, которых нет в таблице, отклоняются (новое действие нужно сначала внести сюда).
 */
function ajaxActionPermissions() {
    $toolsEdit = PERM_TOOLS_EDIT;
    $toolsAny = [PERM_TOOLS_VIEW, PERM_TOOLS_EDIT];
    return [
        'check_permission'      => 'public',

        // Статистика и оформление
        'get_activity'          => 0,
        'get_device_activity'   => 0,
        'preview_theme'         => 0,
        'geo_resolve_portion'   => 0,
        'file_info'             => PERM_INFO_VIEW,
        'file_info_batch'       => PERM_INFO_VIEW,

        // Инструменты: лог, кэши, экспорт
        'backup_log'            => $toolsEdit,
        'clear_log'             => $toolsEdit,
        'clear_geo_cache'       => $toolsEdit,
        'refresh_geo_cache'     => $toolsEdit,
        'check_geo_cache'       => $toolsAny,
        'clear_emoji_cache'     => $toolsEdit,
        'refresh_emoji_cache'   => $toolsEdit,
        'check_emoji_cache'     => $toolsAny,
        'export_project'        => $toolsEdit,   // внутри обработчика дополнительно нужен PERM_ADMIN_FULL

        // Обновление проекта
        'check_github_access'   => $toolsEdit,
        'check_updates'         => $toolsEdit,
        'update_auto_check'     => $toolsEdit,
        'get_update_files'      => $toolsEdit,
        'perform_update'        => $toolsEdit,
        'save_update_settings'  => $toolsEdit,

        // Пользователи
        'get_users'             => [PERM_USERS_VIEW, PERM_USERS_EDIT],
        'add_user'              => PERM_USERS_EDIT,
        'update_user'           => PERM_USERS_EDIT,
        'delete_user'           => PERM_USERS_EDIT,
        'change_user_password'  => PERM_USERS_EDIT,

        // Группы и устройства
        'rename_group'          => PERM_GROUPS_EDIT,
        'delete_group'          => PERM_GROUPS_EDIT,
        'save_group_changes'    => PERM_GROUPS_EDIT,
        'add_device'            => PERM_DEVICES_EDIT,
        'mass_move_devices'     => PERM_DEVICES_EDIT,

        // Белый список IP
        'get_whitelist_ips'     => PERM_IP_WHITELIST,
        'add_whitelist_ip'      => PERM_IP_WHITELIST,
        'remove_whitelist_ip'   => PERM_IP_WHITELIST,
    ];
}

/** Пропускает запрос дальше или отвечает 403 и завершает скрипт. */
function ajaxAuthorizeOrDie($action) {
    $map = ajaxActionPermissions();
    $need = (is_string($action) && array_key_exists($action, $map)) ? $map[$action] : null;
    if ($need === 'public') {
        return;
    }

    $deny = function () {
        $message = function_exists('__') ? __('access_denied') : 'Access denied';
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => false, 'has_permission' => false, 'error' => $message, 'message' => $message]);
        exit;
    };

    // Неизвестное действие или нет входа
    if ($need === null || ($_SESSION['vlmc_admin'] ?? false) !== true) {
        $deny();
    }
    if ($need === 0) {
        return;
    }

    $perms = (int)($_SESSION['vlmc_permissions'] ?? 0);
    if ($perms === 0 && ($_SESSION['vlmc_username'] ?? '') === 'root') {
        $perms = PERM_ADMIN_FULL;
    }
    foreach ((array)$need as $mask) {
        if (($perms & $mask) === $mask) {
            return;
        }
    }
    $deny();
}

// ============================================
// ПРОВЕРКА ПУТЕЙ К ФАЙЛАМ (Ubuntu и Windows)
// ============================================
/** Лежит ли $path внутри каталога $root (оба — уже после realpath). */
function pathIsInside($path, $root) {
    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows: разделители могут быть любыми, регистр букв не важен
        $path = strtolower(str_replace('\\', '/', $path));
        $root = strtolower(str_replace('\\', '/', $root));
    }
    $path = rtrim($path, '/');
    $root = rtrim($root, '/');
    return $path === $root || strpos($path, $root . '/') === 0;
}

/**
 * Превращает путь из запроса в реальный путь к файлу — или null, если файл нельзя
 * показывать. Разрешены только обычные файлы внутри каталога проекта и файл лога
 * (он может лежать вне проекта, например /var/log/vlmcsd.log).
 * Принимает путь относительно корня проекта (основной вариант) или абсолютный
 * (для совместимости со старыми открытыми страницами).
 */
function resolveProjectFilePath($input) {
    if (!is_string($input) || $input === '' || strlen($input) > 4096
        || strpos($input, "\0") !== false || strpos($input, '://') !== false) {
        return null;
    }
    $root = realpath(dirname(__DIR__, 2));
    if ($root === false) {
        return null;
    }

    $isAbsolute = $input[0] === '/' || $input[0] === '\\' || preg_match('/^[A-Za-z]:[\\\\\/]/', $input);
    $candidate = $isAbsolute
        ? $input
        : $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input);

    $real = realpath($candidate); // раскрывает .. и символические ссылки
    if ($real === false || !is_file($real)) {
        return null;
    }
    if (pathIsInside($real, $root)) {
        return $real;
    }

    $logFile = $GLOBALS['fullLogPath'] ?? '';
    $logReal = $logFile !== '' ? realpath($logFile) : false;
    if ($logReal !== false && pathIsInside($real, $logReal) && pathIsInside($logReal, $real)) {
        return $real; // ровно файл лога
    }
    return null;
}

/** Сведения о файле для раздела «Информация»: размер, дата изменения, версия из заголовка. */
function fileInfoPayload($path) {
    $size = filesize($path);
    $mtime = filemtime($path);
    if ($size < 1024) $sizeFormatted = $size . ' B';
    elseif ($size < 1048576) $sizeFormatted = round($size / 1024, 1) . ' KB';
    else $sizeFormatted = round($size / 1048576, 1) . ' MB';
    $version = function_exists('getFileVersionFromContent') ? (string)getFileVersionFromContent($path) : '';
    return ['size' => $sizeFormatted, 'date' => date('d.m.Y H:i', $mtime), 'version' => $version];
}

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ ЭКСПОРТА
// ============================================

/**
 * Рекурсивное копирование директории
 */
function copyDir($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    $files = scandir($source);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $source . '/' . $file;
        $dstPath = $dest . '/' . $file;
        if (is_dir($srcPath)) {
            copyDir($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

/**
 * Копирование директории с исключением указанных файлов/папок
 */
function copyDirWithExclude($source, $dest, $exclude = []) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    $files = scandir($source);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (in_array($file, $exclude)) continue;
        $srcPath = $source . '/' . $file;
        $dstPath = $dest . '/' . $file;
        if (is_dir($srcPath)) {
            copyDirWithExclude($srcPath, $dstPath, $exclude);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

/**
 * Рекурсивное удаление директории
 */
function deleteDir($dir) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Единая проверка прав: вход + право на конкретное действие (см. ajaxActionPermissions)
    ajaxAuthorizeOrDie($_POST['ajax']);
    
    // Действия, меняющие конфиг: блокировка и свежее чтение (параллельные запросы не должны терять изменения друг друга)
    if (isset($configFile)
        && in_array($_POST['ajax'], ['add_device', 'add_whitelist_ip', 'remove_whitelist_ip', 'mass_move_devices', 'rename_group', 'delete_group', 'save_group_changes'], true)) {
        if (!config_refresh($config, $configFile)) {
            echo json_encode(['success' => false, 'message' => __('msg_save_error')], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Получаем активность по датам
    if ($_POST['ajax'] === 'get_activity') {
        $period = $_POST['period'] ?? 'day';
        $activityLog = $GLOBALS['fullLogPath'];
        $activity = logAggCached('activity', $activityLog, function () use ($activityLog, $period) {
            return getActivityData($activityLog, $period);
        }, (string)$period);
        echo json_encode(['success' => true, 'data' => $activity]);
        exit;
    }
    
    // Получаем активность по устройству
    if ($_POST['ajax'] === 'get_device_activity') {
        $device = $_POST['device'] ?? '';
        $period = $_POST['period'] ?? 'day';
        
        if (empty($device)) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }
        
        $activity = getDeviceActivity($GLOBALS['fullLogPath'], $device, $period);
        echo json_encode(['success' => true, 'data' => $activity]);
        exit;
    }
    
    // Предпросмотр темы
    if ($_POST['ajax'] === 'preview_theme') {
        $theme = $_POST['theme'] ?? 'dark';
        
        require_once __DIR__ . '/../vlmctheme.php';
        $themeCSS = getThemeCSS($theme);
        
        echo json_encode([
            'success' => true,
            'bg' => $themeCSS['bg'],
            'text' => $themeCSS['text'],
            'card' => $themeCSS['card'],
            'border' => $themeCSS['border'],
            'primary' => $themeCSS['primary'],
            'success' => $themeCSS['success'],
            'danger' => $themeCSS['danger']
        ]);
        exit;
    }
    
    // Получаем информацию о файле
    if ($_POST['ajax'] === 'file_info' && isset($_POST['path'])) {
        // Только файлы проекта и файл лога; любой другой путь — такой же ответ, как у несуществующего файла
        $path = resolveProjectFilePath($_POST['path']);
        if ($path !== null) {
            echo json_encode(['success' => true] + fileInfoPayload($path));
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Пакетное получение сведений о файлах (кнопка «Обновить» в разделе «Информация»): один запрос на все файлы
    if ($_POST['ajax'] === 'file_info_batch') {
        $paths = json_decode($_POST['paths'] ?? '[]', true);
        $files = [];
        if (is_array($paths)) {
            foreach (array_slice($paths, 0, 500) as $requested) {
                $real = is_string($requested) ? resolveProjectFilePath($requested) : null;
                $files[] = ($real === null)
                    ? ['path' => (string)$requested, 'success' => false]
                    : ['path' => $requested, 'success' => true] + fileInfoPayload($real);
            }
        }
        echo json_encode(['success' => true, 'files' => $files, 'time' => date('H:i:s')]);
        exit;
    }
    
    // Создание резервной копии лога
    if ($_POST['ajax'] === 'backup_log') {
        $fullLogPath = $GLOBALS['fullLogPath'];
        if (!file_exists($fullLogPath)) {
            echo json_encode(['success' => false, 'message' => __('msg_log_not_found')]);
            exit;
        }
        $backupDir = dirname(__DIR__) . '/backups';
        if (!file_exists($backupDir)) mkdir($backupDir, 0755, true);
        $backupFile = $backupDir . '/vlmcsd_' . date('Y-m-d_H-i-s') . '.log';
        if (copy($fullLogPath, $backupFile)) {
            echo json_encode(['success' => true, 'message' => __('msg_backup_created') . ': ' . basename($backupFile)]);
        } else {
            echo json_encode(['success' => false, 'message' => __('msg_backup_error')]);
        }
        exit;
    }
    
    // Очистка лога
    if ($_POST['ajax'] === 'clear_log') {
        $fullLogPath = $GLOBALS['fullLogPath'];
        $clearType = $_POST['clearType'] ?? 'all';
        $startDate = $_POST['startDate'] ?? '';
        $endDate = $_POST['endDate'] ?? '';
        
        if (!file_exists($fullLogPath)) {
            echo json_encode(['success' => false, 'message' => __('msg_log_not_found')]);
            exit;
        }
        
        $content = file_get_contents($fullLogPath);
        $lines = explode("\n", $content);
        $newLines = [];
        $deletedCount = 0;
        
        if ($clearType === 'all') {
            file_put_contents($fullLogPath, '');
            $deletedCount = count($lines);
        } else if ($clearType === 'date_range' && $startDate && $endDate) {
            $start = strtotime($startDate);
            $end = strtotime($endDate . ' 23:59:59');
            
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                    $lineDate = strtotime($matches[1]);
                    if ($lineDate >= $start && $lineDate <= $end) {
                        $deletedCount++;
                        continue;
                    }
                }
                $newLines[] = $line;
            }
            
            file_put_contents($fullLogPath, implode("\n", $newLines));
        }
        
        echo json_encode(['success' => true, 'message' => sprintf(__('msg_records_deleted'), $deletedCount), 'deleted' => $deletedCount]);
        exit;
    }
    
    // ============================================
    // AJAX: Управление пользователями
    // ============================================
    
    // Получить список пользователей
    if ($_POST['ajax'] === 'get_users') {
        require_once __DIR__ . '/users.php';
        try {
            $users = getAllUsers();
            foreach ($users as &$user) {
                unset($user['password_hash']);
            }
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Добавить пользователя
    if ($_POST['ajax'] === 'add_user') {
        require_once __DIR__ . '/users.php';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = (int)($_POST['permissions'] ?? PERM_DEVICES_VIEW);
        
        $result = addUser($username, $password, $permissions);
        echo json_encode($result);
        exit;
    }
    
    // Обновить пользователя
    if ($_POST['ajax'] === 'update_user') {
        require_once __DIR__ . '/users.php';
        $id = (int)($_POST['id'] ?? 0);
        $data = [];
        
        if (isset($_POST['username'])) $data['username'] = trim($_POST['username']);
        if (isset($_POST['permissions'])) $data['permissions'] = (int)$_POST['permissions'];
        
        if (empty($data)) {
            echo json_encode(['success' => false, 'message' => __('msg_invalid_data')]);
            exit;
        }
        
        $result = updateUser($id, $data);
        echo json_encode(['success' => $result, 'message' => $result ? __('user_updated') : __('msg_save_error')]);
        exit;
    }
    
    // Удалить пользователя
    if ($_POST['ajax'] === 'delete_user') {
        require_once __DIR__ . '/users.php';
        $id = (int)($_POST['id'] ?? 0);
        $result = deleteUser($id);
        echo json_encode($result);
        exit;
    }
    
    // Смена пароля пользователя (только для админов)
    if ($_POST['ajax'] === 'change_user_password') {
        require_once __DIR__ . '/users.php';
        
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        
        if ($userId == 0 || empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => __('msg_invalid_data')]);
            exit;
        }
        
        $result = changeUserPasswordAsAdmin($userId, $newPassword);
        echo json_encode($result);
        exit;
    }
    
    // ============================================
    // AJAX: Добавление устройства (с проверкой прав)
    // ============================================
    if ($_POST['ajax'] === 'add_device') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => __('error_no_permission_to_add')]);
            exit;
        }
        
        $userPermissions = $_SESSION['vlmc_permissions'] ?? 0;
        if (!($userPermissions & PERM_DEVICES_EDIT)) {
            echo json_encode(['success' => false, 'message' => __('error_no_permission_to_add')]);
            exit;
        }
        
        $deviceName = trim($_POST['deviceName'] ?? '');
        $deviceGroup = $_POST['deviceGroup'] ?? '';
        $deviceComment = trim($_POST['deviceComment'] ?? '');
        
        $response = ['success' => false, 'message' => ''];
        
        if (empty($deviceName) || empty($deviceGroup)) {
            $response['message'] = __('add_device_error_name_group');
        } else if (!isset($config['devices'][$deviceGroup])) {
            $response['message'] = __('add_device_error_group_not_exists');
        } else {
            $exists = false;
            foreach ($config['devices'][$deviceGroup] as $existing) {
                if ($existing['name'] === $deviceName) {
                    $exists = true;
                    break;
                }
            }
            
            if ($exists) {
                $response['message'] = __('add_device_error_already_exists');
            } else {
                $config['devices'][$deviceGroup][] = [
                    'name' => $deviceName,
                    'comment' => $deviceComment,
                    'added' => date('Y-m-d H:i:s')
                ];
                save_config($configFile, $config);
                $response['success'] = true;
                $response['message'] = __('add_device_success');
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ============================================
    // AJAX: Управление белыми IP
    // ============================================
    
    // Получить список белых IP
    if ($_POST['ajax'] === 'get_whitelist_ips') {
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => __('access_denied')]);
            exit;
        }
        
        $whitelistIps = $config['whitelist_ips'] ?? [];
        echo json_encode(['success' => true, 'ips' => $whitelistIps]);
        exit;
    }
    
    // Добавить IP в белый список
    if ($_POST['ajax'] === 'add_whitelist_ip') {
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => __('access_denied')]);
            exit;
        }
        
        $userPermissions = $_SESSION['vlmc_permissions'] ?? 0;
        if (!hasPermission($userPermissions, PERM_IP_WHITELIST)) {
            echo json_encode(['success' => false, 'message' => __('access_denied')]);
            exit;
        }
        
        $ip = trim($_POST['ip'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        
        // Валидация IP
        if (empty($ip)) {
            echo json_encode(['success' => false, 'message' => __('whitelist_ip_required')]);
            exit;
        }
        
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            echo json_encode(['success' => false, 'message' => __('whitelist_ip_invalid')]);
            exit;
        }
        
        // Получаем текущий список
        $whitelistIps = $config['whitelist_ips'] ?? [];
        
        // Проверяем, не существует ли уже
        if (in_array($ip, $whitelistIps)) {
            echo json_encode(['success' => false, 'message' => __('whitelist_ip_exists')]);
            exit;
        }
        
        // Добавляем IP
        $whitelistIps[] = $ip;
        $config['whitelist_ips'] = $whitelistIps;
        $config['last_modified'] = date('Y-m-d H:i:s');
        
        if (save_config($configFile, $config)) {
            echo json_encode(['success' => true, 'message' => __('whitelist_ip_added')]);
        } else {
            echo json_encode(['success' => false, 'message' => __('msg_save_error')]);
        }
        exit;
    }
    
    // Удалить IP из белого списка
    if ($_POST['ajax'] === 'remove_whitelist_ip') {
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => __('access_denied')]);
            exit;
        }
        
        $userPermissions = $_SESSION['vlmc_permissions'] ?? 0;
        if (!hasPermission($userPermissions, PERM_IP_WHITELIST)) {
            echo json_encode(['success' => false, 'message' => __('access_denied')]);
            exit;
        }
        
        $ip = trim($_POST['ip'] ?? '');
        
        if (empty($ip)) {
            echo json_encode(['success' => false, 'message' => __('whitelist_ip_required')]);
            exit;
        }
        
        $whitelistIps = $config['whitelist_ips'] ?? [];
        $newWhitelist = [];
        $removed = false;
        
        foreach ($whitelistIps as $existingIp) {
            if ($existingIp !== $ip) {
                $newWhitelist[] = $existingIp;
            } else {
                $removed = true;
            }
        }
        
        if (!$removed) {
            echo json_encode(['success' => false, 'message' => __('whitelist_ip_not_found')]);
            exit;
        }
        
        $config['whitelist_ips'] = $newWhitelist;
        $config['last_modified'] = date('Y-m-d H:i:s');
        
        if (save_config($configFile, $config)) {
            echo json_encode(['success' => true, 'message' => __('whitelist_ip_removed')]);
        } else {
            echo json_encode(['success' => false, 'message' => __('msg_save_error')]);
        }
        exit;
    }
    
    // ============================================
    // AJAX: Управление кэшем геолокации
    // ============================================
    
    // Очистка кэша геолокации
    if ($_POST['ajax'] === 'clear_geo_cache') {
        require_once __DIR__ . '/../vlmcinc/geo_cache.php';
        $result = clearGeoCache();
        echo json_encode($result);
        exit;
    }
    
    // Проверка статуса кэша геолокации
    if ($_POST['ajax'] === 'check_geo_cache') {
        require_once __DIR__ . '/../vlmcinc/geo_cache.php';
        $stats = getGeoCacheStats();
        echo json_encode([
            'success' => true,
            'count' => $stats['count'],
            'size' => $stats['size'],
            'size_formatted' => $stats['size_formatted']
        ]);
        exit;
    }
    
    // Определение стран для новых IP порциями (вызывается со страницы статистики, не блокирует рендер)
    if ($_POST['ajax'] === 'geo_resolve_portion') {
        header('Content-Type: application/json');
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        session_write_close(); // не держим блокировку сессии, пока идут сетевые запросы
        set_time_limit(60);
        require_once __DIR__ . '/../vlmcinc/geo_cache.php';
        echo json_encode(resolveGeoPortion($GLOBALS['fullLogPath'], 5));
        exit;
    }
    
    // Принудительное обновление кэша геолокации (порциями — формат ответа ожидает sections/tools.php)
    if ($_POST['ajax'] === 'refresh_geo_cache') {
        header('Content-Type: application/json');
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        session_write_close();
        require_once __DIR__ . '/../vlmcinc/geo_cache.php';
        
        $offset = max(0, (int)($_POST['offset'] ?? 0));
        $limit  = max(1, min(50, (int)($_POST['limit'] ?? 10)));
        
        $allIps = getAllIpsFromMonitor($GLOBALS['fullLogPath'], $config);
        
        if (empty($allIps)) {
            echo json_encode([
                'success' => true,
                'processed' => 0,
                'updated' => 0,
                'failed' => 0,
                'total_all' => 0,
                'has_more' => false,
                'next_offset' => 0,
                'message' => __('tools_cache_no_ips')
            ]);
            exit;
        }
        
        echo json_encode(refreshGeoCacheByPortion($allIps, $offset, $limit));
        exit;
    }
    
    // ============================================
    // AJAX: Экспорт проекта (универсальный)
    // ============================================
    if ($_POST['ajax'] === 'export_project') {
        $exportType = $_POST['export_type'] ?? 'clean';
        
        if (!in_array($exportType, ['clean', 'config', 'full'])) {
            echo json_encode(['success' => false, 'message' => __('msg_invalid_data')]);
            exit;
        }
        
        $tempDir = '/tmp/kms_export_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => __('export_error')]);
            exit;
        }
        
        $baseDir = dirname(__DIR__, 2);
        
        $alwaysExclude = [
            '.git', '.gitignore', '.DS_Store', 'Thumbs.db',
            'cache', 'tmp', 'temp', 'data_dir.local.php'
        ];
        
        $shouldExclude = function($item, $currentPath, $exportType) use ($alwaysExclude) {
            if (in_array($item, $alwaysExclude)) {
                return true;
            }
            
            if (strpos($item, '.') === 0 && $item !== '.htaccess') {
                return true;
            }
            
            if (preg_match('/\.log$/', $item)) {
                return $exportType !== 'full';
            }
            
            if ($item === 'vlmcconf_config.json' || $item === 'users.json') {
                return $exportType === 'clean';
            }
            
            if (strpos($currentPath, 'vlmcconf') !== false) {
                if ($item === 'cache' || $item === 'backups') {
                    return true;
                }
                
                if (preg_match('/\.log$/', $item)) {
                    return $exportType !== 'full';
                }
            }
            
            return false;
        };
        
        $copyWithExclude = function($source, $dest, $exportType, &$copyWithExclude) use ($shouldExclude) {
            if (!is_dir($source)) return;
            if (!is_dir($dest)) mkdir($dest, 0755, true);
            
            $items = scandir($source);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $sourcePath = $source . '/' . $item;
                $destPath = $dest . '/' . $item;
                
                if ($shouldExclude($item, $source, $exportType)) continue;
                
                if (is_dir($sourcePath)) {
                    $copyWithExclude($sourcePath, $destPath, $exportType, $copyWithExclude);
                } else {
                    copy($sourcePath, $destPath);
                }
            }
        };
        
        $rootItems = scandir($baseDir);
        foreach ($rootItems as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $sourcePath = $baseDir . '/' . $item;
            $destPath = $tempDir . '/' . $item;
            
            if ($shouldExclude($item, $baseDir, $exportType)) continue;
            
            if (is_dir($sourcePath)) {
                $copyWithExclude($sourcePath, $destPath, $exportType, $copyWithExclude);
            } else {
                copy($sourcePath, $destPath);
            }
        }
        
        // Файлы данных лежат вне папки панели: для экспортов «config» и «full» кладём их в архив
        // на прежнее место (vlmcconf/) — так архив восстанавливается как раньше
        if ($exportType !== 'clean') {
            foreach (unserialize(VLMC_DATA_FILES) as $dataName) {
                $dataPath = vlmcDataFile($dataName);
                if (is_file($dataPath)) {
                    if (!is_dir($tempDir . '/vlmcconf')) {
                        mkdir($tempDir . '/vlmcconf', 0755, true);
                    }
                    copy($dataPath, $tempDir . '/vlmcconf/' . $dataName);
                }
            }
        }
        
        if ($exportType === 'full') {
            $cacheDir = $tempDir . '/vlmcconf/cache';
            if (is_dir($cacheDir)) {
                deleteDir($cacheDir);
            }
        }
        
        $archiveName = 'kms_export_' . $exportType . '_' . date('Y-m-d_H-i-s') . '.tar.gz';
        $archivePath = '/tmp/' . $archiveName;
        
        $archiveCreated = false;
        
        if (class_exists('PharData')) {
            try {
                $phar = new PharData('/tmp/kms_export_temp.tar');
                $phar->buildFromDirectory($tempDir);
                $phar->compress(Phar::GZ);
                if (file_exists('/tmp/kms_export_temp.tar.gz')) {
                    rename('/tmp/kms_export_temp.tar.gz', $archivePath);
                    $archiveCreated = true;
                }
                if (file_exists('/tmp/kms_export_temp.tar')) {
                    unlink('/tmp/kms_export_temp.tar');
                }
            } catch (Exception $e) {
                $archiveCreated = false;
            }
        }
        
        if (!$archiveCreated) {
            $tarCmd = "cd " . escapeshellarg($tempDir) . " && tar -czf " . escapeshellarg($archivePath) . " . 2>/dev/null";
            exec($tarCmd, $output, $returnCode);
            if ($returnCode === 0 && file_exists($archivePath) && filesize($archivePath) > 0) {
                $archiveCreated = true;
            }
        }
        
        if (!$archiveCreated || !file_exists($archivePath) || filesize($archivePath) === 0) {
            deleteDir($tempDir);
            if (file_exists($archivePath)) @unlink($archivePath);
            echo json_encode(['success' => false, 'message' => __('export_error')]);
            exit;
        }
        
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $archiveName . '"');
        header('Content-Length: ' . filesize($archivePath));
        header('Cache-Control: private');
        header('Pragma: public');
        
        if (readfile($archivePath) === false) {
            echo json_encode(['success' => false, 'message' => __('export_error')]);
        }
        
        deleteDir($tempDir);
        if (file_exists($archivePath)) @unlink($archivePath);
        exit;
    }
    
    // ============================================
    // AJAX: Сброс настроек
    // ============================================
    if (isset($_POST['action']) && $_POST['action'] === 'reset_config') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => __('access_denied')]);
            exit;
        }
        
        $userPermissions = $_SESSION['vlmc_permissions'] ?? 0;
        if (!hasPermission($userPermissions, PERM_ADMIN_FULL)) {
            echo json_encode(['success' => false, 'message' => __('access_denied')]);
            exit;
        }
        
        $configFile = vlmcConfigFile();
        
        $defaultConfig = [
            'config_version' => CONFIG_VERSION,
            'config_date' => date('Y-m-d'),
            'theme' => 'dark',
            'language' => 'ru',
            'logPath' => 'vlmcsd.log',
            'groupColors' => [
                'Домашние' => '#2ecc71',
                'Рабочие' => '#e74c3c',
                'Знакомые' => '#3498db',
                'Клиенты' => '#9b59b6'
            ],
            'devices' => [],
            'whitelist_ips' => []
        ];
        
        if (save_config($configFile, $defaultConfig)) {
            echo json_encode(['success' => true, 'message' => __('msg_config_reset')]);
        } else {
            echo json_encode(['success' => false, 'message' => __('msg_save_error')]);
        }
        exit;
    }
    
    // ============================================
    // AJAX: Проверка прав пользователя
    // ============================================
    if ($_POST['ajax'] === 'check_permission') {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE && !defined('VLMC_SESSION_CLOSED')) {
            vlmcSessionStart();
        }
        
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['has_permission' => false]);
            exit;
        }
        
        $permission = $_POST['permission'] ?? '';
        
        if ($permission === 'PERM_DEVICES_EDIT') {
            $userPermissions = $_SESSION['vlmc_permissions'] ?? 0;
            $hasPermission = ($userPermissions & 8) === 8;
            echo json_encode(['has_permission' => $hasPermission]);
            exit;
        }
        
        if ($permission === 'PERM_IP_WHITELIST') {
            $userPermissions = $_SESSION['vlmc_permissions'] ?? 0;
            $hasPermission = ($userPermissions & PERM_IP_WHITELIST) === PERM_IP_WHITELIST;
            echo json_encode(['has_permission' => $hasPermission]);
            exit;
        }
        
        echo json_encode(['has_permission' => false]);
        exit;
    }
    
	// ============================================
	// AJAX: Управление кэшем эмодзи
	// ============================================

	if ($_POST['ajax'] === 'check_emoji_cache') {
		require_once __DIR__ . '/emoji_manager.php';
		
		$exists = file_exists(EMOJI_CACHE_FILE);
		$size = $exists ? filesize(EMOJI_CACHE_FILE) : 0;
		
		echo json_encode([
			'success' => true,
			'exists' => $exists,
			'size' => $size,
			'size_formatted' => $exists ? formatSize($size) : '0 B'
		]);
		exit;
	}

	if ($_POST['ajax'] === 'clear_emoji_cache') {
		require_once __DIR__ . '/emoji_manager.php';
		
		if (clear_emoji_cache()) {
			echo json_encode(['success' => true, 'message' => __('tools_cache_cleared')]);
		} else {
			echo json_encode(['success' => false, 'message' => __('msg_save_error')]);
		}
		exit;
	}

	if ($_POST['ajax'] === 'refresh_emoji_cache') {
		require_once __DIR__ . '/emoji_manager.php';
		
		$result = refresh_emoji_cache();
		echo json_encode($result);
		exit;
	}
	
	// ============================================
	// AJAX: Переименование группы
	// ============================================
	if ($_POST['ajax'] === 'rename_group') {
		if (!check_permission(PERM_GROUPS_EDIT)) {
			echo json_encode(['success' => false, 'message' => __('access_denied')]);
			exit;
		}
		
		$oldName = trim($_POST['old_name'] ?? '');
		$newName = trim($_POST['new_name'] ?? '');
		
		if (empty($oldName) || empty($newName)) {
			echo json_encode(['success' => false, 'message' => __('msg_invalid_data')]);
			exit;
		}
		
		$configFile = vlmcConfigFile();
		$config = config_read($configFile)['config'];
		
		if (!isset($config['groupColors'][$oldName])) {
			echo json_encode(['success' => false, 'message' => __('msg_group_not_found')]);
			exit;
		}
		
		if (isset($config['groupColors'][$newName])) {
			echo json_encode(['success' => false, 'message' => __('msg_group_exists')]);
			exit;
		}
		
		// Переименовываем группу
		$config['groupColors'][$newName] = $config['groupColors'][$oldName];
		unset($config['groupColors'][$oldName]);
		
		// Переименовываем устройства
		if (isset($config['devices'][$oldName])) {
			$config['devices'][$newName] = $config['devices'][$oldName];
			unset($config['devices'][$oldName]);
		}
		
		$config['last_modified'] = date('Y-m-d H:i:s');
		save_config($configFile, $config);
		
		echo json_encode(['success' => true, 'message' => __('msg_group_renamed')]);
		exit;
	}

	// ============================================
	// AJAX: Удаление группы
	// ============================================
	if ($_POST['ajax'] === 'delete_group') {
		if (!check_permission(PERM_GROUPS_EDIT)) {
			echo json_encode(['success' => false, 'message' => __('access_denied')]);
			exit;
		}
		
		$groupName = trim($_POST['group_name'] ?? '');
		
		if (empty($groupName)) {
			echo json_encode(['success' => false, 'message' => __('msg_invalid_data')]);
			exit;
		}
		
		$configFile = vlmcConfigFile();
		$config = config_read($configFile)['config'];
		
		if (!isset($config['groupColors'][$groupName])) {
			echo json_encode(['success' => false, 'message' => __('msg_group_not_found')]);
			exit;
		}
		
		// Переносим устройства в группу-заглушку __orphaned__
		if (isset($config['devices'][$groupName])) {
			if (!isset($config['devices']['__orphaned__'])) {
				$config['devices']['__orphaned__'] = [];
			}
			$config['devices']['__orphaned__'] = array_merge($config['devices']['__orphaned__'], $config['devices'][$groupName]);
			unset($config['devices'][$groupName]);
		}
		
		// Удаляем группу
		unset($config['groupColors'][$groupName]);
		
		$config['last_modified'] = date('Y-m-d H:i:s');
		save_config($configFile, $config);
		
		echo json_encode(['success' => true, 'message' => __('msg_group_deleted')]);
		exit;
	}
	
	// ============================================
	// AJAX: Сохранение изменений группы (имя, иконка, цвет)
	// ============================================
	if ($_POST['ajax'] === 'save_group_changes') {
		if (!check_permission(PERM_GROUPS_EDIT)) {
			echo json_encode(['success' => false, 'message' => __('access_denied')]);
			exit;
		}
		
		$oldName = trim($_POST['old_name'] ?? '');
		$newName = trim($_POST['new_name'] ?? '');
		$newIcon = trim($_POST['new_icon'] ?? '📁');
		$newColor = trim($_POST['new_color'] ?? '#3498db');
		
		if (empty($oldName) || empty($newName)) {
			echo json_encode(['success' => false, 'message' => __('msg_invalid_data')]);
			exit;
		}
		
		$configFile = vlmcConfigFile();
		$config = config_read($configFile)['config'];
		
		if (!isset($config['groupColors'][$oldName])) {
			echo json_encode(['success' => false, 'message' => __('msg_group_not_found')]);
			exit;
		}
		
		// Если имя изменилось, проверяем что новой группы нет
		if ($newName !== $oldName && isset($config['groupColors'][$newName])) {
			echo json_encode(['success' => false, 'message' => __('msg_group_exists')]);
			exit;
		}
		
		// Обновляем группу
		if ($newName !== $oldName) {
			// Переименовываем
			$config['groupColors'][$newName] = $config['groupColors'][$oldName];
			unset($config['groupColors'][$oldName]);
			
			// Переименовываем устройства
			if (isset($config['devices'][$oldName])) {
				$config['devices'][$newName] = $config['devices'][$oldName];
				unset($config['devices'][$oldName]);
			}
		}
		
		// Обновляем иконку и цвет
		$config['groupColors'][$newName]['icon'] = $newIcon;
		$config['groupColors'][$newName]['color'] = $newColor;
		
		$config['last_modified'] = date('Y-m-d H:i:s');
		save_config($configFile, $config);
		
		echo json_encode(['success' => true, 'message' => __('msg_group_saved')]);
		exit;
	}
	
	// ============================================
	// AJAX: Массовое перемещение устройств
	// ============================================
	if ($_POST['ajax'] === 'mass_move_devices') {
		if (!check_permission(PERM_DEVICES_EDIT)) {
			echo json_encode(['success' => false, 'message' => __('access_denied')]);
			exit;
		}
		
		$deviceNames = json_decode($_POST['device_names'] ?? '[]', true);
		$targetGroup = trim($_POST['target_group'] ?? '');
		
		if (empty($deviceNames) || empty($targetGroup)) {
			echo json_encode(['success' => false, 'message' => __('msg_invalid_data')]);
			exit;
		}
		
		$configFile = vlmcConfigFile();
		$config = config_read($configFile)['config'];
		
		if (!isset($config['groupColors'][$targetGroup])) {
			echo json_encode(['success' => false, 'message' => __('msg_group_not_found')]);
			exit;
		}
		
		$moved = 0;
		
		foreach ($config['devices'] as $group => &$devices) {
			foreach ($devices as $key => $device) {
				if (in_array($device['name'], $deviceNames)) {
					unset($devices[$key]);
					if (!isset($config['devices'][$targetGroup])) {
						$config['devices'][$targetGroup] = [];
					}
					$device['moved'] = date('Y-m-d H:i:s');
					$config['devices'][$targetGroup][] = $device;
					$moved++;
				}
			}
			$devices = array_values($devices);
		}
		
		if ($moved === 0) {
			echo json_encode(['success' => false, 'message' => __('devices_no_selected')]);
			exit;
		}
		
		$config['last_modified'] = date('Y-m-d H:i:s');
		save_config($configFile, $config);
		
		echo json_encode(['success' => true, 'message' => sprintf(__('devices_moved'), $moved, $targetGroup)]);
		exit;
	}
	
// ============================================
// AJAX: Обновление панели с GitHub (проверка связи, проверка каналов, список файлов, установка, настройки)
// ============================================
if (in_array($_POST['ajax'], ['check_github_access', 'check_updates', 'update_auto_check', 'get_update_files', 'perform_update', 'save_update_settings'], true)) {
    require_once __DIR__ . '/updater.php';
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // долгие сетевые запросы не должны держать сессию
    }
    $updater = new Updater();
    $action = $_POST['ajax'];
    $updateSettings = $updater->getSettings();

    // Канал из запроса (если задан и допустим) или выбранный в настройках
    $channel = (isset($_POST['channel']) && in_array($_POST['channel'], Updater::CHANNELS, true))
        ? $_POST['channel']
        : $updateSettings['type'];

    // --- Проверка связи с GitHub и SSL ---
    if ($action === 'check_github_access') {
        $r = $updater->checkGitHubAccess();
        if ($r['success']) {
            $r['message'] = sprintf(__('update_github_ok'), $r['remaining'], $r['limit']);
        } else {
            $r['message'] = Updater::errorText($r['error'], $r);
            $r['ssl_help'] = in_array($r['error'], ['ssl_ca', 'ssl'], true);
        }
        unset($r['ssl']);
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Проверка обоих каналов: кнопкой (всегда заново) или автоматически (по интервалу) ---
    if ($action === 'check_updates' || $action === 'update_auto_check') {
        if ($action === 'update_auto_check' && !$updateSettings['auto_check']) {
            echo json_encode(['success' => true, 'enabled' => false]);
            exit;
        }
        $res = $updater->checkAll($action === 'check_updates');
        $state = $res['state'];
        $out = [
            'success'         => true,
            'enabled'         => true,
            'cached'          => $res['cached'],
            'current_version' => $updater->getCurrentVersion(),
            'selected'        => $updateSettings['type'],
            'checked_at'      => (int)($state['checked_at'] ?? 0),
            'banner'          => $updater->bannerData($state),
            'channels'        => [],
            'ssl_help'        => false
        ];
        foreach (Updater::CHANNELS as $ch) {
            $r = (isset($state[$ch]) && is_array($state[$ch])) ? $state[$ch] : ['success' => false, 'error' => 'bad_response'];
            if (empty($r['success'])) {
                $code = (string)($r['error'] ?? 'bad_response');
                $out['ssl_help'] = $out['ssl_help'] || in_array($code, ['ssl_ca', 'ssl'], true);
                $out['channels'][$ch] = ['success' => false, 'error' => $code, 'message' => Updater::errorText($code, $r)];
                continue;
            }
            $out['channels'][$ch] = [
                'success'        => true,
                'available'      => (bool)$r['available'],
                'newer'          => $r['newer'],
                'version'        => (string)$r['version'],
                'commit'         => substr((string)$r['commit'], 0, 7),
                'published_at'   => (string)$r['published_at'],
                'committed_at'   => (string)$r['committed_at'],
                'subject'        => (string)$r['subject'],
                'new_count'      => (int)$r['new_count'],
                'changed_count'  => (int)$r['changed_count'],
                'obsolete_count' => (int)$r['obsolete_count'],
                'main_behind'    => isset($r['main_behind']) ? $r['main_behind'] : null
            ];
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Список файлов для окна подтверждения ---
    if ($action === 'get_update_files') {
        $r = $updater->getFilesToUpdate($channel);
        if (empty($r['success'])) {
            $r['message'] = Updater::errorText((string)($r['error'] ?? 'bad_response'), $r);
        }
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Установка выбранного канала ---
    if ($action === 'perform_update') {
        ignore_user_abort(true);
        set_time_limit(300);
        $files = json_decode($_POST['files'] ?? 'null', true);
        ob_start();
        $r = $updater->performUpdate($channel, is_array($files) ? $files : null);
        $stray = ob_get_clean();
        if ($stray !== '') {
            error_log('Update output: ' . $stray); // посторонний вывод в журнал, клиенту не отдаём
        }
        if (empty($r['success'])) {
            $r['message'] = Updater::errorText((string)($r['error'] ?? 'bad_response'), $r);
        } else {
            $r['message'] = sprintf(__('update_done'), (int)$r['updated_count'], basename((string)$r['backup_dir']));
        }
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Настройки: канал, автопроверка, интервал. Автоустановка, расписание и уведомления — в разработке ---
    if ($action === 'save_update_settings') {
        $configFile = vlmcConfigFile();
        config_lock($configFile);
        $config = config_read($configFile)['config'];
        if (!is_array($config)) {
            echo json_encode(['success' => false, 'message' => __('msg_save_error')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $type = $_POST['type'] ?? 'release';
        if (!in_array($type, Updater::CHANNELS, true)) {
            $type = 'release';
        }
        $interval = (int)($_POST['check_interval'] ?? 12);
        if (!in_array($interval, [1, 3, 6, 12, 24, 72], true)) {
            $interval = 12;
        }
        $config['update']['type'] = $type;
        $config['update']['auto_check'] = (($_POST['auto_check'] ?? '') === 'true');
        $config['update']['check_interval_hours'] = $interval;
        $config['update']['mode'] = 'manual';
        $saved = save_config($configFile, $config);
        echo json_encode(['success' => $saved, 'message' => __($saved ? 'update_settings_saved' : 'msg_save_error')], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
    echo json_encode(['success' => false]);
    exit;
}
?>