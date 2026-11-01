<?php
// ============================================
// ФАЙЛ: vlmcinc/paths.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-01
// @description: Расположение файлов данных (users.json, vlmcconf_config.json) вне веб-папки, автоперенос, откат на старое место
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'paths.php') {
    http_response_code(403);
    exit('Access denied');
}

if (!defined('VLMC_DATA_FILES')) {
    define('VLMC_DATA_FILES', serialize(['users.json', 'vlmcconf_config.json']));
}

/** Старое место файлов данных: папка vlmcconf/ (внутри веб-папки). */
function vlmcLegacyDir() {
    return dirname(__DIR__);
}

function vlmcLegacyFile($name) {
    return vlmcLegacyDir() . DIRECTORY_SEPARATOR . $name;
}

/** Место по умолчанию: Ubuntu — /var/lib/vlmc-monitor, Windows — %PROGRAMDATA%\vlmc-monitor. */
function vlmcDefaultDataDir() {
    if (DIRECTORY_SEPARATOR === '\\') {
        $base = getenv('PROGRAMDATA');
        if (!$base) {
            $base = 'C:\\ProgramData';
        }
        return rtrim($base, '\\/') . '\\vlmc-monitor';
    }
    return '/var/lib/vlmc-monitor';
}

/**
 * Явно заданная папка данных: переменная окружения VLMC_DATA_DIR
 * (Apache SetEnv попадает в $_SERVER, PHP-FPM env[] — в getenv) или файл
 * vlmcconf/data_dir.local.php, возвращающий строку с путём (в репозитории его нет,
 * поэтому обновления панели его не затрагивают).
 * @return array|null [источник, путь]
 */
function vlmcConfiguredDataDir() {
    $env = getenv('VLMC_DATA_DIR');
    if ($env === false || trim((string)$env) === '') {
        $env = $_SERVER['VLMC_DATA_DIR'] ?? ($_ENV['VLMC_DATA_DIR'] ?? '');
    }
    if (is_string($env) && trim($env) !== '') {
        return ['env', rtrim(trim($env), '/\\')];
    }

    $local = vlmcLegacyDir() . DIRECTORY_SEPARATOR . 'data_dir.local.php';
    if (is_file($local)) {
        $value = @include $local;
        if (is_string($value) && trim($value) !== '') {
            return ['local', rtrim(trim($value), '/\\')];
        }
    }
    return null;
}

/**
 * Можно ли использовать папку. Пишем — да. Только читаем — да, но лишь если данные уже
 * лежат в ней (иначе при сбое прав панель «потеряла» бы пользователей и откатилась на старое место).
 */
function vlmcDataDirUsable($dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (!is_dir($dir)) {
        return false;
    }
    if (is_writable($dir)) {
        return true;
    }
    return is_readable($dir . DIRECTORY_SEPARATOR . 'users.json');
}

/** Если папка данных всё же оказалась внутри веб-папки — закрыть её для веб-сервера (Apache). */
function vlmcDataProtectInsideRoot($dir) {
    $real = realpath($dir);
    $root = realpath(dirname(__DIR__, 2));
    if ($real === false || $root === false || !is_writable($dir)) {
        return;
    }
    $a = $real;
    $b = $root;
    if (DIRECTORY_SEPARATOR === '\\') {
        $a = strtolower(str_replace('\\', '/', $a));
        $b = strtolower(str_replace('\\', '/', $b));
    }
    $a = rtrim($a, '/');
    $b = rtrim($b, '/');
    if ($a === $b || strpos($a, $b . '/') === 0) {
        $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
    }
}

/**
 * Перенос файлов из старого места в папку данных: копия -> проверка -> копия «до переноса»
 * в backups/ -> удаление старого файла. Если в новой папке файл уже есть, старый не трогаем
 * (попадает в stale: администратору показывается предупреждение).
 */
