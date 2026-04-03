<?php
// ============================================
// ФАЙЛ: vlmcconf/vlmcconf.php
// ВЕРСИЯ: 4.8.1
// ДАТА: 2026-04-03
// @description: Панель управления настройками монитора
// ============================================

session_start();

// ============================================
// ЗАЩИТА ОТ ПРЯМОГО ДОСТУПА К КОНФИГУРАЦИОННЫМ ФАЙЛАМ
// ============================================

$requestUri = $_SERVER['REQUEST_URI'];
$protectedExtensions = ['json', 'log', 'ini', 'conf', 'txt'];

foreach ($protectedExtensions as $ext) {
    if (preg_match('/\.' . $ext . '$/', $requestUri)) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
}

// Проверка авторизации
if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Проверка времени сессии (30 минут)
if (isset($_SESSION['vlmc_login_time']) && (time() - $_SESSION['vlmc_login_time'] > 1800)) {
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}
$_SESSION['vlmc_login_time'] = time();

// ============================================
// ПОДКЛЮЧЕНИЕ ВСЕХ НЕОБХОДИМЫХ ФАЙЛОВ
// ============================================
require_once __DIR__ . '/vlmctheme.php';
require_once __DIR__ . '/vlmcgeoip.php';
require_once __DIR__ . '/vlmcloghandler.php';
require_once __DIR__ . '/vlmcinc/config.php';
require_once __DIR__ . '/vlmcinc/structure.php';
require_once __DIR__ . '/vlmcinc/auth.php';
require_once __DIR__ . '/vlmcinc/analytics.php';
require_once __DIR__ . '/vlmcinc/users.php';

// ============================================
// ПРОВЕРКА ПРАВ
// ============================================

/**
 * Проверка наличия права доступа (битовая маска)
 */
function hasPermission($permissions, $mask) {
    return ($permissions & $mask) == $mask;
}

// Получаем права из сессии
$currentUserPermissions = $_SESSION['vlmc_permissions'] ?? 0;

// Принудительная установка для root (если права не установлены)
if ($currentUserPermissions == 0 && isset($_SESSION['vlmc_username']) && $_SESSION['vlmc_username'] === 'root') {
    $currentUserPermissions = PERM_ADMIN_FULL;
    $_SESSION['vlmc_permissions'] = $currentUserPermissions;
}

// Для обратной совместимости
$isAdmin = hasPermission($currentUserPermissions, PERM_USERS_EDIT);

define('CONFIG_VERSION', '4.6.0');
define('CONFIG_DATE', '2026-03-27');

$configFile = __DIR__ . '/vlmcconf_config.json';
$logFile = dirname(__DIR__) . '/vlmcsd.log';

$defaultGroupColors = [
    'Домашние' => '#2ecc71',
    'Рабочие' => '#e74c3c',
    'Знакомые' => '#3498db',
    'Клиенты' => '#9b59b6'
];

$defaultConfig = [
    'config_version' => CONFIG_VERSION,
    'config_date' => CONFIG_DATE,
    'theme' => 'dark',
    'language' => 'ru',
    'logPath' => 'vlmcsd.log',
    'groupColors' => $defaultGroupColors,
    'devices' => []
];

foreach (array_keys($defaultGroupColors) as $group) {
    $defaultConfig['devices'][$group] = [];
}

// Загрузка конфига
$config = $defaultConfig;
if (file_exists($configFile)) {
    $loaded = json_decode(file_get_contents($configFile), true);
    if ($loaded) {
        $config = array_merge($defaultConfig, $loaded);
        $config['config_version'] = CONFIG_VERSION;
        $config['config_date'] = CONFIG_DATE;
    }
}

$themeCSS = getThemeCSS($config['theme']);
$availableThemes = getAvailableThemes();

// Загрузка переводов
function loadTranslations($language) {
    $localeFile = __DIR__ . '/locale/' . $language . '.php';
    if (file_exists($localeFile)) {
        return include $localeFile;
    }
    return include __DIR__ . '/locale/ru.php';
}

$translations = loadTranslations($config['language'] ?? 'ru');

// Проверка пути к логу
$basePath = realpath(__DIR__ . '/..');
$logPath = $config['logPath'];
$fullLogPath = (strpos($logPath, '/') === 0) ? $logPath : $basePath . '/' . ltrim($logPath, './');
$logFileExists = file_exists($fullLogPath);
$logSize = $logFileExists ? filesize($fullLogPath) : 0;

// Функция для получения версии из файла
function getFileVersion($filePath) {
    if (!file_exists($filePath)) return '-';
    $content = @file_get_contents($filePath);
    if ($content === false) return '-';
    if (preg_match('~//\s*ВЕРСИЯ:\s*([0-9.]+)~', $content, $matches)) {
        return $matches[1];
    }
    return '-';
}

// Получаем версии файлов
$fileVersions = [
    'vlmc.php' => getFileVersion(dirname(__DIR__) . '/vlmc.php'),
    'vlmcconf.php' => getFileVersion(__DIR__ . '/vlmcconf.php'),
    'login.php' => getFileVersion(__DIR__ . '/login.php'),
    'logout.php' => getFileVersion(__DIR__ . '/logout.php'),
    'vlmctheme.php' => getFileVersion(__DIR__ . '/vlmctheme.php'),
    'vlmcgeoip.php' => getFileVersion(__DIR__ . '/vlmcgeoip.php'),
    'vlmcloghandler.php' => getFileVersion(__DIR__ . '/vlmcloghandler.php'),
];

