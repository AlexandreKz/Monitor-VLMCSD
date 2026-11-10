<?php
// ============================================
// ФАЙЛ: vlmcinc/config.php
// ВЕРСИЯ: 2.2.0
// ДАТА: 2026-11-07
// @description: Общие функции и утилиты + обновление структуры конфига и миграция
// ============================================

require_once __DIR__ . '/paths.php';

/**
 * Получение даты первого события в логе
 */
function getFirstLogEvent($logFile) {
    if (!file_exists($logFile)) {
        return ['date' => '—', 'event' => 'Файл не найден'];
    }
    
    // Читаем потоково только до первой строки с датой, а не весь лог
    $fh = @fopen($logFile, 'rb');
    if ($fh === false) {
        return ['date' => '—', 'event' => 'Ошибка чтения'];
    }
    
    $firstLine = null;
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line !== '' && preg_match('/\d{4}-\d{2}-\d{2}/', $line)) {
            $firstLine = $line;
            break;
        }
    }
    fclose($fh);
    
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

// ============================================
// БЕЗОПАСНАЯ ЗАГРУЗКА И ЗАПИСЬ КОНФИГУРАЦИИ
// Атомарная запись, резервные копии, блокировка на время «прочитать — изменить — записать»,
// защита от затирания настоящего конфига значениями по умолчанию, журнал изменений.
// ============================================

if (!defined('CONFIG_BACKUP_KEEP')) define('CONFIG_BACKUP_KEEP', 50); // сколько резервных копий хранить

