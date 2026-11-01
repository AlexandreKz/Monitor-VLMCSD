<?php
// ============================================
// ФАЙЛ: update_check.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-01
// @description: Проверка обновлений из консоли (cron / systemd timer / Планировщик заданий Windows). Только проверка.
// ============================================
//
// Запуск:  php vlmcconf/update_check.php
// Коды завершения: 0 — обновлений нет, 10 — есть обновление, 1 — ошибка проверки.
// Cron (Ubuntu, от имени пользователя веб-сервера, чтобы права на файл состояния совпадали):
//   0 */6 * * * www-data php /var/www/html/vlmcconf/update_check.php >/dev/null 2>&1

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/vlmcinc/paths.php';
require_once __DIR__ . '/vlmcinc/updater.php';

$updater = new Updater();
$result = $updater->checkAll(true);
$state = $result['state'];

echo 'Installed version: ' . $updater->getCurrentVersion() . PHP_EOL;

$available = false;
$errors = 0;
foreach (Updater::CHANNELS as $channel) {
    $r = $state[$channel] ?? [];
    if (empty($r['success'])) {
        $errors++;
        echo strtoupper($channel) . ': ERROR ' . ($r['error'] ?? 'unknown') . PHP_EOL;
        continue;
    }
    echo strtoupper($channel) . ': '
        . ($r['available'] ? 'UPDATE AVAILABLE' : 'up to date')
        . ' (new files: ' . $r['new_count'] . ', changed: ' . $r['changed_count'] . ')'
        . ($r['version'] !== '' ? ', version ' . $r['version'] : ', revision ' . substr($r['commit'], 0, 7)) . PHP_EOL;
    if ($r['available']) {
        $available = true;
    }
}

exit($errors === count(Updater::CHANNELS) ? 1 : ($available ? 10 : 0));
