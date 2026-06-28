<?php
// ============================================
// ФАЙЛ: vlmcinc/config.php
// ВЕРСИЯ: 2.1.0
// ДАТА: 2026-06-04
// @description: Общие функции и утилиты + обновление структуры конфига и миграция
// ============================================

/**
 * Получение даты первого события в логе
 */
function getFirstLogEvent($logFile) {
    if (!file_exists($logFile)) {
        return ['date' => '—', 'event' => 'Файл не найден'];
    }
    
    $content = file_get_contents($logFile);
    if ($content === false) {
        return ['date' => '—', 'event' => 'Ошибка чтения'];
    }
    
    $lines = explode("\n", $content);
    $firstLine = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && preg_match('/\d{4}-\d{2}-\d{2}/', $line)) {
            $firstLine = $line;
            break;
        }
    }
    
    if (!$firstLine) {
        return ['date' => '—', 'event' => 'Лог пуст'];
    }
    
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $firstLine, $matches)) {
        return [
            'date' => $matches[1],
            'event' => $firstLine
        ];
    }
    
    return ['date' => 'Дата не найдена', 'event' => $firstLine];
}

/**
 * Форматирование размера файла
 */
function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

/**
 * Глобальная функция перевода
 */
function __($key) {
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : $key;
}

/**
 * Автоматическая миграция формата users.json из старого в новый
 * 
 * @param string $usersFile Путь к файлу users.json
 * @param int $adminFullPermissions Значение PERM_ADMIN_FULL
 * @return bool Были ли изменения
 */
