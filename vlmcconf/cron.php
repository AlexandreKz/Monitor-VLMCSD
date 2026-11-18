<?php
// ============================================
// ФАЙЛ: cron.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-18
// @description: Консольный запуск фоновых задач панели (cron / systemd timer / Планировщик заданий Windows)
// ============================================
//
// Запуск:  php vlmcconf/cron.php          — выполнить просроченные задачи
//          php vlmcconf/cron.php --force  — выполнить все включённые задачи сейчас
// Cron (Ubuntu, от имени пользователя веб-сервера, чтобы права на файлы состояния совпадали), каждые 15 минут:
//   */15 * * * * www-data php /var/www/html/vlmcconf/cron.php >/dev/null 2>&1
// Windows (Планировщик заданий):
//   schtasks /Create /SC MINUTE /MO 15 /TN "KMS Monitor cron" /TR "php \"C:\path\to\vlmcconf\cron.php\"" /F
// Коды завершения: 0 — всё в порядке, 1 — есть задача с ошибкой, 2 — другой запуск уже идёт.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/vlmcinc/paths.php';

// Язык берётся из настроек панели (для текстов результатов и журнала)
$translations = [];
$cfgLang = 'ru';
$cfgRaw = @json_decode((string)@file_get_contents(vlmcConfigFile()), true);
if (is_array($cfgRaw) && in_array($cfgRaw['language'] ?? '', ['ru', 'en'], true)) {
    $cfgLang = $cfgRaw['language'];
}
$translations = @include __DIR__ . '/locale/' . $cfgLang . '.php';
if (!is_array($translations)) {
    $translations = [];
}
function __($key) {
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : $key;
}

require_once __DIR__ . '/vlmcinc/scheduler.php';

$force = in_array('--force', $argv ?? [], true);
if ($force) {
    $res = ['busy' => false, 'ran' => []];
    foreach (array_keys(schedTasks()) as $id) {
        $r = schedRun('cli', $id);
        if (!empty($r['busy'])) { $res['busy'] = true; break; }
        $res['ran'] += $r['ran'];
    }
} else {
    $res = schedRun('cli');
}

if (!empty($res['busy'])) {
    echo 'Another run is in progress' . PHP_EOL;
    exit(2);
}
$failed = 0;
foreach ($res['ran'] as $id => $r) {
    echo ($r['ok'] ? 'OK    ' : 'ERROR ') . $id . ': ' . $r['text'] . ' (' . $r['ms'] . ' ms)' . PHP_EOL;
    if (!$r['ok']) { $failed++; }
}
if (!$res['ran']) {
    echo 'Nothing to run' . PHP_EOL;
}
exit($failed ? 1 : 0);
