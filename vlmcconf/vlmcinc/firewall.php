<?php
// ============================================
// ФАЙЛ: vlmcinc/firewall.php
// ВЕРСИЯ: 1.0.1
// ДАТА: 2026-11-18
// @description: Блокировка IP в фаерволе из панели (п. 30): список блокировок, ufw/iptables/nftables через sudo-помощник, netsh на Windows
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';

if (basename($_SERVER['PHP_SELF'] ?? '') === 'firewall.php') {
    http_response_code(403);
    exit('Access denied');
}

const FW_HELPER_DEFAULT = '/usr/local/sbin/vlmc-firewall';
const FW_BACKENDS = ['auto', 'ufw', 'iptables', 'nftables', 'windows', 'none'];

function fwIsWindows() {
    return DIRECTORY_SEPARATOR === '\\';
}

function fwBlockFile() {
    return vlmcDataFile('blocked_ips.json');
}

/** Настройки: backend (auto|ufw|iptables|nftables|windows|none), scope (all|port), port, helper (путь к скрипту). */
function fwSettings($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    $f = isset($config['firewall']) && is_array($config['firewall']) ? $config['firewall'] : [];
    $backend = $f['backend'] ?? 'auto';
    if (!in_array($backend, FW_BACKENDS, true)) {
        $backend = 'auto';
    }
    $scope = (($f['scope'] ?? 'all') === 'port') ? 'port' : 'all';
    $port = (int)($f['port'] ?? 1688);
    if ($port < 1 || $port > 65535) {
        $port = 1688;
    }
    return ['backend' => $backend, 'scope' => $scope, 'port' => $port, 'helper' => FW_HELPER_DEFAULT];
}

/** Путь к sudo-помощнику (для тестов можно подменить переменной VLMC_FIREWALL_HELPER). */
function fwHelperPath() {
    $env = getenv('VLMC_FIREWALL_HELPER');
    return ($env !== false && $env !== '') ? $env : FW_HELPER_DEFAULT;
}

function fwExecAvailable() {
    if (!function_exists('exec')) {
        return false;
    }
    $dis = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array('exec', $dis, true);
}

/** Какой механизм будет использован: ufw/iptables/nftables/windows/none и пригоден ли он. */
function fwStatus($settings = null) {
    $settings = $settings ?: fwSettings();
    $st = [
        'os' => fwIsWindows() ? 'windows' : 'linux',
        'backend' => $settings['backend'],
        'effective' => 'none',
        'ready' => false,
        'problem' => '',      // код проблемы для подсказки в интерфейсе
        'helper' => fwHelperPath(),
    ];
    if (!fwExecAvailable()) {
        $st['problem'] = 'exec_disabled';
        return $st;
    }
    $backend = $settings['backend'];
    if ($backend === 'none') {
        $st['problem'] = 'disabled';
        return $st;
    }
    if (fwIsWindows()) {
        $st['effective'] = 'windows';
        $st['ready'] = true;   // права администратора проверяются при первой блокировке
        return $st;
    }
    // Помощник установлен с правами 750 (root:root), поэтому is_executable() для веб-пользователя всегда «нет».
    // Наличие файла проверяем is_file(), а право запуска — самим вызовом через sudo ниже.
    if (!is_file($st['helper'])) {
        $st['problem'] = 'helper_missing';
        return $st;
    }
    $st['effective'] = $backend === 'auto' ? 'auto' : $backend;
    $out = [];
    $code = 1;
    @exec('sudo -n ' . escapeshellarg($st['helper']) . ' status ' . escapeshellarg($st['effective']) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $st['problem'] = 'sudo_denied';
        $st['detail'] = trim(implode(' ', array_slice($out, 0, 2)));
        return $st;
    }
    // Помощник отвечает строкой «ok <механизм>»
    $line = trim($out[0] ?? '');
    if (preg_match('/^ok (ufw|iptables|nftables)$/', $line, $m)) {
        $st['effective'] = $m[1];
        $st['ready'] = true;
    } else {
        $st['problem'] = 'no_backend';
        $st['detail'] = $line;
    }
    return $st;
}

/** Адреса, которые блокировать нельзя: петля, адрес администратора, адреса из «Белых IP» главной страницы. */
function fwProtectedReason($ip, $config = null) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'invalid';
    }
    if ($ip === '::1' || strpos($ip, '127.') === 0 || $ip === '0.0.0.0') {
        return 'loopback';
    }
    $client = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($client !== '' && $client === $ip) {
        return 'self';
    }
    $srv = $_SERVER['SERVER_ADDR'] ?? '';
    if ($srv !== '' && $srv === $ip) {
        return 'server';
    }
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    if (in_array($ip, $config['whitelist_ips'] ?? [], true)) {
        return 'whitelisted';
    }
    return '';
}

// ---------- список блокировок ----------

function fwList() {
    $r = config_read(fwBlockFile());
    if ($r['status'] === 'ok' && is_array($r['config'])) {
        return array_values($r['config']);
    }
    return [];
}

