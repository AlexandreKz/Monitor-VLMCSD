<?php
// ============================================
// ФАЙЛ: vlmcinc/ajax.php
// ВЕРСИЯ: 2.5.0
// ДАТА: 2026-04-13
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
            echo json_encode(['success' => false, 'message' => 'Файл лога не найден']);
            exit;
        }
        $backupDir = dirname(__DIR__) . '/backups';
        if (!file_exists($backupDir)) mkdir($backupDir, 0755, true);
        $backupFile = $backupDir . '/vlmcsd_' . date('Y-m-d_H-i-s') . '.log';
        if (copy($fullLogPath, $backupFile)) {
            echo json_encode(['success' => true, 'message' => 'Резервная копия создана: ' . basename($backupFile)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ошибка создания резервной копии']);
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
            echo json_encode(['success' => false, 'message' => 'Файл лога не найден']);
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
        
        echo json_encode(['success' => true, 'message' => "✅ Удалено $deletedCount записей", 'deleted' => $deletedCount]);
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
            echo json_encode(['success' => false, 'message' => 'Нет данных для обновления']);
            exit;
        }
        
        $result = updateUser($id, $data);
        echo json_encode(['success' => $result, 'message' => $result ? 'Пользователь обновлен' : 'Ошибка обновления']);
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
            echo json_encode(['success' => false, 'message' => 'Неверные данные']);
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
        
        // Проверка авторизации
        if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
            echo json_encode(['success' => false, 'message' => __('error_no_permission_to_add')]);
            exit;
        }
        
        // Проверка права на редактирование устройств
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
            $response['message'] = 'Имя устройства и группа обязательны';
        } else if (!isset($config['devices'][$deviceGroup])) {
            $response['message'] = 'Группа не существует';
        } else {
            $exists = false;
            foreach ($config['devices'][$deviceGroup] as $existing) {
                if ($existing['name'] === $deviceName) {
                    $exists = true;
                    break;
                }
            }
            
            if ($exists) {
                $response['message'] = 'Устройство уже существует';
            } else {
                $config['devices'][$deviceGroup][] = [
                    'name' => $deviceName,
                    'comment' => $deviceComment,
                    'added' => date('Y-m-d H:i:s')
                ];
                file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $response['success'] = true;
                $response['message'] = 'Устройство добавлено';
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ============================================
    // AJAX: Экспорт проекта
    // ============================================
    if ($_POST['ajax'] === 'export_project') {
        $exportType = $_POST['export_type'] ?? 'clean';
        
        // Создаём временную директорию
        $tempDir = '/tmp/kms_export_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Не удалось создать временную директорию']);
            exit;
        }
        
        // Копируем файлы в зависимости от типа
        $baseDir = dirname(__DIR__, 2);
        
        // Всегда копируем основные файлы проекта
        $filesToCopy = [
            'vlmc.php',
            'index.php',
            '.htaccess',
            'pic'
        ];
        
        // Копируем папку vlmcconf (без sensitive файлов)
        $excludeFromVlmcconf = [];
        if ($exportType === 'clean') {
            $excludeFromVlmcconf = ['vlmcconf_config.json', 'users.json', 'backups', 'cache', 'tmp'];
        } elseif ($exportType === 'config') {
            $excludeFromVlmcconf = ['backups', 'cache', 'tmp'];
        } elseif ($exportType === 'full') {
            $excludeFromVlmcconf = ['cache', 'tmp'];
        }
        
        // Копируем основные файлы
        foreach ($filesToCopy as $item) {
            $source = $baseDir . '/' . $item;
            $dest = $tempDir . '/' . $item;
            if (file_exists($source)) {
                if (is_dir($source)) {
                    copyDir($source, $dest);
                } else {
                    copy($source, $dest);
                }
            }
        }
        
        // Копируем папку vlmcconf
        $vlmcconfSource = $baseDir . '/vlmcconf';
        $vlmcconfDest = $tempDir . '/vlmcconf';
        if (is_dir($vlmcconfSource)) {
            copyDirWithExclude($vlmcconfSource, $vlmcconfDest, $excludeFromVlmcconf);
        }
        
        // Для полного бэкапа добавляем лог-файлы
        if ($exportType === 'full') {
            $logFile = $baseDir . '/vlmcsd.log';
            if (file_exists($logFile)) {
                copy($logFile, $tempDir . '/vlmcsd.log');
            }
            $backupsDir = $baseDir . '/vlmcconf/backups';
            if (is_dir($backupsDir)) {
                copyDir($backupsDir, $tempDir . '/backups');
            }
        }
        
        // Создаём архив
        $archiveName = 'kms_export_' . $exportType . '_' . date('Y-m-d_H-i-s') . '.tar.gz';
        $archivePath = '/tmp/' . $archiveName;
        
        $phar = new PharData('/tmp/kms_export_temp.tar');
        $phar->buildFromDirectory($tempDir);
        $phar->compress(Phar::GZ);
        rename('/tmp/kms_export_temp.tar.gz', $archivePath);
        unlink('/tmp/kms_export_temp.tar');
        
        // Отправляем архив
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $archiveName . '"');
        header('Content-Length: ' . filesize($archivePath));
        readfile($archivePath);
        
        // Удаляем временные файлы
        deleteDir($tempDir);
        unlink($archivePath);
        exit;
    }
	
	// Проверка прав пользователя
if ($_POST['ajax'] === 'check_permission') {
    header('Content-Type: application/json');
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Проверяем авторизацию
    if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
        echo json_encode(['has_permission' => false]);
        exit;
    }
    
    $permission = $_POST['permission'] ?? '';
    
    // Проверяем право PERM_DEVICES_EDIT (8)
    if ($permission === 'PERM_DEVICES_EDIT') {
        $userPermissions = $_SESSION['vlmc_permissions'] ?? 0;
        $hasPermission = ($userPermissions & 8) === 8;
        echo json_encode(['has_permission' => $hasPermission]);
        exit;
    }
    
    echo json_encode(['has_permission' => false]);
    exit;
}

    echo json_encode(['success' => false]);
    exit;
}