function vlmcDataMigrate(array &$info) {
    $dir = $info['dir'];
    $todo = [];
    foreach (unserialize(VLMC_DATA_FILES) as $name) {
        if (is_file(vlmcLegacyFile($name))) {
            $todo[] = $name;
        }
    }
    if (!$todo) {
        return;
    }
    if (!is_writable($dir)) {
        $info['stale'] = $todo;
        return;
    }

    $lock = @fopen($dir . DIRECTORY_SEPARATOR . '.migrate.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        $info['stale'] = $todo;
        return;
    }

    foreach ($todo as $name) {
        $old = vlmcLegacyFile($name);
        $new = $dir . DIRECTORY_SEPARATOR . $name;

        if (!is_file($old)) {
            continue; // уже перенесён параллельным запросом
        }
        if (file_exists($new)) {
            $info['stale'][] = $name;
            continue;
        }

        $data = @file_get_contents($old);
        if ($data === false) {
            $info['stale'][] = $name;
            continue;
        }
        json_decode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('vlmc paths: ' . $old . ' is not valid JSON, not migrated');
            $info['stale'][] = $name;
            continue;
        }

        $tmp = $new . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $data, LOCK_EX) === false || !@rename($tmp, $new)) {
            @unlink($tmp);
            $info['stale'][] = $name;
            continue;
        }
        if (@file_get_contents($new) !== $data) {
            @unlink($new);
            $info['stale'][] = $name;
            continue;
        }
        @chmod($new, 0640);

        // Копия «до переноса» для отката — тоже вне веб-папки
        $backups = $dir . DIRECTORY_SEPARATOR . 'backups';
        @mkdir($backups, 0750, true);
        @file_put_contents($backups . DIRECTORY_SEPARATOR . $name . '.pre-migration-' . date('Ymd-His'), $data);

        if (!@unlink($old)) {
            error_log('vlmc paths: cannot remove old file ' . $old);
            $info['stale'][] = $name;
        }
    }

    flock($lock, LOCK_UN);
    fclose($lock);
}

/**
 * Где сейчас лежат данные. Порядок: явно заданная папка -> папка ОС по умолчанию ->
 * старое место (vlmcconf/). Если выбранная папка недоступна — работаем по-старому
 * и сообщаем причину (панель при этом не ломается).
 * @return array dir, legacy, source (env|local|default|legacy), reason, default_dir, stale
 */
function vlmcDataInfo() {
    static $info = null;
    if ($info !== null) {
        return $info;
    }

    $default = vlmcDefaultDataDir();
    $info = [
        'dir'         => vlmcLegacyDir(),
        'legacy'      => true,
        'source'      => 'legacy',
        'reason'      => null,
        'default_dir' => $default,
        'stale'       => []
    ];

    $configured = vlmcConfiguredDataDir();
    if ($configured !== null) {
        if (vlmcDataDirUsable($configured[1])) {
            $info['dir'] = $configured[1];
            $info['legacy'] = false;
            $info['source'] = $configured[0];
        } else {
            $info['reason'] = 'configured_unusable';
        }
    } elseif (vlmcDataDirUsable($default)) {
        $info['dir'] = $default;
        $info['legacy'] = false;
        $info['source'] = 'default';
    } else {
        $info['reason'] = 'default_unusable';
    }

    if (!$info['legacy']) {
        vlmcDataProtectInsideRoot($info['dir']);
        vlmcDataMigrate($info);
    }
    return $info;
}

function vlmcDataDir() {
    return vlmcDataInfo()['dir'];
}

function vlmcDataFile($name) {
    return vlmcDataInfo()['dir'] . DIRECTORY_SEPARATOR . $name;
}

function vlmcUsersFile() {
    return vlmcDataFile('users.json');
}

function vlmcConfigFile() {
    return vlmcDataFile('vlmcconf_config.json');
}

/** Состояние для предупреждения в панели. */
function vlmcDataStatus() {
    return vlmcDataInfo();
}

/** Команда создания папки данных для текущей ОС (показывается в предупреждении). */
function vlmcDataSetupHint($dir) {
    if (DIRECTORY_SEPARATOR === '\\') {
        return 'mkdir "' . $dir . '"';
    }
    $user = 'www-data';
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $pw = @posix_getpwuid(posix_geteuid());
        if (is_array($pw) && !empty($pw['name'])) {
            $user = $pw['name'];
        }
    }
    return 'sudo install -d -o ' . $user . ' -g ' . $user . ' -m 750 ' . $dir;
}
