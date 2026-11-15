<?php
// ============================================
// ФАЙЛ: vlmcinc/safe_json.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-14
// @description: Безопасная запись JSON-файлов данных (настройки, пользователи): атомарно, с блокировкой, копиями и журналом
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'safe_json.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/paths.php';

if (!defined('CONFIG_BACKUP_KEEP')) define('CONFIG_BACKUP_KEEP', 50); // сколько резервных копий хранить

/** Вид файла: users (пользователи) или config (настройки). От него зависят папка копий и журнал. */
function config_file_kind($file) {
    return basename((string)$file) === 'users.json' ? 'users' : 'config';
}

/**
 * Папка резервных копий файла (backups/config или backups/users рядом с данными: вне веб-папки,
 * если включено хранение данных вне неё). В backups/ создаётся .htaccess с запретом доступа.
 */
function config_backup_dir($file = null) {
    $root = vlmcDataFile('backups');
    $dir = $root . DIRECTORY_SEPARATOR . ($file === null ? 'config' : config_file_kind($file));
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $ht = $root . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_dir($root) && is_writable($root) && !file_exists($ht)) {
        @file_put_contents($ht,
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    return $dir;
}

function config_backup_files($file, $suffix) {
    $files = glob(config_backup_dir($file) . DIRECTORY_SEPARATOR . basename($file) . '.*.' . $suffix);
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
    $files = config_backup_files($configFile, 'bak');
    if ($files) {
        $last = end($files);
        if (md5((string)@file_get_contents($last)) === md5($raw)) {
            return;
        }
    }
    $ms = (int)((microtime(true) - floor(microtime(true))) * 1000);
    $target = config_backup_dir($configFile) . DIRECTORY_SEPARATOR . basename($configFile) . '.' . date('Ymd-His') . sprintf('-%03d', $ms) . '.bak';
    if (@file_put_contents($target, $raw) === false) {
        return;
    }
    @chmod($target, 0640);
    $files = config_backup_files($configFile, 'bak');
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
    $files = config_backup_files($configFile, 'broken');
    if ($files) {
        $last = end($files);
        if (md5((string)@file_get_contents($last)) === md5($raw)) {
            return;
        }
    }
    @file_put_contents(config_backup_dir($configFile) . DIRECTORY_SEPARATOR . basename($configFile) . '.' . date('Ymd-His') . '.broken', $raw);
    $files = config_backup_files($configFile, 'broken');
    while (count($files) > 10) {
        @unlink(array_shift($files));
    }
}

/** Восстановление из последней корректной резервной копии. Возвращает конфиг или null. */
function config_restore_from_backup($configFile) {
    $files = array_reverse(config_backup_files($configFile, 'bak'));
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

/**
 * Запись о каждом сохранении: когда, кто, что делал, сколько было и стало (для разбора потерь).
 * Настройки: config_changes.log (число устройств), пользователи: users_changes.log (число пользователей).
 */
function config_trace($file, $before, $after, $ok) {
    $isUsers = config_file_kind($file) === 'users';
    $log = vlmcDataFile($isUsers ? 'users_changes.log' : 'config_changes.log');
    $b = is_array($before) ? ($isUsers ? count($before) : config_count_devices($before)) : null;
    $a = is_array($after) ? ($isUsers ? count($after) : config_count_devices($after)) : 0;
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
          . ($isUsers ? 'users ' : 'devices ') . ($b === null ? '?' : $b) . '->' . $a . $flag . "\n";
    if (is_file($log) && @filesize($log) > 524288) {
        @rename($log, str_replace('.log', '.1.log', $log));
    }
    @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Сохранение JSON-файла (настройки или пользователи): никогда не пишет пустоту при ошибке JSON,
 * делает резервную копию, пишет атомарно под блокировкой и оставляет запись в журнале изменений.
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
    config_trace($configFile, $before['config'], $config, $ok);
    return $ok;
}
