<?php
// ============================================
// ФАЙЛ: vlmcconf/index.php
// ВЕРСИЯ: 2.0.0
// @description: Защита директории, запрет прямого доступа
// ============================================

// Запрещаем прямой доступ к конфигурационным файлам
$requestUri = $_SERVER['REQUEST_URI'];
$protectedExtensions = ['json', 'log', 'ini', 'conf', 'txt', 'bak', 'sql', 'zip', 'tar', 'gz'];
$protectedFiles = ['vlmcconf_config', 'users', 'vlmcsd', '.htaccess', '.htpasswd'];

// Проверка по имени файла
foreach ($protectedFiles as $file) {
    if (strpos($requestUri, $file) !== false) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
}

// Проверка по расширению
foreach ($protectedExtensions as $ext) {
    if (preg_match('/\.' . $ext . '$/', $requestUri)) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
}

// Перенаправляем на страницу входа
header('Location: login.php');
exit;