$firstEvent = getFirstLogEvent($fullLogPath);

// ============================================
// СТАТИСТИКА
// ============================================

$deviceRequests = getDeviceRequests($fullLogPath);
list($oldestDevice, $newestDevice, $oldestDate, $newestDate) = getOldestNewestDevice($fullLogPath);
$serverInfo = getServerInfo();
$osName = $serverInfo['osName'];
$hostname = $serverInfo['hostname'];
$serverIp = $serverInfo['serverIp'];
$phpVersion = $serverInfo['phpVersion'];
$serverSoftware = $serverInfo['serverSoftware'];
$uptimeFormatted = $serverInfo['uptime'];
$commentsStats = getCommentsStats($config);
$devicesWithComments = $commentsStats['devicesWithComments'];
$avgCommentLength = $commentsStats['avgCommentLength'];
$uptime = getUptime($fullLogPath);

$totalDevices = 0;
foreach ($config['devices'] as $g => $d) $totalDevices += count($d);
$totalGroups = count($config['groupColors']);
$customGroups = $totalGroups - count($defaultGroupColors);

$groupStats = [];
foreach ($config['groupColors'] as $group => $color) {
    $groupStats[$group] = isset($config['devices'][$group]) ? count($config['devices'][$group]) : 0;
}
arsort($groupStats);

$largestGroup = key($groupStats);
$largestGroupCount = current($groupStats);

$smallestGroup = null;
$smallestGroupCount = PHP_INT_MAX;
foreach ($groupStats as $group => $count) {
    if ($count > 0 && $count < $smallestGroupCount) {
        $smallestGroupCount = $count;
        $smallestGroup = $group;
    }
}

// Собираем все устройства
$allDevices = [];
foreach ($config['devices'] as $group => $list) {
    foreach ($list as $d) {
        $d['group'] = $group;
        $d['color'] = isset($config['groupColors'][$group]) ? $config['groupColors'][$group] : '#888';
        $allDevices[] = $d;
    }
}
usort($allDevices, function($a, $b) { return strcmp($a['name'], $b['name']); });

// Список устройств для статистики
$deviceList = [];
if (!empty($deviceRequests)) {
    $deviceList = array_keys($deviceRequests);
}
if (empty($deviceList) && !empty($allDevices)) {
    foreach ($allDevices as $device) {
        if (isset($device['name']) && is_string($device['name'])) {
            $deviceList[] = $device['name'];
        }
    }
    $deviceList = array_unique($deviceList);
    sort($deviceList);
}
if (empty($deviceList)) {
    $deviceList = ['DESKTOP-RC5D9N0', 'X2-31337', 'AKZ-31337', 'UMA', 'MN-31337'];
}
$deviceList = array_map('strval', $deviceList);

// ============================================
// AJAX ОБРАБОТЧИКИ
// ============================================

$GLOBALS['fullLogPath'] = $fullLogPath;
$GLOBALS['themeCSS'] = $themeCSS;
require_once __DIR__ . '/vlmcinc/ajax.php';

$activeSection = $_GET['section'] ?? 'general';
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? 'success';

