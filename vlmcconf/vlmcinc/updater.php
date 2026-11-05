<?php
// ============================================
// ФАЙЛ: vlmcinc/updater.php
// ВЕРСИЯ: 3.0.2
// ДАТА: 2026-11-03
// @description: Обновление проекта с GitHub: каналы release/early, проверка по git-SHA, автопроверка, установка с откатом
// @description: Project update from GitHub: release/early channels, git-SHA comparison, auto-check, install with rollback
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/http_client.php';

class Updater {

    const REPO   = 'AlexandreKz/Monitor-VLMCSD';
    const BRANCH = 'main';
    const API    = 'https://api.github.com';
    const RAW    = 'https://raw.githubusercontent.com';

    /** Каналы: release — последний опубликованный релиз, early — ветка main (ранний доступ). */
    const CHANNELS = ['release', 'early'];

    private $baseDir;

    /** Пути репозитория, которые не обновляются (папки pic/ и screen/ — по решению владельца проекта). */
    private $excludes = [
        '.git', '.gitignore', '.github', 'README.md', 'LICENSE',
        'backups', 'cache', 'screen', 'pic',
        'vlmcconf/cache', 'vlmcconf/backups',
        'vlmcconf/data_dir.local.php', 'vlmcconf/users.json', 'vlmcconf/vlmcconf_config.json'
    ];

    private $lockHandle = null;

    public function __construct() {
        $this->baseDir = dirname(__DIR__, 2);
    }

    // ============================================
    // НАСТРОЙКИ И СОСТОЯНИЕ
    // ============================================

    /** Установленная версия панели (CONFIG_VERSION; в консоли — разбор vlmcconf.php). */
    public function getCurrentVersion() {
        if (defined('CONFIG_VERSION')) {
            return CONFIG_VERSION;
        }
        return $this->readVersionFromFile();
    }

    private function readVersionFromFile() {
        // Версия проекта задаётся в vlmcinc/version.php (старые установки: CONFIG_VERSION в vlmcconf.php)
        foreach (['/vlmcconf/vlmcinc/version.php' => "/define\\('VLMC_VERSION',\\s*'([^']+)'/",
                  '/vlmcconf/vlmcconf.php'        => "/define\\('CONFIG_VERSION',\\s*'([^']+)'/"] as $rel => $regex) {
            $file = $this->baseDir . $rel;
            $src = is_file($file) ? (string)file_get_contents($file) : '';
            if (preg_match($regex, $src, $m)) {
                return $m[1];
            }
        }
        return '0.0.0';
    }

    /** Настройки обновления из конфига: канал, автопроверка, интервал (часы). */
    public function getSettings() {
        $config = [];
        $file = vlmcConfigFile();
        if (is_file($file)) {
            $loaded = json_decode((string)file_get_contents($file), true);
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }
        $u = is_array($config['update'] ?? null) ? $config['update'] : [];

        $type = $u['type'] ?? 'release';
        if (!in_array($type, self::CHANNELS, true)) {
            $type = 'release';
        }
        $interval = (int)($u['check_interval_hours'] ?? 12);
        if (!in_array($interval, [1, 3, 6, 12, 24, 72], true)) {
            $interval = 12;
        }
        return [
            'type'       => $type,
            'auto_check' => array_key_exists('auto_check', $u) ? (bool)$u['auto_check'] : true,
            'interval'   => $interval
        ];
    }

    private function statePath() {
        return vlmcDataFile('update_state.json');
    }

    /** Результаты последней проверки обоих каналов и сведения об установке. */
    public function loadState() {
        $file = $this->statePath();
        if (is_file($file)) {
            $state = json_decode((string)@file_get_contents($file), true);
            if (is_array($state)) {
                return $state;
            }
        }
        return [];
    }

