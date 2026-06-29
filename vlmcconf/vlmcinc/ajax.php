<?php
// ============================================
// ФАЙЛ: vlmcinc/ajax.php
// ВЕРСИЯ: 3.2.0
// ДАТА: 2026-06-01
// @description: Все AJAX обработчики с проверкой прав
// ============================================

// Сессия может быть уже запущена (в vlmcconf.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/users.php';

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
    
    // Получаем активность по датам
    if ($_POST['ajax'] === 'get_activity') {
        $period = $_POST['period'] ?? 'day';
        $activity = getActivityData($GLOBALS['fullLogPath'], $period);
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
        $path = $_POST['path'];
        if (file_exists($path)) {
            $size = filesize($path);
            $mtime = filemtime($path);
            if ($size < 1024) $sizeFormatted = $size . ' B';
            elseif ($size < 1048576) $sizeFormatted = round($size/1024,1) . ' KB';
            else $sizeFormatted = round($size/1048576,1) . ' MB';
            $dateFormatted = date('d.m.Y H:i', $mtime);
            echo json_encode(['success' => true, 'size' => $sizeFormatted, 'date' => $dateFormatted]);
        } else {
            echo json_encode(['success' => false]);
        }
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
                file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
        
        if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
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
        
        if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
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
    
    // Принудительное обновление кэша геолокации
    if ($_POST['ajax'] === 'refresh_geo_cache') {
        require_once __DIR__ . '/../vlmcinc/geo_cache.php';
        
        $fullLogPath = $GLOBALS['fullLogPath'];
        $allIps = getAllIpsFromMonitor($fullLogPath, $config);
        
        if (empty($allIps)) {
            echo json_encode([
                'success' => true, 
                'updated' => 0, 
                'failed' => 0, 
                'total' => 0, 
                'message' => __('tools_cache_no_ips')
            ]);
            exit;
        }
        
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                $result = refreshGeoCacheForIpsFast($allIps);
                echo json_encode($result);
            } elseif ($pid == 0) {
                refreshGeoCacheForIpsBackground($allIps);
                exit;
            } else {
                echo json_encode([
                    'success' => true, 
                    'async' => true,
                    'total' => count($allIps),
                    'message' => __('tools_cache_background')
                ]);
            }
        } else {
            $result = refreshGeoCacheForIpsFast($allIps);
            echo json_encode($result);
        }
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
            'cache', 'tmp', 'temp'
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
        
        $configFile = dirname(__DIR__) . '/vlmcconf_config.json';
        
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
        
        if (file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
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
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
		
		$configFile = dirname(__DIR__) . '/vlmcconf_config.json';
		$config = json_decode(file_get_contents($configFile), true);
		
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
		file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		
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
		
		$configFile = dirname(__DIR__) . '/vlmcconf_config.json';
		$config = json_decode(file_get_contents($configFile), true);
		
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
		file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		
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
		
		$configFile = dirname(__DIR__) . '/vlmcconf_config.json';
		$config = json_decode(file_get_contents($configFile), true);
		
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
		file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		
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
		
		$configFile = dirname(__DIR__) . '/vlmcconf_config.json';
		$config = json_decode(file_get_contents($configFile), true);
		
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
		file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		
		echo json_encode(['success' => true, 'message' => sprintf(__('devices_moved'), $moved, $targetGroup)]);
		exit;
	}
	
// ============================================
// AJAX: Проверка доступности GitHub
// ============================================
if ($_POST['ajax'] === 'check_github_access') {
    require_once __DIR__ . '/updater.php';
    $updater = new Updater();
    $result = $updater->checkGitHubAccess();
    echo json_encode($result);
    exit;
}

// ============================================
// AJAX: Проверка обновлений
// ============================================
if ($_POST['ajax'] === 'check_updates') {
    require_once __DIR__ . '/updater.php';
    $updater = new Updater();
    $result = $updater->checkUpdates();
    echo json_encode($result);
    exit;
}

// ============================================
// AJAX: Получение списка файлов для обновления
// ============================================
if ($_POST['ajax'] === 'get_update_files') {
    require_once __DIR__ . '/updater.php';
    $updater = new Updater();
    $result = $updater->getFilesToUpdate();
    echo json_encode($result);
    exit;
}

// ============================================
// AJAX: Выполнение обновления
// ============================================
if ($_POST['ajax'] === 'perform_update') {
    // Перехватываем весь вывод
    ob_start();
    
    $files = json_decode($_POST['files'] ?? '[]', true);
    
    require_once __DIR__ . '/updater.php';
    $updater = new Updater();
    $result = $updater->performUpdate($files);
    
    // Проверяем, что было выведено
    $output = ob_get_clean();
    if (!empty($output)) {
        // Логируем и отправляем ошибку
        error_log("Update output: " . $output);
        echo json_encode([
            'success' => false,
            'message' => '⚠️ ' . strip_tags(substr($output, 0, 500))
        ]);
        exit;
    }
    
    echo json_encode($result);
    exit;
}

// ============================================
// AJAX: Сохранение настроек в Обновлении
// ============================================
if ($_POST['ajax'] === 'save_update_settings') {
    $configFile = dirname(__DIR__) . '/vlmcconf_config.json';
    $config = json_decode(file_get_contents($configFile), true);
    
    $config['update']['type'] = $_POST['type'] ?? 'release';
    $config['update']['mode'] = $_POST['mode'] ?? 'manual';
    $config['update']['schedule']['enabled'] = $_POST['schedule_enabled'] === 'true';
    $config['update']['schedule']['time'] = $_POST['schedule_time'] ?? '03:00';
    $config['update']['schedule']['days'] = isset($_POST['schedule_days']) ? json_decode($_POST['schedule_days'], true) : [1,2,3,4,5,6,7];
    $config['update']['notifications']['enabled'] = $_POST['notify_enabled'] === 'true';
    $config['update']['notifications']['email'] = $_POST['notify_email'] ?? '';
    $config['update']['notifications']['telegram'] = $_POST['notify_telegram'] ?? '';
    
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => true, 'message' => __('settings_saved')]);
    exit;
}

    echo json_encode(['success' => false]);
    exit;
}
?>