// ============================================
// ОБРАБОТКА POST ЗАПРОСОВ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    if (isset($_POST['action'])) {
        
        // Сохранение темы
        if ($_POST['action'] === 'save_theme' && isset($_POST['theme'])) {
            $config['theme'] = $_POST['theme'];
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=general&message=' . urlencode(__('msg_theme_saved')) . '&type=success');
            exit;
        }
        
        // Сохранение языка
        if ($_POST['action'] === 'save_language' && isset($_POST['language'])) {
            $config['language'] = $_POST['language'];
            $translations = loadTranslations($config['language']);
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=general&message=' . urlencode(__('msg_language_saved')) . '&type=success');
            exit;
        }
        
        // Сохранение пути к логу
        if ($_POST['action'] === 'save_log_path' && isset($_POST['logPath'])) {
            $config['logPath'] = $_POST['logPath'];
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=general&message=' . urlencode(__('msg_log_path_saved')) . '&type=success');
            exit;
        }
        
        // Добавление группы
        if ($_POST['action'] === 'add_group' && isset($_POST['groupName']) && isset($_POST['groupColor']) && hasPermission($currentUserPermissions, PERM_GROUPS_EDIT)) {
            $groupName = trim($_POST['groupName']);
            $groupColor = trim($_POST['groupColor']);
            
            if (strlen($groupName) > 50) {
                header('Location: vlmcconf.php?section=groups&message=' . urlencode(__('msg_name_too_long')) . '&type=warning');
                exit;
            }
            if (!preg_match('/^[a-zA-Zа-яА-Я0-9\s\-_]+$/u', $groupName)) {
                header('Location: vlmcconf.php?section=groups&message=' . urlencode(__('msg_invalid_chars')) . '&type=warning');
                exit;
            }
            if (!preg_match('/^#[a-f0-9]{6}$/i', $groupColor)) {
                header('Location: vlmcconf.php?section=groups&message=' . urlencode(__('msg_invalid_color')) . '&type=warning');
                exit;
            }
            
            if (!empty($groupName) && !isset($config['groupColors'][$groupName])) {
                $config['groupColors'][$groupName] = $groupColor;
                $config['devices'][$groupName] = [];
                $messageText = __('msg_group_added') . " '$groupName'";
                $messageType = 'success';
            } else {
                $messageText = __('msg_group_exists');
                $messageType = 'warning';
            }
            
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=groups&message=' . urlencode($messageText) . '&type=' . $messageType);
            exit;
        }
        
        // Удаление группы
        if ($_POST['action'] === 'delete_group' && isset($_POST['groupName']) && hasPermission($currentUserPermissions, PERM_GROUPS_EDIT)) {
            $groupName = $_POST['groupName'];
            
            if (!isset($defaultGroupColors[$groupName]) && isset($config['groupColors'][$groupName])) {
                unset($config['groupColors'][$groupName]);
                unset($config['devices'][$groupName]);
                $messageText = __('msg_group_deleted');
                $messageType = 'success';
            } else {
                $messageText = __('msg_group_std');
                $messageType = 'warning';
            }
            
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=groups&message=' . urlencode($messageText) . '&type=' . $messageType);
            exit;
        }
        
        // Сохранение цветов групп
        if ($_POST['action'] === 'save_group_colors' && hasPermission($currentUserPermissions, PERM_GROUPS_EDIT)) {
            foreach ($config['groupColors'] as $group => $oldColor) {
                if (isset($_POST['color_' . $group])) {
                    $color = trim($_POST['color_' . $group]);
                    if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
                        $config['groupColors'][$group] = $color;
                    }
                }
            }
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=groups&message=' . urlencode(__('msg_colors_saved')) . '&type=success');
            exit;
        }
        
        // Добавление устройства
        if ($_POST['action'] === 'add_device' && isset($_POST['deviceName']) && isset($_POST['deviceGroup']) && hasPermission($currentUserPermissions, PERM_DEVICES_EDIT)) {
            $deviceName = trim($_POST['deviceName']);
            $deviceGroup = $_POST['deviceGroup'];
            $deviceComment = trim($_POST['deviceComment'] ?? '');
            
            if (strlen($deviceName) > 100) {
                header('Location: vlmcconf.php?section=devices&message=' . urlencode(__('msg_name_too_long')) . '&type=warning');
                exit;
            }
            
            if (!empty($deviceName) && isset($config['devices'][$deviceGroup])) {
                $exists = false;
                foreach ($config['devices'][$deviceGroup] as $existing) {
                    if ($existing['name'] === $deviceName) { $exists = true; break; }
                }
                if (!$exists) {
                    $config['devices'][$deviceGroup][] = [
                        'name' => $deviceName,
                        'comment' => $deviceComment,
                        'added' => date('Y-m-d H:i:s'),
                        'added_version' => CONFIG_VERSION
                    ];
                    $messageText = __('msg_device_added');
                    $messageType = 'success';
                } else {
                    $messageText = __('msg_device_exists');
                    $messageType = 'warning';
                }
            } else {
                $messageText = __('msg_invalid_data');
                $messageType = 'warning';
            }
            
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=devices&message=' . urlencode($messageText) . '&type=' . $messageType);
            exit;
        }
        
        // Удаление устройства
        if ($_POST['action'] === 'delete_device' && isset($_POST['deviceName']) && isset($_POST['deviceGroup']) && hasPermission($currentUserPermissions, PERM_DEVICES_EDIT)) {
            $deviceName = $_POST['deviceName'];
            $deviceGroup = $_POST['deviceGroup'];
            
            if (isset($config['devices'][$deviceGroup])) {
                $newList = [];
                foreach ($config['devices'][$deviceGroup] as $device) {
                    if ($device['name'] !== $deviceName) $newList[] = $device;
                }
                $config['devices'][$deviceGroup] = $newList;
                $messageText = __('msg_device_deleted');
                $messageType = 'success';
            } else {
                $messageText = __('msg_device_not_found');
                $messageType = 'warning';
            }
            
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=devices&message=' . urlencode($messageText) . '&type=' . $messageType);
            exit;
        }
        
        // Редактирование устройства
        if ($_POST['action'] === 'edit_device' && isset($_POST['oldName']) && isset($_POST['newName']) && hasPermission($currentUserPermissions, PERM_DEVICES_EDIT)) {
            $oldName = $_POST['oldName'];
            $oldGroup = $_POST['oldGroup'];
            $newName = $_POST['newName'];
            $newGroup = $_POST['newGroup'];
            $newComment = $_POST['newComment'] ?? '';
            
            if (isset($config['devices'][$oldGroup])) {
                $newList = [];
                foreach ($config['devices'][$oldGroup] as $device) {
                    if ($device['name'] !== $oldName) $newList[] = $device;
                }
                $config['devices'][$oldGroup] = $newList;
                
                if (!isset($config['devices'][$newGroup])) $config['devices'][$newGroup] = [];
                $config['devices'][$newGroup][] = [
                    'name' => $newName,
                    'comment' => $newComment,
                    'added' => date('Y-m-d H:i:s'),
                    'edited' => date('Y-m-d H:i:s')
                ];
                $messageText = __('msg_device_updated');
                $messageType = 'success';
            } else {
                $messageText = __('msg_device_not_found');
                $messageType = 'warning';
            }
            
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=devices&message=' . urlencode($messageText) . '&type=' . $messageType);
            exit;
        }
        
        // Сброс настроек
        if ($_POST['action'] === 'reset_config' && $isAdmin) {
            $config = $defaultConfig;
            $config['config_version'] = CONFIG_VERSION;
            $config['config_date'] = CONFIG_DATE;
            $config['last_modified'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: vlmcconf.php?section=info&message=' . urlencode(__('msg_config_reset')) . '&type=warning');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="shortcut icon" href="../pic/favicon.png" type="image/x-icon">
    <meta charset="UTF-8">
    <title>KMS Monitor • <?= __('menu_settings') ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ============================================ */
        /* ВЕСЬ CSS ОСТАЕТСЯ БЕЗ ИЗМЕНЕНИЙ */
        /* ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: <?= $themeCSS['bg'] ?>;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 15px;
            height: 100vh;
            color: <?= $themeCSS['text'] ?>;
            overflow: hidden;
        }
        .container {
            max-width: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            height: 100%;
            padding: 0 15px;
        }
        .header {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            box-shadow: <?= $themeCSS['shadow'] ?>;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            color: <?= $themeCSS['text'] ?>;
        }
        .version-badge {
            background: <?= $themeCSS['input'] ?>;
            color: #8aa0bb;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid <?= $themeCSS['border'] ?>;
        }
        .back-link {
            color: <?= $themeCSS['text'] ?>;
            text-decoration: none;
            background: <?= $themeCSS['input'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            transition: all 0.2s;
        }
        .back-link:hover {
            background: <?= $themeCSS['hover'] ?>;
        }
        .main-content {
            display: flex;
            gap: 15px;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        .settings-menu {
            width: 260px;
            background: <?= $themeCSS['menuBg'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex-shrink: 0;
        }
        .menu-item {
            padding: 12px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            color: <?= $themeCSS['menuText'] ?>;
            height: 44px;
            line-height: 1;
        }
        .menu-item:hover {
            background: <?= $themeCSS['menuHover'] ?>;
        }
        .menu-item.active {
            background: <?= $themeCSS['hover'] ?>;
            border-left: 3px solid <?= $themeCSS['primary'] ?>;
        }
        .menu-item-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        .menu-item.logout-btn {
            margin-top: auto;
            border-top: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 0;
            color: <?= $themeCSS['danger'] ?>;
        }
        .menu-item.logout-btn:hover {
            background: <?= $themeCSS['danger'] ?>20;
            color: <?= $themeCSS['danger'] ?>;
        }
        .settings-content {
            flex: 1;
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 20px;
            overflow-y: auto;
            min-height: 0;
        }
        .settings-section {
            display: none;
            animation: fadeIn 0.3s;
        }
        .settings-section.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid <?= $themeCSS['border'] ?>;
            color: <?= $themeCSS['text'] ?>;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }
        .section-message {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: normal;
            animation: fadeInOut 5s forwards;
            max-width: 400px;
            margin-left: auto;
        }
        .section-message.success {
            background: <?= $themeCSS['success'] ?>20;
            color: <?= $themeCSS['success'] ?>;
            border: 1px solid <?= $themeCSS['success'] ?>;
        }
        .section-message.warning {
            background: <?= $themeCSS['warning'] ?>20;
            color: <?= $themeCSS['warning'] ?>;
            border: 1px solid <?= $themeCSS['warning'] ?>;
        }
        .section-message.error {
            background: <?= $themeCSS['danger'] ?>20;
            color: <?= $themeCSS['danger'] ?>;
            border: 1px solid <?= $themeCSS['danger'] ?>;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-10px); }
            10% { opacity: 1; transform: translateX(0); }
            90% { opacity: 1; transform: translateX(0); }
            100% { opacity: 0; transform: translateX(10px); }
        }
        .settings-card {
            background: <?= $themeCSS['input'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .settings-card-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #8aa0bb;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .form-group {
            margin-bottom: 12px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 4px;
            color: #8aa0bb;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['inputBorder'] ?>;
            border-radius: 6px;
            color: <?= $themeCSS['text'] ?>;
            font-size: 13px;
        }
        .form-control:focus {
            outline: none;
            border-color: <?= $themeCSS['primary'] ?>;
            box-shadow: 0 0 0 2px <?= $themeCSS['primary'] ?>20;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary { background: <?= $themeCSS['primary'] ?>; color: white; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-warning { background: <?= $themeCSS['warning'] ?>; color: white; }
        .btn-danger { background: <?= $themeCSS['danger'] ?>; color: white; }
        .btn-success { background: <?= $themeCSS['success'] ?>; color: white; }
        .btn-small { padding: 4px 8px; font-size: 11px; }
        .theme-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .theme-options {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .theme-option {
            background: <?= $themeCSS['card'] ?>;
            border: 2px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 8px 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
            color: <?= $themeCSS['text'] ?>;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .theme-option:hover {
            transform: translateY(-2px);
            border-color: <?= $themeCSS['primary'] ?>;
        }
        .theme-option.selected {
            border-color: <?= $themeCSS['primary'] ?>;
            background: <?= $themeCSS['hover'] ?>;
        }
        .theme-preview {
            width: 260px;
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s;
        }
        .theme-preview-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #8aa0bb;
            margin-bottom: 10px;
            text-align: center;
        }
        .theme-preview-content {
            background: <?= $themeCSS['bg'] ?>;
            border-radius: 6px;
            padding: 10px;
            transition: all 0.3s;
        }
        .preview-buttons {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .preview-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
            color: white;
            transition: all 0.3s;
        }
        .preview-card {
            background: <?= $themeCSS['card'] ?>;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 11px;
            transition: all 0.3s;
        }
        .preview-muted {
            font-size: 10px;
            color: #8aa0bb;
            transition: all 0.3s;
        }
        .path-preview {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .device-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        .device-filter select {
            width: 200px;
        }
        .device-filter span {
            font-size: 12px;
            color: #8aa0bb;
        }
        .devices-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-height: 450px;
            overflow-y: auto;
            padding-right: 5px;
        }
        .device-item {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 6px;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            min-height: 36px;
        }
        .device-item:hover {
            box-shadow: <?= $themeCSS['shadow'] ?>;
            border-color: <?= $themeCSS['primary'] ?>;
        }
        .device-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            flex-shrink: 0;
        }
        .device-info {
            flex: 2;
            line-height: 1.2;
        }
        .device-name {
            font-weight: 600;
            font-size: 12px;
        }
        .device-comment {
            font-size: 10px;
            color: #8aa0bb;
            margin-top: 2px;
        }
        .device-meta {
            flex: 1;
            font-size: 11px;
            color: #8aa0bb;
        }
        .device-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }
        .edit-device-btn, .delete-device-btn {
            padding: 3px 6px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
            min-width: 24px;
        }
        .edit-device-btn {
            background: <?= $themeCSS['primary'] ?>;
            color: white;
        }
        .delete-device-btn {
            background: <?= $themeCSS['danger'] ?>;
            color: white;
        }
        .log-info-card {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 8px 15px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        .security-layout {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .security-left { flex: 2; }
        .security-right { flex: 1; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .stat-card-value {
            font-size: 28px;
            font-weight: 600;
            color: <?= $themeCSS['primary'] ?>;
            margin-bottom: 4px;
        }
        .stat-card-label {
            font-size: 10px;
            color: #8aa0bb;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stats-detailed {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-block {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 12px;
        }
        .stat-block-title {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #8aa0bb;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stat-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .stat-list-item {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 3px 0;
            border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
        }
        .stat-list-label {
            color: #8aa0bb;
        }
        .stat-list-value {
            font-weight: 600;
        }
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
            max-height: 300px;
            overflow-y: auto;
            padding: 5px;
        }
        .group-card {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .group-color {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            flex-shrink: 0;
        }
        .group-info {
            flex: 1;
        }
        .group-name {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 2px;
            color: <?= $themeCSS['text'] ?>;
        }
        .group-devices-count {
            font-size: 11px;
            color: #8aa0bb;
        }
        .group-actions {
            display: flex;
            gap: 4px;
        }
        .color-picker {
            width: 30px;
            height: 30px;
            padding: 2px;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 4px;
            background: <?= $themeCSS['card'] ?>;
            cursor: pointer;
        }
        .add-group-form {
            display: flex;
            gap: 8px;
            align-items: center;
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }
        .info-list {
            background: <?= $themeCSS['card'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 8px;
            padding: 15px;
        }
        .info-item {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
        }
        .info-item:last-child { border-bottom: none; }
        .info-item-label {
            width: 120px;
            color: #8aa0bb;
            font-size: 12px;
            text-transform: uppercase;
        }
        .info-item-value {
            flex: 1;
            font-size: 13px;
            color: <?= $themeCSS['text'] ?>;
        }
        hr {
            margin: 15px 0;
            border-color: <?= $themeCSS['border'] ?>;
        }
        .footer {
            padding: 8px;
            text-align: center;
            color: #8aa0bb;
            font-size: 11px;
            flex-shrink: 0;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: <?= $themeCSS['card'] ?>;
            padding: 25px;
            border-radius: 12px;
            width: 550px;
            max-width: 90%;
            box-shadow: <?= $themeCSS['shadow'] ?>;
            color: <?= $themeCSS['text'] ?>;
            position: relative;
            animation: modalFadeIn 0.3s;
            border: 2px solid <?= $themeCSS['primary'] ?>;
            z-index: 100001;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid <?= $themeCSS['border'] ?>;
        }
        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: <?= $themeCSS['primary'] ?>;
        }
        .modal-close {
            color: #8aa0bb;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
            line-height: 1;
        }
        .modal-close:hover {
            color: <?= $themeCSS['text'] ?>;
        }
        .modal-body {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .modal-form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .modal-form-group label {
            font-size: 12px;
            font-weight: 500;
            color: #8aa0bb;
            text-transform: uppercase;
        }
        .modal-form-control {
            padding: 8px 12px;
            background: <?= $themeCSS['input'] ?>;
            border: 1px solid <?= $themeCSS['border'] ?>;
            border-radius: 6px;
            color: <?= $themeCSS['text'] ?>;
            font-size: 13px;
        }
        .modal-btn {
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 5px;
        }
        .modal-btn-primary {
            background: <?= $themeCSS['primary'] ?>;
            color: white;
        }
        .modal-message {
            padding: 8px;
            border-radius: 4px;
            font-size: 12px;
            text-align: center;
            display: none;
        }
        .modal-message.success {
            background: <?= $themeCSS['success'] ?>20;
            color: <?= $themeCSS['success'] ?>;
            border: 1px solid <?= $themeCSS['success'] ?>;
        }
        .modal-message.error {
            background: <?= $themeCSS['danger'] ?>20;
            color: <?= $themeCSS['danger'] ?>;
            border: 1px solid <?= $themeCSS['danger'] ?>;
        }
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
            margin-top: 8px;
        }
        .period-selector {
            display: flex;
            gap: 5px;
        }
        .period-btn {
            padding: 3px 8px;
            border: 1px solid <?= $themeCSS['border'] ?>;
            background: <?= $themeCSS['input'] ?>;
            color: <?= $themeCSS['text'] ?>;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10px;
        }
        .period-btn.active {
            background: <?= $themeCSS['primary'] ?>;
            color: white;
            border-color: <?= $themeCSS['primary'] ?>;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: <?= $themeCSS['bg'] ?>; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: <?= $themeCSS['border'] ?>; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: <?= $themeCSS['primary'] ?>; }
        .server-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 30px;
            font-size: 12px;
        }
        .server-info-item {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
        }
		
		/* Стили только для темы Корпоративный синий (only) */
		<?php if ($config['theme'] === 'corporate_blue'): ?>
		.section-title {
			color: #0097dc !important;
		}
		.section-title span {
			color: #0097dc !important;
		}
		.settings-card-title {
			color: #0097dc !important;
		}
		.menu-item span {
			color: #0097dc !important;
		}
		.header h1 {
			color: #0097dc !important;
		}
		<?php endif; ?>
		
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            width: 100%;
            padding: 8px 35px 8px 12px;
            box-sizing: border-box;
        }
        .toggle-password-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #8aa0bb;
            padding: 0;
            margin: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password-btn:hover {
            color: <?= $themeCSS['primary'] ?>;
        }
    </style>
<script>
    // ============================================
    // JAVASCRIPT ФУНКЦИИ
    // ============================================
    
    function saveActiveSection(section) {
        localStorage.setItem('vlmc_active_section', section);
    }
    
    function getActiveSection() {
        return localStorage.getItem('vlmc_active_section') || 'general';
    }
    
    function logout() {
        if (confirm('<?= __('menu_logout_confirm') ?>')) {
            localStorage.removeItem('vlmc_active_section');
            window.location.href = 'logout.php';
        }
    }
    
    function showSection(section, element) {
        saveActiveSection(section);
        document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
        if (element) element.classList.add('active');
        document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
        const selected = document.getElementById('section-' + section);
        if (selected) selected.classList.add('active');
        if (section === 'info') refreshFileInfo();
        if (section === 'stats') {
            setTimeout(function() {
                if (typeof loadTimelineActivity === 'function') {
                    loadTimelineActivity();
                }
            }, 100);
        }
        
        // Удаляем уведомление из DOM при переключении секции
        const sectionMessage = document.querySelector('.section-message');
        if (sectionMessage) {
            sectionMessage.remove();
        }
        
        // Удаляем параметры из URL
        removeMessageParams();
    }
    
    function removeMessageParams() {
        if (window.location.search.includes('message=') || window.location.search.includes('type=')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('message');
            url.searchParams.delete('type');
            window.history.replaceState({}, document.title, url.toString());
        }
    }
    
    function selectThemeAndPreview(themeKey) {
        document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('selected'));
        document.querySelector('.theme-option.' + themeKey).classList.add('selected');
        document.getElementById('selectedTheme').value = themeKey;
        previewTheme(themeKey);
    }
    
    function previewTheme(themeKey) {
        const fd = new FormData();
        fd.append('ajax', 'preview_theme');
        fd.append('theme', themeKey);
        
        fetch('', { method: 'POST', body: fd })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const previewContent = document.getElementById('themePreviewContent');
                    if (previewContent) {
                        previewContent.style.background = data.bg;
                        previewContent.style.color = data.text;
                    }
                    const btnPrimary = document.getElementById('previewBtnPrimary');
                    const btnSuccess = document.getElementById('previewBtnSuccess');
                    const btnDanger = document.getElementById('previewBtnDanger');
                    if (btnPrimary) btnPrimary.style.background = data.primary;
                    if (btnSuccess) btnSuccess.style.background = data.success;
                    if (btnDanger) btnDanger.style.background = data.danger;
                    const previewCard = document.getElementById('previewCard');
                    if (previewCard) previewCard.style.background = data.card;
                    const previewMuted = document.getElementById('previewMuted');
                    if (previewMuted) previewMuted.style.color = data.border;
                    const previewBlock = document.getElementById('themePreview');
                    if (previewBlock) previewBlock.style.borderColor = data.border;
                }
            })
            .catch(e => console.error('Preview error:', e));
    }
    
    function filterDevices() {
        const filter = document.getElementById('deviceGroupFilter');
        if (!filter) return;
        const val = filter.value;
        const devices = document.querySelectorAll('.device-item');
        let cnt = 0;
        devices.forEach(d => {
            if (val === 'all' || d.dataset.group === val) { d.style.display = 'flex'; cnt++; }
            else d.style.display = 'none';
        });
        const cs = document.getElementById('deviceCount');
        if (cs) cs.textContent = `<?= __('showing') ?> ${cnt} <?= __('of') ?> ${devices.length}`;
    }
    
    function editDevice(name, group, comment) {
        document.getElementById('editOldName').value = name;
        document.getElementById('editOldGroup').value = group;
        document.getElementById('editDeviceName').value = name;
        document.getElementById('editDeviceGroup').value = group;
        document.getElementById('editDeviceComment').value = comment || '';
        document.getElementById('editDeviceMessage').style.display = 'none';
        document.getElementById('editDeviceModal').style.display = 'flex';
        return false;
    }
    
    function closeEditModal() {
        document.getElementById('editDeviceModal').style.display = 'none';
    }
    
    function clearLog(type) {
        const sd = document.getElementById('startDate')?.value;
        const ed = document.getElementById('endDate')?.value;
        if (type === 'date_range' && (!sd || !ed)) { alert('<?= __('date_range_required') ?>'); return; }
        if (type === 'date_range' && sd > ed) { alert('<?= __('date_range_invalid') ?>'); return; }
        if (!confirm(type === 'all' ? '<?= __('security_clear_all_confirm') ?>' : `<?= __('security_clear_date_confirm') ?> ${sd} ${ed}?`)) return;
        
        const fd = new FormData();
        fd.append('ajax', 'clear_log');
        fd.append('clearType', type);
        if (type === 'date_range') { fd.append('startDate', sd); fd.append('endDate', ed); }
        
        fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { 
                if (d.success) { 
                    alert(d.message); 
                    location.reload(); 
                } else { 
                    alert(d.message); 
                } 
            })
            .catch(e => { alert('<?= __('msg_save_error') ?>'); });
    }
    
    function backupLog() {
        if (!confirm('<?= __('security_backup_confirm') ?>')) return;
        const fd = new FormData();
        fd.append('ajax', 'backup_log');
        fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => alert(d.message))
            .catch(e => alert('<?= __('msg_backup_error') ?>'));
    }
    
    function refreshFileInfo() {
        document.querySelectorAll('.file-row').forEach(row => {
            const path = row.dataset.path;
            const sizeCell = row.querySelector('.file-size');
            const mtimeCell = row.querySelector('.file-mtime');
            if (!path) return;
            const fd = new FormData();
            fd.append('ajax', 'file_info');
            fd.append('path', path);
            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { 
                    if (d.success) { 
                        if (sizeCell && sizeCell.textContent !== d.size) sizeCell.textContent = d.size; 
                        if (mtimeCell && mtimeCell.textContent !== d.date) mtimeCell.textContent = d.date; 
                    } 
                })
                .catch(e => console.error('File info error:', e));
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const editForm = document.getElementById('editDeviceForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const oldName = document.getElementById('editOldName').value;
                const oldGroup = document.getElementById('editOldGroup').value;
                const newName = document.getElementById('editDeviceName').value.trim();
                const newGroup = document.getElementById('editDeviceGroup').value;
                const comment = document.getElementById('editDeviceComment').value.trim();
                const msgDiv = document.getElementById('editDeviceMessage');
                if (!newName) { 
                    msgDiv.className = 'modal-message error'; 
                    msgDiv.textContent = '<?= __('name_required') ?>'; 
                    msgDiv.style.display = 'block'; 
                    return; 
                }
                this.submit();
            });
        }
        
        window.onclick = function(e) { 
            if (e.target === document.getElementById('editDeviceModal')) closeEditModal();
        };
        
        const urlSection = new URLSearchParams(window.location.search).get('section');
        const sectionToActivate = urlSection || getActiveSection();
        document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
        const target = document.getElementById('section-' + sectionToActivate);
        if (target) target.classList.add('active');
        else document.getElementById('section-general').classList.add('active');
        document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
        document.querySelectorAll('.menu-item').forEach(i => { 
            if (i.getAttribute('onclick')?.includes(sectionToActivate)) i.classList.add('active'); 
        });
        
        filterDevices();
        if (sectionToActivate === 'info') setTimeout(refreshFileInfo, 500);
        
        // Принудительно скрываем все модальные окна
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
        
        // Удаляем параметры уведомлений из URL сразу после загрузки страницы
        removeMessageParams();
    });
    
    window.showSection = showSection;
    window.editDevice = editDevice;
    window.parentEditDevice = editDevice;
    window.removeMessageParams = removeMessageParams;