    private function saveState(array $state) {
        $file = $this->statePath();
        $json = json_encode($state, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) !== false && !@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    public function isStale(array $state, $hours) {
        return (time() - (int)($state['checked_at'] ?? 0)) >= ((int)$hours * 3600);
    }

    /** Что показать в уведомлении: версия релиза и число файлов раннего доступа. */
    public function bannerData(array $state) {
        $rel = is_array($state['release'] ?? null) ? $state['release'] : [];
        $early = is_array($state['early'] ?? null) ? $state['early'] : [];
        return [
            'release' => !empty($rel['available']) ? (string)($rel['version'] ?? '') : '',
            'early'   => !empty($early['available']) ? (int)($early['new_count'] ?? 0) + (int)($early['changed_count'] ?? 0) : 0
        ];
    }

    // ============================================
    // ЗАПРОСЫ К GITHUB
    // ============================================

    private function github($path) {
        $r = vlmcHttpRequest(self::API . $path, [
            'timeout'         => 15,
            'connect_timeout' => 5,
            'user_agent'      => 'KMS-Monitor-Updater/3.0',
            'headers'         => ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28']
        ]);
        if ($r['error'] !== '') {
            return ['ok' => false, 'error' => $r['error'], 'detail' => $r['detail']];
        }
        if ($r['status'] === 429 || ($r['status'] === 403 && ($r['headers']['x-ratelimit-remaining'] ?? '') === '0')) {
            return ['ok' => false, 'error' => 'rate_limit', 'reset' => (int)($r['headers']['x-ratelimit-reset'] ?? 0)];
        }
        if ($r['status'] === 404) {
            return ['ok' => false, 'error' => 'not_found', 'status' => 404];
        }
        if (!$r['ok']) {
            return ['ok' => false, 'error' => 'http_error', 'status' => $r['status']];
        }
        $data = json_decode($r['body'], true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'bad_response'];
        }
        return ['ok' => true, 'data' => $data, 'headers' => $r['headers']];
    }

    /** Проверка связи с GitHub (запрос лимитов API не расходует лимит) и SSL-настроек. */
    public function checkGitHubAccess() {
        $r = $this->github('/rate_limit');
        $ssl = vlmcSslDiagnostics();
        if (!$r['ok']) {
            return ['success' => false, 'error' => $r['error'], 'detail' => $r['detail'] ?? '',
                    'status' => $r['status'] ?? 0, 'reset' => $r['reset'] ?? 0, 'ssl' => $ssl];
        }
        $core = $r['data']['resources']['core'] ?? [];
        return ['success' => true, 'remaining' => (int)($core['remaining'] ?? 0), 'limit' => (int)($core['limit'] ?? 0),
                'reset' => (int)($core['reset'] ?? 0), 'ssl' => $ssl];
    }

    private function versionFromTag($tag) {
        if (preg_match('/(\d+\.\d+\.\d+(?:\.\d+)?)/', $tag, $m)) {
            return $m[1];
        }
        return ltrim($tag, 'vV');
    }

    /** Определяет конкретную ревизию канала: тег последнего релиза или ветку main -> коммит и дерево. */
    private function resolveChannel($channel) {
        $version = '';
        $published = '';
        if ($channel === 'release') {
            $rel = $this->github('/repos/' . self::REPO . '/releases/latest');
            if (!$rel['ok']) {
                if ($rel['error'] === 'not_found') {
                    $rel['error'] = 'no_releases';
                }
                return $rel;
            }
            $ref = (string)($rel['data']['tag_name'] ?? '');
            if ($ref === '') {
                return ['ok' => false, 'error' => 'bad_response'];
            }
            $version = $this->versionFromTag($ref);
            $published = (string)($rel['data']['published_at'] ?? '');
        } else {
            $ref = self::BRANCH;
        }

        $c = $this->github('/repos/' . self::REPO . '/commits/' . rawurlencode($ref));
        if (!$c['ok']) {
            return $c;
        }
        $commit = (string)($c['data']['sha'] ?? '');
        $tree = (string)($c['data']['commit']['tree']['sha'] ?? '');
        if (!preg_match('/^[0-9a-f]{40}$/', $commit) || !preg_match('/^[0-9a-f]{40}$/', $tree)) {
            return ['ok' => false, 'error' => 'bad_response'];
        }
        $message = (string)($c['data']['commit']['message'] ?? '');
        return [
            'ok' => true, 'channel' => $channel, 'ref' => $ref, 'version' => $version,
            'commit' => $commit, 'tree' => $tree, 'published_at' => $published,
            'committed_at' => (string)($c['data']['commit']['committer']['date'] ?? ''),
            'subject' => trim(strtok($message, "\n"))
        ];
    }