function fwSaveList(array $list) {
    return save_config(fwBlockFile(), array_values($list));
}

/** Выполняет команду фаервола. Возвращает [успех, текст]. */
function fwRun($action, $ip, $settings) {
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !in_array($action, ['block', 'unblock'], true)) {
        return [false, 'invalid'];
    }
    $port = $settings['scope'] === 'port' ? (int)$settings['port'] : 0;
    $out = [];
    $code = 1;
    if (fwIsWindows()) {
        $name = 'KMS Monitor block ' . $ip;
        if ($action === 'block') {
            $cmd = 'netsh advfirewall firewall add rule name=' . escapeshellarg($name) . ' dir=in action=block remoteip=' . escapeshellarg($ip)
                 . ($port ? ' protocol=TCP localport=' . $port : '') . ' 2>&1';
        } else {
            $cmd = 'netsh advfirewall firewall delete rule name=' . escapeshellarg($name) . ' 2>&1';
        }
    } else {
        $cmd = 'sudo -n ' . escapeshellarg(fwHelperPath()) . ' ' . escapeshellarg($settings['backend']) . ' '
             . $action . ' ' . escapeshellarg($ip) . ($port ? ' ' . $port : '') . ' 2>&1';
    }
    @exec($cmd, $out, $code);
    return [$code === 0, trim(implode("\n", array_slice($out, 0, 5)))];
}

/**
 * Блокирует адрес. Если фаервол недоступен, адрес всё равно записывается в список
 * со статусом «pending» (не применён) — его можно применить позже.
 * Возвращает ['success' => bool, 'code' => строка кода для сообщения, 'applied' => bool].
 */
function fwBlock($ip, $reason, $user) {
    $ip = trim((string)$ip);
    $why = fwProtectedReason($ip);
    if ($why !== '') {
        return ['success' => false, 'code' => 'protected_' . $why, 'applied' => false];
    }
    $file = fwBlockFile();
    config_lock($file);
    $list = fwList();
    foreach ($list as $row) {
        if (($row['ip'] ?? '') === $ip) {
            return ['success' => false, 'code' => 'exists', 'applied' => false];
        }
    }
    $settings = fwSettings();
    $status = fwStatus($settings);
    $applied = false;
    $note = '';
    if ($status['ready']) {
        list($applied, $note) = fwRun('block', $ip, $settings);
    } else {
        $note = $status['problem'];
    }
    $list[] = [
        'ip' => $ip,
        'reason' => mb_substr(trim((string)$reason), 0, 200),
        'user' => (string)$user,
        'time' => date('Y-m-d H:i:s'),
        'status' => $applied ? 'applied' : 'pending',
        'backend' => $applied ? $status['effective'] : '',
        'note' => $applied ? '' : mb_substr($note, 0, 200),
    ];
    if (!fwSaveList($list)) {
        return ['success' => false, 'code' => 'save_error', 'applied' => false];
    }
    return ['success' => true, 'code' => $applied ? 'blocked' : 'listed', 'applied' => $applied];
}

/** Снимает блокировку и удаляет адрес из списка. */
function fwUnblock($ip) {
    $ip = trim((string)$ip);
    $file = fwBlockFile();
    config_lock($file);
    $list = fwList();
    $keep = [];
    $found = null;
    foreach ($list as $row) {
        if (($row['ip'] ?? '') === $ip) {
            $found = $row;
        } else {
            $keep[] = $row;
        }
    }
    if ($found === null) {
        return ['success' => false, 'code' => 'not_found'];
    }
    $settings = fwSettings();
    if (($found['status'] ?? '') === 'applied') {
        list($ok, $note) = fwRun('unblock', $ip, $settings);
        if (!$ok) {
            return ['success' => false, 'code' => 'unblock_failed', 'detail' => $note];
        }
    }
    if (!fwSaveList($keep)) {
        return ['success' => false, 'code' => 'save_error'];
    }
    return ['success' => true, 'code' => 'unblocked'];
}

/** Применяет записи со статусом «pending» (после настройки фаервола); при $all — все (например, после перезагрузки сервера, когда правила iptables сброшены). */
function fwApplyPending($all = false) {
    $file = fwBlockFile();
    config_lock($file);
    $list = fwList();
    $settings = fwSettings();
    $status = fwStatus($settings);
    if (!$status['ready']) {
        return ['success' => false, 'applied' => 0, 'code' => $status['problem']];
    }
    $n = 0;
    foreach ($list as &$row) {
        if ($all || ($row['status'] ?? '') !== 'applied') {
            list($ok, $note) = fwRun('block', $row['ip'], $settings);
            if ($ok) {
                $row['status'] = 'applied';
                $row['backend'] = $status['effective'];
                $row['note'] = '';
                $n++;
            } else {
                $row['note'] = mb_substr($note, 0, 200);
            }
        }
    }
    unset($row);
    fwSaveList($list);
    return ['success' => true, 'applied' => $n, 'code' => 'applied'];
}