</script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <h1>⚙️ KMS MONITOR • <?= __('menu_settings') ?></h1>
                <span class="version-badge">v<?= CONFIG_VERSION ?></span>
            </div>
            <a href="../vlmc.php" class="back-link">← <?= __('back') ?></a>
        </div>
        
        <div class="main-content">
            <div class="settings-menu">
                <!-- Общие настройки - доступны всем -->
                <div class="menu-item <?= $activeSection === 'general' ? 'active' : '' ?>" onclick="showSection('general', this)">
                    <span class="menu-item-icon">⚙️</span>
                    <span><?= __('menu_general') ?></span>
                </div>
                
                <!-- Группы - только если есть права на просмотр -->
                <?php if (hasPermission($currentUserPermissions, PERM_GROUPS_VIEW)): ?>
                <div class="menu-item <?= $activeSection === 'groups' ? 'active' : '' ?>" onclick="showSection('groups', this)">
                    <span class="menu-item-icon">👥</span>
                    <span><?= __('menu_groups') ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Устройства - только если есть права на просмотр -->
                <?php if (hasPermission($currentUserPermissions, PERM_DEVICES_VIEW)): ?>
                <div class="menu-item <?= $activeSection === 'devices' ? 'active' : '' ?>" onclick="showSection('devices', this)">
                    <span class="menu-item-icon">📱</span>
                    <span><?= __('menu_devices') ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Безопасность - только если есть права на просмотр логов или пользователей -->
                <?php if (hasPermission($currentUserPermissions, PERM_LOGS_VIEW) || hasPermission($currentUserPermissions, PERM_USERS_VIEW)): ?>
                <div class="menu-item <?= $activeSection === 'security' ? 'active' : '' ?>" onclick="showSection('security', this)">
                    <span class="menu-item-icon">🔒</span>
                    <span><?= __('menu_security') ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Статистика - доступна всем -->
                <div class="menu-item <?= $activeSection === 'stats' ? 'active' : '' ?>" onclick="showSection('stats', this)">
                    <span class="menu-item-icon">📊</span>
                    <span><?= __('menu_stats') ?></span>
                </div>
                
                <!-- Информация - только если есть права на просмотр -->
                <?php if (hasPermission($currentUserPermissions, PERM_INFO_VIEW)): ?>
                <div class="menu-item <?= $activeSection === 'info' ? 'active' : '' ?>" onclick="showSection('info', this)">
                    <span class="menu-item-icon">ℹ️</span>
                    <span><?= __('menu_info') ?></span>
                </div>
				
				<!-- Документация - доступна всем -->
				<div class="menu-item <?= $activeSection === 'documentation' ? 'active' : '' ?>" onclick="showSection('documentation', this)">
					<span class="menu-item-icon">📚</span>
					<span><?= __('menu_documentation') ?></span>
				</div>
				
                <?php endif; ?>
                
                <div style="flex: 1;"></div>
                
                <div class="menu-item logout-btn" onclick="logout()">
                    <span class="menu-item-icon">🚪</span>
                    <span><?= __('menu_logout') ?></span>
                </div>
            </div>
            
            <div class="settings-content">
				<?php include __DIR__ . '/sections/documentation.php'; ?>
                <?php include __DIR__ . '/sections/general.php'; ?>
                <?php if (hasPermission($currentUserPermissions, PERM_GROUPS_VIEW)) include __DIR__ . '/sections/groups.php'; ?>
                <?php if (hasPermission($currentUserPermissions, PERM_DEVICES_VIEW)) include __DIR__ . '/sections/devices.php'; ?>
                <?php if (hasPermission($currentUserPermissions, PERM_LOGS_VIEW) || hasPermission($currentUserPermissions, PERM_USERS_VIEW)) include __DIR__ . '/sections/security.php'; ?>
                <?php include __DIR__ . '/sections/stats.php'; ?>
                <?php if (hasPermission($currentUserPermissions, PERM_INFO_VIEW)) include __DIR__ . '/sections/info.php'; ?>
		    </div>
        </div>
        
        <div class="footer">
            KMS Monitor Configuration • <?= __('info_version') ?> <?= CONFIG_VERSION ?> • <?= __($themeCSS['name']) ?>
        </div>
    </div>
    
    <div id="editDeviceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✎ <?= __('edit') ?></h2>
                <span class="modal-close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="editDeviceMessage" class="modal-message"></div>
                <form id="editDeviceForm" method="POST">
                    <input type="hidden" name="action" value="edit_device">
                    <input type="hidden" name="oldName" id="editOldName">
                    <input type="hidden" name="oldGroup" id="editOldGroup">
                    <div class="modal-form-group">
                        <label><?= __('devices_name') ?></label>
                        <input type="text" name="newName" id="editDeviceName" class="modal-form-control" required>
                    </div>
                    <div class="modal-form-group">
                        <label><?= __('devices_group') ?></label>
                        <select name="newGroup" id="editDeviceGroup" class="modal-form-control">
                            <?php foreach ($config['groupColors'] as $group => $color): ?>
                            <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label><?= __('devices_comment') ?></label>
                        <input type="text" name="newComment" id="editDeviceComment" class="modal-form-control" placeholder="<?= __('devices_comment') ?>">
                    </div>
                    <button type="submit" class="modal-btn modal-btn-primary">💾 <?= __('save') ?></button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</body>
</html>