/** Папка резервных копий конфига (рядом с данными: вне веб-папки, если включено хранение данных вне неё). */
function config_backup_dir() {
    $dir = vlmcDataFile('backups') . DIRECTORY_SEPARATOR . 'config';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

function config_backup_files($pattern) {
    $files = glob(config_backup_dir() . DIRECTORY_SEPARATOR . $pattern);
    $files = $files ? $files : [];
    sort($files); // в именах дата и время: порядок хронологический
    return $files;
}

/**
 * Чтение конфига. Возвращает ['status' => ok|missing|empty|corrupt, 'config' => array|null].
 * Если файл пуст или повреждён, чтение повторяется: возможно, другой запрос как раз его записывает.
 */
function config_read($configFile, $retries = 3) {
    if (!is_file($configFile)) {
        return ['status' => 'missing', 'config' => null];
    }
    $status = 'corrupt';
    for ($i = 0; $i <= $retries; $i++) {
        clearstatcache(true, $configFile);
        $raw = @file_get_contents($configFile);
        if ($raw !== false) {
            if (trim($raw) === '') {
                $status = 'empty';
            } else {
                $data = json_decode($raw, true);
                if (is_array($data) && $data) {
                    return ['status' => 'ok', 'config' => $data];
                }
                $status = 'corrupt';
            }
        }
        if ($i < $retries) {
            usleep(60000);
        }
    }
    return ['status' => $status, 'config' => null];
}

/**
 * Эксклюзивная блокировка конфига на время «прочитать — изменить — записать»:
 * запросы, меняющие конфиг, выстраиваются по очереди и не теряют изменения друг друга.
 * Блокировка освобождается при завершении скрипта.
 */
function config_lock($configFile) {
    static $handles = [];
    if (isset($handles[$configFile])) {
        return true;
    }
    $fh = @fopen($configFile . '.lock', 'c');
    if ($fh === false) {
        return false; // блокировку взять нельзя — работаем без неё
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return false;
    }
    $handles[$configFile] = $fh;
    return true;
}

/**
 * Берёт блокировку и подтягивает свежий конфиг поверх загруженного в начале запроса.
 * false — файл есть, но не читается: менять и записывать конфиг нельзя.
 */
function config_refresh(&$config, $configFile) {
    config_lock($configFile);
    $r = config_read($configFile);
    if ($r['status'] === 'ok') {
        $config = is_array($config) ? array_merge($config, $r['config']) : $r['config'];
        return true;
    }
    return $r['status'] === 'missing'; // первый запуск: файла ещё нет
}

/** JSON для записи. Если в названиях есть битые символы UTF-8, подменяем их, а не теряем весь конфиг. */
function config_json($config) {
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
    $json = json_encode($config, $flags);
    if ($json === false && defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $json = json_encode($config, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    return $json;
}

function config_count_devices($config) {
    $n = 0;
    if (is_array($config) && isset($config['devices']) && is_array($config['devices'])) {
        foreach ($config['devices'] as $list) {
            if (is_array($list)) {
                $n += count($list);
            }
        }
    }
    return $n;
}

/** Копия текущего корректного конфига перед перезаписью (только если содержимое изменилось). */
function config_backup($configFile) {
    if (!is_file($configFile)) {
        return;
    }
    $raw = @file_get_contents($configFile);
    if ($raw === false || trim($raw) === '') {
        return;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !$data) {
        return; // битый файл в обычные копии не кладём (см. config_keep_broken)
    }
    $files = config_backup_files('vlmcconf_config.json.*.bak');
    if ($files) {
        $last = end($files);
        if (md5((string)@file_get_contents($last)) === md5($raw)) {
            return;
        }
    }
    $ms = (int)((microtime(true) - floor(microtime(true))) * 1000);
    $target = config_backup_dir() . DIRECTORY_SEPARATOR . 'vlmcconf_config.json.' . date('Ymd-His') . sprintf('-%03d', $ms) . '.bak';
    if (@file_put_contents($target, $raw) === false) {
        return;
    }
    @chmod($target, 0640);
    $files = config_backup_files('vlmcconf_config.json.*.bak');
    while (count($files) > CONFIG_BACKUP_KEEP) {
        @unlink(array_shift($files));
    }
}

/** Сохранить копию повреждённого файла для разбора перед тем, как его заменят. */
function config_keep_broken($configFile) {
    if (!is_file($configFile)) {
        return;
    }
    $raw = @file_get_contents($configFile);
    if ($raw === false || trim($raw) === '') {
        return;
    }
    $files = config_backup_files('vlmcconf_config.json.*.broken');
    if ($files) {
        $last = end($files);
        if (md5((string)@file_get_contents($last)) === md5($raw)) {
            return;
        }
    }
    @file_put_contents(config_backup_dir() . DIRECTORY_SEPARATOR . 'vlmcconf_config.json.' . date('Ymd-His') . '.broken', $raw);
    $files = config_backup_files('vlmcconf_config.json.*.broken');
    while (count($files) > 10) {
        @unlink(array_shift($files));
    }
}

/** Восстановление из последней корректной резервной копии. Возвращает конфиг или null. */
function config_restore_from_backup($configFile) {
    $files = array_reverse(config_backup_files('vlmcconf_config.json.*.bak'));
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        $data = ($raw === false) ? null : json_decode($raw, true);
        if (is_array($data) && $data) {
            config_keep_broken($configFile);
            if (config_write_atomic($configFile, $raw)) {
                return $data;
            }
        }
    }
    return null;
}

/** Атомарная запись: временный файл рядом, затем замена. Права сохраняются (для нового файла 0640). */
function config_write_atomic($configFile, $json) {
    $tmp = $configFile . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    $mode = 0640;
    if (is_file($configFile)) {
        $perms = @fileperms($configFile);
        if ($perms !== false) {
            $mode = $perms & 0777;
        }
    }
    @chmod($tmp, $mode);
    if (@rename($tmp, $configFile)) {
        return true;
    }
    // Windows: замена может не сработать, если файл открыт другим процессом — пишем на месте под блокировкой
    $ok = @file_put_contents($configFile, $json, LOCK_EX) !== false;
    @unlink($tmp);
    return $ok;
}

/** Запись о каждом сохранении конфига: когда, кто, что делал, сколько устройств было и стало (для разбора потерь). */
function config_trace($before, $after, $ok) {
    $log = vlmcDataFile('config_changes.log');
    $b = is_array($before) ? config_count_devices($before) : null;
    $a = config_count_devices($after);
    $ajax = $_POST['ajax'] ?? null;
    if (is_scalar($ajax)) {
        $ctx = (string)$ajax;
    } elseif (isset($_POST['action']) && is_scalar($_POST['action'])) {
        $ctx = 'form:' . $_POST['action'];
    } else {
        $ctx = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'cli'));
    }
    $user = (string)($_SESSION['vlmc_username'] ?? '-');
    $flag = ($b !== null && $a < $b) ? ' SHRINK' : '';
    $line = date('Y-m-d H:i:s') . "\t" . ($ok ? 'OK' : 'FAIL') . "\t"
          . preg_replace('/[\r\n\t]+/', ' ', $user) . "\t" . preg_replace('/[\r\n\t]+/', ' ', $ctx) . "\t"
          . 'devices ' . ($b === null ? '?' : $b) . '->' . $a . $flag . "\n";
    if (is_file($log) && @filesize($log) > 524288) {
        @rename($log, str_replace('.log', '.1.log', $log));
    }
    @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Сохранение конфигурации: никогда не пишет пустоту при ошибке JSON, делает резервную копию,
 * пишет атомарно под блокировкой и оставляет запись в журнале изменений.
 *
 * @return bool
 */
function save_config($configFile, $config) {
    $json = config_json($config);
    if ($json === false) {
        error_log('config: json_encode failed (' . json_last_error_msg() . '), config file left untouched');
        return false;
    }
    config_lock($configFile);
    $existing = is_file($configFile) ? @file_get_contents($configFile) : false;
    if ($existing !== false && $existing === $json) {
        return true; // содержимое не изменилось: ничего не пишем и копий не плодим
    }
    $before = config_read($configFile, 0);
    if ($before['status'] === 'corrupt' || $before['status'] === 'empty') {
        config_keep_broken($configFile);
    } else {
        config_backup($configFile);
    }
    $ok = config_write_atomic($configFile, $json);
    config_trace($before['config'], $config, $ok);
    return $ok;
}

/**
 * Загрузка конфигурации с автоматическим обновлением структуры.
 * Если файл есть, но не читается или повреждён, поверх него ничего не записывается:
 * сначала пробуем восстановить из резервной копии, иначе работаем с настройками по умолчанию
 * в памяти (статус в $GLOBALS['vlmc_config_status']: restored | corrupt).
 *
 * @param string $configFile Путь к файлу конфигурации
 * @param array $defaultConfig Конфигурация по умолчанию
 * @param string $configVersion Текущая версия проекта
 * @return array Загруженная и обновлённая конфигурация
 */
function load_config_with_update($configFile, $defaultConfig, $configVersion) {
    $GLOBALS['vlmc_config_status'] = '';
    $config = $defaultConfig;
    $canAutoSave = true;

    $r = config_read($configFile);
    if ($r['status'] !== 'ok' && $r['status'] !== 'missing') {
        // Под блокировкой повторяем чтение: возможно, файл как раз записывал другой запрос
        config_lock($configFile);
        $r = config_read($configFile, 1);
    }

    if ($r['status'] === 'ok') {
        $config = array_merge($defaultConfig, $r['config']);
    } elseif ($r['status'] !== 'missing') {
        $restored = config_restore_from_backup($configFile);
        if ($restored !== null) {
            $config = array_merge($defaultConfig, $restored);
            $GLOBALS['vlmc_config_status'] = 'restored';
        } else {
            $GLOBALS['vlmc_config_status'] = 'corrupt';
            $canAutoSave = false;
        }
    }

    // Обновляем структуру конфига
    $config = update_config_structure($config, $configVersion);

    // Сохраняем обновлённый конфиг, если были изменения (но не поверх нечитаемого файла)
    static $saved = false;
    if (!$saved && $canAutoSave && isset($GLOBALS['config_updated']) && $GLOBALS['config_updated'] === true) {
        save_config($configFile, $config);
        $saved = true;
    }

    return $config;
}

/**
 * Загрузка конфигурации (обёртка для простоты использования).
 */
function load_config($configFile, $defaultConfig, $configVersion, $saveChanges = true) {
    return load_config_with_update($configFile, $defaultConfig, $configVersion);
}


?>