function migrate_users_format($usersFile, $adminFullPermissions = 16383) {
    if (!file_exists($usersFile)) {
        return false;
    }
    
    $data = json_decode(file_get_contents($usersFile), true);
    if (!is_array($data)) {
        return false;
    }
    
    // Проверяем старый формат: {"users": [...]}
    if (isset($data['users']) && is_array($data['users'])) {
        $users = $data['users'];
        $migrated = false;
        
        // Обновляем права для каждого пользователя
        foreach ($users as &$user) {
            // Обновляем permissions со старого значения 4095 на новое
            if (isset($user['permissions']) && $user['permissions'] === 4095) {
                $user['permissions'] = $adminFullPermissions;
                $migrated = true;
            }
            // Добавляем source если нет
            if (!isset($user['source'])) {
                $user['source'] = 'local';
                $migrated = true;
            }
            // Добавляем created если нет
            if (!isset($user['created'])) {
                $user['created'] = date('Y-m-d H:i:s');
                $migrated = true;
            }
        }
        
        // Сохраняем в новом формате (прямой массив)
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    // Проверяем и обновляем права в новом формате (если нужно)
    if (isset($data[0]) && is_array($data[0])) {
        $updated = false;
        foreach ($data as &$user) {
            if (isset($user['permissions']) && $user['permissions'] === 4095) {
                $user['permissions'] = $adminFullPermissions;
                $updated = true;
            }
            if (!isset($user['source'])) {
                $user['source'] = 'local';
                $updated = true;
            }
            if (!isset($user['created']) && isset($user['last_login'])) {
                $user['created'] = $user['last_login'];
                $updated = true;
            }
        }
        if ($updated) {
            file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return true;
        }
    }
    
    return false;
}

/**
 * Миграция groupColors из старого формата в новый (с иконками)
 * Было: "groupColors": {"Домашние": "#2ecc71"}
 * Стало: "groupColors": {"Домашние": {"color": "#2ecc71", "icon": "🏠"}}
 * 
 * @param array &$config Конфигурация (передаётся по ссылке для изменения)
 * @return bool Были ли изменения
 */
function migrate_group_colors(&$config) {
    $defaultIcons = [
        'Домашние' => '🏠',
        'Рабочие' => '💼',
        'Знакомые' => '👥',
        'Клиенты' => '🤝'
    ];
    
    $updated = false;
    
    if (!isset($config['groupColors']) || !is_array($config['groupColors'])) {
        return false;
    }
    
    foreach ($config['groupColors'] as $group => $value) {
        // Если значение строка (старый формат) — мигрируем
        if (is_string($value)) {
            $config['groupColors'][$group] = [
                'color' => $value,
                'icon' => $defaultIcons[$group] ?? '📁'
            ];
            $updated = true;
        }
        // Если уже новый формат, но нет иконки — добавляем
        elseif (is_array($value) && !isset($value['icon'])) {
            $config['groupColors'][$group]['icon'] = $defaultIcons[$group] ?? '📁';
            $updated = true;
        }
    }
    
    return $updated;
}

/**
 * Обновление структуры конфигурации до актуальной версии
 * Без потери существующих данных
 * 
 * @param array $config Текущая конфигурация
 * @param string $configVersion Текущая версия проекта
 * @return array Обновлённая конфигурация
 */
function update_config_structure($config, $configVersion) {
    $updated = false;
    
    // ============================================
    // СЕКЦИЯ: integrations (AD + Webhook)
    // ============================================
    if (!isset($config['integrations'])) {
        $config['integrations'] = [
            'ad' => [
                'enabled' => false,
                'auth_enabled' => false,
                'server' => '',
                'port' => 389,
                'use_ssl' => false,
                'base_dn' => '',
                'domain' => '',
                'service_user' => '',
                'service_password' => ''
            ],
            'webhook' => [
                'enabled' => false,
                'url' => '',
                'secret' => '',
                'events' => ['suspicious_ip', 'mass_activation']
            ]
        ];
        $updated = true;
    } else {
        // Проверяем наличие подсекции ad
        if (!isset($config['integrations']['ad'])) {
            $config['integrations']['ad'] = [
                'enabled' => false,
                'auth_enabled' => false,
                'server' => '',
                'port' => 389,
                'use_ssl' => false,
                'base_dn' => '',
                'domain' => '',
                'service_user' => '',
                'service_password' => ''
            ];
            $updated = true;
        } else {
            // Проверяем наличие поля auth_enabled (добавлено в версии 5.0.0)
            if (!isset($config['integrations']['ad']['auth_enabled'])) {
                $config['integrations']['ad']['auth_enabled'] = false;
                $updated = true;
            }
        }
        
        // Проверяем наличие подсекции webhook
        if (!isset($config['integrations']['webhook'])) {
            $config['integrations']['webhook'] = [
                'enabled' => false,
                'url' => '',
                'secret' => '',
                'events' => ['suspicious_ip', 'mass_activation']
            ];
            $updated = true;
        }
    }
    
    // ============================================
    // СЕКЦИЯ: api
    // ============================================
    if (!isset($config['api'])) {
        $config['api'] = [
            'enabled' => false,
            'base_path' => '/api/v1',
            'cors_origins' => ['*'],
            'rate_limit' => 60,
            'keys' => []
        ];
        $updated = true;
    }
    
    // ============================================
    // СЕКЦИЯ: whitelist_ips (если нет)
    // ============================================
    if (!isset($config['whitelist_ips'])) {
        $config['whitelist_ips'] = [];
        $updated = true;
    }
    
    // ============================================
    // МИГРАЦИЯ groupColors (добавление иконок)
    // ============================================
    if (migrate_group_colors($config)) {
        $updated = true;
    }
    
    // ============================================
    // ОБНОВЛЯЕМ ВЕРСИЮ КОНФИГА
    // ============================================
    if ($updated) {
        $config['config_version'] = $configVersion;
        $config['config_date'] = date('Y-m-d');
        $config['last_modified'] = date('Y-m-d H:i:s');
        $config['last_modified_version'] = $configVersion;
        $GLOBALS['config_updated'] = true;
    }
    
    return $config;
}

/**
 * Загрузка конфигурации с автоматическим обновлением структуры
 * 
 * @param string $configFile Путь к файлу конфигурации
 * @param array $defaultConfig Конфигурация по умолчанию
 * @param string $configVersion Текущая версия проекта
 * @return array Загруженная и обновлённая конфигурация
 */
function load_config_with_update($configFile, $defaultConfig, $configVersion) {
    $config = $defaultConfig;
    
    if (file_exists($configFile)) {
        $loaded = json_decode(file_get_contents($configFile), true);
        if ($loaded && is_array($loaded)) {
            $config = array_merge($defaultConfig, $loaded);
        }
    }
    
    // Обновляем структуру конфига
    $config = update_config_structure($config, $configVersion);
    
    // Сохраняем обновлённый конфиг, если были изменения
    static $saved = false;
    if (!$saved && isset($GLOBALS['config_updated']) && $GLOBALS['config_updated'] === true) {
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $saved = true;
    }
    
    return $config;
}

/**
 * Загрузка конфигурации с сохранением (обёртка для простоты использования)
 * 
 * @param string $configFile Путь к файлу конфигурации
 * @param array $defaultConfig Конфигурация по умолчанию
 * @param string $configVersion Текущая версия проекта
 * @param bool $saveChanges Сохранять ли изменения автоматически
 * @return array Загруженная и обновлённая конфигурация
 */
function load_config($configFile, $defaultConfig, $configVersion, $saveChanges = true) {
    $config = $defaultConfig;
    
    if (file_exists($configFile)) {
        $loaded = json_decode(file_get_contents($configFile), true);
        if ($loaded && is_array($loaded)) {
            $config = array_merge($defaultConfig, $loaded);
        }
    }
    
    $config = update_config_structure($config, $configVersion);
    
    if ($saveChanges) {
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    return $config;
}

/**
 * Сохранение конфигурации
 * 
 * @param string $configFile Путь к файлу конфигурации
 * @param array $config Конфигурация для сохранения
 * @return bool
 */
function save_config($configFile, $config) {
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
?>