    /** Список файлов дерева (одним запросом) с их git-SHA, без исключений и небезопасных путей. */
    private function getTree($treeSha) {
        $t = $this->github('/repos/' . self::REPO . '/git/trees/' . $treeSha . '?recursive=1');
        if (!$t['ok']) {
            return $t;
        }
        if (!empty($t['data']['truncated'])) {
            return ['ok' => false, 'error' => 'tree_truncated'];
        }
        $files = [];
        foreach ((array)($t['data']['tree'] ?? []) as $item) {
            if (($item['type'] ?? '') !== 'blob') {
                continue;
            }
            $path = (string)($item['path'] ?? '');
            $sha = (string)($item['sha'] ?? '');
            if ($path === '' || !preg_match('/^[0-9a-f]{40}$/', $sha) || $this->isExcluded($path) || !$this->isSafePath($path)) {
                continue;
            }
            $files[$path] = ['sha' => $sha, 'size' => (int)($item['size'] ?? 0)];
        }
        return ['ok' => true, 'files' => $files];
    }

    private function isExcluded($path) {
        foreach ($this->excludes as $ex) {
            if ($path === $ex || strpos($path, $ex . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    /** Путь из репозитория допустим: без «..», абсолютных и необычных символов. */
    private function isSafePath($path) {
        if ($path === '' || strlen($path) > 255 || $path[0] === '/' || strpos($path, '\\') !== false || strpos($path, "\0") !== false) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || !preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                return false;
            }
        }
        return true;
    }

    // ============================================
    // СРАВНЕНИЕ ФАЙЛОВ ПО git-SHA
    // ============================================

    private function blobSha($content) {
        return sha1('blob ' . strlen($content) . "\0" . $content);
    }

    /**
     * Совпадает ли локальный файл с версией в репозитории. Сравнивается git-SHA содержимого:
     * как есть, с переводами строк LF и с CRLF (на Windows git мог поменять окончания строк).
     */
    private function sameAsRemote($localPath, $sha) {
        $content = @file_get_contents($localPath);
        if ($content === false) {
            return false;
        }
        $lf = str_replace("\r\n", "\n", $content);
        $crlf = str_replace("\n", "\r\n", $lf);
        foreach ([$content, $lf, $crlf] as $variant) {
            if ($this->blobSha($variant) === $sha) {
                return true;
            }
        }
        return false;
    }

    private function computeDiff(array $files) {
        $new = [];
        $changed = [];
        foreach ($files as $path => $info) {
            $local = $this->baseDir . '/' . $path;
            if (!is_file($local)) {
                $new[] = $path;
            } elseif (!$this->sameAsRemote($local, $info['sha'])) {
                $changed[] = $path;
            }
        }
        sort($new);
        sort($changed);

        // Файлы кода, которых нет в репозитории (не удаляются, только сообщаем)
        $obsolete = [];
        foreach (['vlmcconf/sections', 'vlmcconf/vlmcinc', 'vlmcconf/locale'] as $dir) {
            $full = $this->baseDir . '/' . $dir;
            if (!is_dir($full)) {
                continue;
            }
            foreach ((array)scandir($full) as $name) {
                if (substr($name, -4) !== '.php') {
                    continue;
                }
                $rel = $dir . '/' . $name;
                if (!isset($files[$rel]) && is_file($this->baseDir . '/' . $rel)) {
                    $obsolete[] = $rel;
                }
            }
        }
        sort($obsolete);
        return ['new' => $new, 'changed' => $changed, 'obsolete' => $obsolete];
    }

    // ============================================
    // ПРОВЕРКА ОБНОВЛЕНИЙ
    // ============================================

    /** Проверка одного канала: что нового относительно установленных файлов. */
    public function checkChannel($channel) {
        if (!in_array($channel, self::CHANNELS, true)) {
            return ['ok' => false, 'success' => false, 'error' => 'bad_response', 'channel' => $channel];
        }
        $info = $this->resolveChannel($channel);
        if (!$info['ok']) {
            return $info + ['success' => false, 'channel' => $channel];
        }
        $tree = $this->getTree($info['tree']);
        if (!$tree['ok']) {
            return $tree + ['success' => false, 'channel' => $channel];
        }

        $diff = $this->computeDiff($tree['files']);
        $current = $this->getCurrentVersion();
        $toInstall = count($diff['new']) + count($diff['changed']);

        if ($channel === 'release') {
            $newer = version_compare($current, $info['version']) < 0;   // старый релиз — не «обновление»
            $available = $newer && $toInstall > 0;
        } else {
            $newer = null;
            $available = $toInstall > 0;
        }

        // Для релиза: сколько коммитов тега отсутствует в ветке main (релиз создан, а ветка с ним не влита)
        $mainBehind = null;
        if ($channel === 'release') {
            $cmp = $this->github('/repos/' . self::REPO . '/compare/' . rawurlencode(self::BRANCH) . '...' . rawurlencode($info['ref']));
            if ($cmp['ok'] && isset($cmp['data']['ahead_by'])) {
                $mainBehind = (int)$cmp['data']['ahead_by'];
            }
        }

        return [
            'ok' => true, 'success' => true, 'channel' => $channel, 'main_behind' => $mainBehind,
            'ref' => $info['ref'], 'version' => $info['version'], 'commit' => $info['commit'],
            'published_at' => $info['published_at'], 'committed_at' => $info['committed_at'], 'subject' => $info['subject'],
            'newer' => $newer, 'available' => $available,
            'new' => $diff['new'], 'changed' => $diff['changed'], 'obsolete' => $diff['obsolete'],
            'new_count' => count($diff['new']), 'changed_count' => count($diff['changed']),
            'obsolete_count' => count($diff['obsolete'])
        ];
    }

    /**
     * Проверка обоих каналов. Без $force результат берётся из сохранённого, пока он свежее интервала.
     * @return array ['cached' => bool, 'state' => array]
     */
    public function checkAll($force = false) {
        $state = $this->loadState();
        $settings = $this->getSettings();
        if (!$force && !$this->isStale($state, $settings['interval'])) {
            return ['cached' => true, 'state' => $state];
        }

        $state['checked_at'] = time();
        foreach (self::CHANNELS as $channel) {
            $res = $this->checkChannel($channel);
            foreach (['new', 'changed', 'obsolete'] as $key) {   // в состоянии держим не более 100 имён
                if (isset($res[$key])) {
                    $res[$key] = array_slice($res[$key], 0, 100);
                }
            }
            $state[$channel] = $res;
        }
        $this->saveState($state);
        return ['cached' => false, 'state' => $state];
    }

    /** Список файлов для окна подтверждения (выбранный канал). */
    public function getFilesToUpdate($channel) {
        $res = $this->checkChannel($channel);
        if (empty($res['success'])) {
            return $res;
        }
        $items = [];
        foreach ($res['new'] as $path) {
            $items[] = ['path' => $path, 'status' => 'new'];
        }
        foreach ($res['changed'] as $path) {
            $items[] = ['path' => $path, 'status' => 'changed'];
        }
        return ['success' => true, 'channel' => $channel, 'version' => $res['version'], 'commit' => $res['commit'],
                'items' => $items, 'total' => count($items), 'obsolete' => $res['obsolete']];
    }

    // ============================================
    // УСТАНОВКА
    // ============================================

    private function acquireLock() {
        $fh = @fopen(vlmcDataFile('update.lock'), 'c');
        if ($fh === false || !flock($fh, LOCK_EX | LOCK_NB)) {
            return false;
        }
        $this->lockHandle = $fh;
        return true;
    }

    private function releaseLock() {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    private function removeDir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array)scandir($dir) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Установка выбранного канала. Сервер сам определяет список файлов: от клиента принимается
     * только подмножество этого списка. Порядок: скачать во временную папку и сверить SHA каждого файла
     * -> резервная копия -> замена -> при любой ошибке откат из резервной копии.
     */
    public function performUpdate($channel, $requested = null) {
        $log = [];
        $fail = function ($code, array $extra = []) use (&$log) {
            return ['success' => false, 'error' => $code, 'log' => $log] + $extra;
        };

        if (!in_array($channel, self::CHANNELS, true)) {
            return $fail('bad_response');
        }
        if (!$this->acquireLock()) {
            return $fail('busy');
        }

        $stage = null;
        try {
            $log[] = '🔍 Resolving channel: ' . $channel;
            $info = $this->resolveChannel($channel);
            if (!$info['ok']) {
                return $fail($info['error'], $info);
            }
            $tree = $this->getTree($info['tree']);
            if (!$tree['ok']) {
                return $fail($tree['error'], $tree);
            }

            $diff = $this->computeDiff($tree['files']);
            $targets = array_merge($diff['new'], $diff['changed']);
            if (is_array($requested)) {
                $wanted = [];
                foreach ($requested as $name) {
                    $name = trim((string)$name);
                    $pos = strpos($name, ' (');
                    $wanted[] = $pos !== false ? substr($name, 0, $pos) : $name;
                }
                $targets = array_values(array_intersect($targets, $wanted));
            }
            if (!$targets) {
                return $fail('nothing');
            }
            $log[] = '📋 Files to update: ' . count($targets) . ' (revision ' . substr($info['commit'], 0, 7) . ')';

            // 1) скачиваем во временную папку и проверяем SHA — рабочие файлы пока не тронуты
            $stage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vlmc_update_' . bin2hex(random_bytes(6));
            if (!@mkdir($stage, 0700, true)) {
                return $fail('write_failed', ['file' => $stage]);
            }
            $log[] = '📥 Downloading new files...';
            foreach ($targets as $path) {
                $url = self::RAW . '/' . self::REPO . '/' . $info['commit'] . '/'
                     . implode('/', array_map('rawurlencode', explode('/', $path)));
                $r = vlmcHttpRequest($url, ['timeout' => 30, 'connect_timeout' => 8, 'user_agent' => 'KMS-Monitor-Updater/3.0']);
                if (!$r['ok']) {
                    $log[] = '❌ ' . $path . ' — download FAILED (' . ($r['error'] !== '' ? $r['error'] : 'HTTP ' . $r['status']) . ')';
                    return $fail($r['error'] !== '' ? $r['error'] : 'http_error', ['status' => $r['status'], 'file' => $path]);
                }
                if ($this->blobSha($r['body']) !== $tree['files'][$path]['sha']) {
                    $log[] = '❌ ' . $path . ' — checksum mismatch';
                    return $fail('verify_failed', ['file' => $path]);
                }
                $staged = $stage . '/' . $path;
                if (!is_dir(dirname($staged))) {
                    @mkdir(dirname($staged), 0700, true);
                }
                if (@file_put_contents($staged, $r['body']) === false) {
                    return $fail('write_failed', ['file' => $path]);
                }
                $log[] = '✅ ' . basename($path) . ' — downloaded and verified (' . strlen($r['body']) . ' bytes)';
            }

            // 2) резервная копия заменяемых файлов (вне веб-папки, если включено хранение данных вне неё)
            $backupDir = vlmcDataFile('backups') . '/update_' . date('Y-m-d_H-i-s');
            @mkdir($backupDir, 0750, true);
            $log[] = '📦 Creating backup: ' . basename($backupDir);
            foreach ($targets as $path) {
                $local = $this->baseDir . '/' . $path;
                if (is_file($local)) {
                    $copy = $backupDir . '/' . $path;
                    @mkdir(dirname($copy), 0750, true);
                    if (!@copy($local, $copy)) {
                        return $fail('write_failed', ['file' => 'backup: ' . $path]);
                    }
                }
            }

            // 3) замена файлов; при ошибке — откат
            $installed = [];
            foreach ($targets as $path) {
                $target = $this->baseDir . '/' . $path;
                $dir = dirname($target);
                $ok = (is_dir($dir) || @mkdir($dir, 0755, true));
                if ($ok) {
                    $real = realpath($dir);
                    $root = realpath($this->baseDir);
                    $ok = $real !== false && $root !== false
                        && (strpos(str_replace('\\', '/', $real) . '/', str_replace('\\', '/', $root) . '/') === 0);
                }
                $tmp = $target . '.new.' . getmypid();
                if (!$ok || !@copy($stage . '/' . $path, $tmp) || !@rename($tmp, $target)) {
                    @unlink($tmp);
                    $log[] = '❌ ' . $path . ' — installation FAILED, rolling back';
                    foreach (array_reverse($installed) as $done) {
                        $saved = $backupDir . '/' . $done;
                        if (is_file($saved)) {
                            @copy($saved, $this->baseDir . '/' . $done);
                        } else {
                            @unlink($this->baseDir . '/' . $done);
                        }
                    }
                    return $fail('write_failed', ['file' => $path, 'backup_dir' => $backupDir]);
                }
                @chmod($target, 0644);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($target, true);
                }
                $installed[] = $path;
                $log[] = '✅ ' . basename($path) . ' — installed';
            }

            $log[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
            $log[] = '✅ Updated: ' . count($installed) . ' files';
            @file_put_contents(vlmcDataFile('backups') . '/update_log_' . date('Y-m-d_H-i-s') . '.txt', implode("\n", $log));

            // после установки прежние результаты проверки устарели
            $state = $this->loadState();
            unset($state['release'], $state['early']);
            $state['checked_at'] = 0;
            $state['installed'] = [
                'channel' => $channel, 'ref' => $info['ref'], 'commit' => $info['commit'],
                'version' => $this->readVersionFromFile(), 'at' => time()
            ];
            $this->saveState($state);

            return [
                'success' => true, 'channel' => $channel, 'version' => $state['installed']['version'],
                'backup_dir' => $backupDir, 'updated' => $installed, 'updated_count' => count($installed),
                'failed' => [], 'log' => $log
            ];
        } finally {
            if ($stage !== null) {
                $this->removeDir($stage);
            }
            $this->releaseLock();
        }
    }

    // ============================================
    // ТЕКСТЫ ОШИБОК
    // ============================================

    /** Понятный текст ошибки (строки update_err_* в локалях; в консоли — сам код). */
    public static function errorText($code, array $r = []) {
        $key = 'update_err_' . $code;
        $text = function_exists('__') ? __($key) : $key;
        if ($text === $key) {
            return (string)$code;
        }
        switch ($code) {
            case 'rate_limit':
                return sprintf($text, !empty($r['reset']) ? date('H:i', (int)$r['reset']) : '—');
            case 'http_error':
                return sprintf($text, (int)($r['status'] ?? 0));
            case 'verify_failed':
            case 'write_failed':
                return sprintf($text, (string)($r['file'] ?? ''));
        }
        return $text;
    }
}
