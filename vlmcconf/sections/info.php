<?php
// ============================================
// ФАЙЛ: sections/info.php
// ВЕРСИЯ: 1.7.1
// ДАТА: 2026-11-17
// @description: Секция "Информация" (доступ по праву PERM_INFO_VIEW): сервер, структура проекта (вкладка, прокручивается внутри блока) и файлы данных вне веб-папки (имя, назначение, полный путь на Ubuntu)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'info.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права на просмотр информации
$canViewInfo = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_INFO_VIEW);

if (!$canViewInfo) {
    echo '<div class="settings-section" id="section-info"><div class="section-title"><span>ℹ️ ' . __('info_title') . '</span></div><div style="text-align: center; padding: 40px; color: ' . $themeCSS['muted'] . ';">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
?>

<div id="section-info" class="settings-section <?= $activeSection === 'info' ? 'active' : '' ?>">
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <span>ℹ️ <?= __('info_title') ?></span>
        <div class="info-tabs">
            <button type="button" class="info-tab active" data-p="infoStructure">📁 <?= __('info_tab_structure') ?></button>
            <button type="button" class="info-tab" data-p="infoData">🗄️ <?= __('info_tab_data') ?></button>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span id="fileInfoStatus" class="file-info-status" style="display: none;"></span>
            <button id="refreshFileInfoBtn" class="btn btn-primary btn-small" onclick="refreshFileInfo(true)" style="font-size: 11px; padding: 4px 10px;">🔄 <?= __('refresh') ?></button>
        </div>
        <?php if ($activeSection === 'info' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Информация о сервере -->
    <div class="settings-card info-server-card">
        <div class="settings-card-title">🖥️ <?= __('info_server') ?></div>
        <div class="server-info-grid">
            <div class="server-info-item"><span style="color: <?= $themeCSS['muted'] ?>;"><?= __('info_os') ?>:</span><span><?= htmlspecialchars($osName) ?></span></div>
            <div class="server-info-item"><span style="color: <?= $themeCSS['muted'] ?>;"><?= __('info_kernel') ?>:</span><span><?= htmlspecialchars(php_uname('r')) ?></span></div>
            <div class="server-info-item"><span style="color: <?= $themeCSS['muted'] ?>;"><?= __('info_host') ?>:</span><span><?= htmlspecialchars($hostname) ?></span></div>
            <div class="server-info-item"><span style="color: <?= $themeCSS['muted'] ?>;"><?= __('info_ip') ?>:</span><span><?= htmlspecialchars($serverIp) ?></span></div>
            <div class="server-info-item"><span style="color: <?= $themeCSS['muted'] ?>;"><?= __('info_web_server') ?>:</span><span><?= htmlspecialchars($serverSoftware) ?></span></div>
            <div class="server-info-item"><span style="color: <?= $themeCSS['muted'] ?>;"><?= __('info_php') ?>:</span><span><?= htmlspecialchars($phpVersion) ?></span></div>
            <div class="server-info-item"><span style="color: <?= $themeCSS['muted'] ?>;"><?= __('info_uptime') ?>:</span><span><?= $uptimeFormatted ?></span></div>
        </div>
    </div>
    
    <!-- Структура проекта: прокручивается внутри блока, страница не уезжает -->
    <div id="infoStructure" class="info-page active">
        <div class="info-scroll"><?php renderFileStructure($themeCSS, $fileVersions, $configFile); ?></div>
    </div>

    <!-- Файлы данных вне веб-папки -->
    <div id="infoData" class="info-page">
        <div class="info-scroll">
        <?php
        $dataInfo = vlmcDataInfo();
        $isUnix = DIRECTORY_SEPARATOR === '/';
        // [имя, ключ описания, подпапка/признак каталога]
        $dataFiles = [
            ['vlmcconf_config.json', 'info_data_config'],
            ['users.json', 'info_data_users'],
            ['audit.log', 'info_data_audit'],
            ['audit.prune', 'info_data_audit_prune'],
            ['notify.json', 'info_data_notify'],
            ['notify.key', 'info_data_notify_key'],
            ['notify_state.json', 'info_data_notify_state'],
            ['notify_history.jsonl', 'info_data_notify_history'],
            ['update_state.json', 'info_data_update_state'],
            ['github_token.json', 'info_data_github_token'],
            ['update.lock', 'info_data_update_lock'],
            ['config_changes.log', 'info_data_config_changes'],
            ['users_changes.log', 'info_data_users_changes'],
            ['vlmcconf_config.json.lock', 'info_data_lock'],
            ['users.json.lock', 'info_data_lock'],
            ['backups', 'info_data_backups'],
        ];
        ?>
        <div class="info-data-card">
            <div class="info-data-head">
                <span><?= __('info_data_dir') ?>:</span>
                <?php if ($isUnix): ?><code><?= htmlspecialchars($dataInfo['dir']) ?></code><?php else: ?><span><?= __('info_data_dir_hidden') ?></span><?php endif; ?>
                <?php if (!empty($dataInfo['legacy'])): ?><span class="info-data-warn">⚠️ <?= __('info_data_legacy') ?></span><?php endif; ?>
            </div>
            <table class="info-data-table">
                <thead><tr>
                    <th><?= __('info_data_col_name') ?></th>
                    <th><?= __('info_data_col_purpose') ?></th>
                    <?php if ($isUnix): ?><th><?= __('info_data_col_path') ?></th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($dataFiles as $df): $full = vlmcDataFile($df[0]); $exists = file_exists($full); ?>
                    <tr class="<?= $exists ? '' : 'info-data-absent' ?>">
                        <td class="info-data-name"><?= is_dir($full) || $df[0] === 'backups' ? '📂' : '📄' ?> <?= htmlspecialchars($df[0]) ?><?= $df[0] === 'backups' ? '/' : '' ?><?php if (!$exists): ?> <span class="info-data-badge"><?= __('info_data_absent') ?></span><?php endif; ?></td>
                        <td><?= __($df[1]) ?></td>
                        <?php if ($isUnix): ?><td class="info-data-path"><code><?= htmlspecialchars($full) ?></code></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="info-hint"><?= __('info_data_hint') ?></div>
        </div>
        </div>
    </div>
</div>

<style>
/* Раздел занимает всю высоту области; прокручивается только блок со списком (страницу листать не нужно) */
#section-info.active { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.info-page { display: none; flex: 1 1 auto; min-height: 0; }
.info-page.active { display: flex; flex-direction: column; }
.info-scroll { flex: 1 1 auto; min-height: 160px; overflow: auto; }
.info-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
.info-tab { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 20px; padding: 6px 20px; font-size: 13px; font-weight: 500; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.7; transition: all 0.2s; font-family: inherit; }
.info-tab:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.info-tab.active { opacity: 1; color: <?= $themeCSS['accentText'] ?>; background: <?= $themeCSS['card'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.info-data-card { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 12px; }
.info-data-head { display: flex; flex-wrap: wrap; gap: 8px 10px; align-items: center; font-size: 12px; margin-bottom: 10px; }
.info-data-head code, .info-data-path code { font-family: 'JetBrains Mono', monospace; font-size: 11px; word-break: break-all; }
.info-data-warn { color: <?= $themeCSS['warnText'] ?>; }
.info-data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.info-data-table th { text-align: left; padding: 7px 10px; background: <?= $themeCSS['input'] ?>; color: <?= $themeCSS['muted'] ?>; font-weight: 600; white-space: nowrap; position: sticky; top: 0; }
.info-data-table td { padding: 6px 10px; border-top: 1px dashed <?= $themeCSS['border'] ?>; vertical-align: top; }
.info-data-name { white-space: nowrap; font-weight: 600; }
.info-data-absent td { opacity: 0.6; }
.info-data-badge { font-size: 10px; font-weight: 400; padding: 1px 8px; border-radius: 10px; background: <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; margin-left: 6px; }
.info-hint { margin-top: 10px; font-size: 11px; color: <?= $themeCSS['muted'] ?>; }
.info-server-card { padding: 8px 12px; margin-bottom: 10px; flex: 0 0 auto; height: auto; }
.info-server-card .settings-card-title { margin-bottom: 6px; font-size: 12px; }
.server-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 2px 24px;
    font-size: 12px;
}

.server-info-item {
    display: flex;
    justify-content: space-between;
    padding: 1px 0;
    border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
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
    color: <?= $themeCSS['muted'] ?>;
    text-transform: uppercase;
    letter-spacing: 0.3px;
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

.btn-primary {
    background: <?= $themeCSS['primary'] ?>;
    color: <?= $themeCSS['onPrimary'] ?>;
}
.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-small {
    padding: 4px 10px;
    font-size: 11px;
}

.btn:disabled {
    opacity: 0.7;
    cursor: wait;
    transform: none;
}

.file-info-status {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 12px;
    border: 1px solid transparent;
}
.file-info-status.ok {
    color: <?= $themeCSS['success'] ?>;
    border-color: <?= $themeCSS['success'] ?>;
}
.file-info-status.err {
    color: <?= $themeCSS['danger'] ?>;
    border-color: <?= $themeCSS['danger'] ?>;
}

/* Подсветка значений, которые изменились при обновлении */
.file-flash {
    background: <?= $themeCSS['primary'] ?>44;
    border-radius: 3px;
    transition: background 1.6s;
}
</style>

<script>
(function () {
    'use strict';

    // вкладки раздела
    document.querySelectorAll('#section-info .info-tab').forEach(function (b) {
        b.onclick = function () {
            document.querySelectorAll('#section-info .info-tab, #section-info .info-page').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            document.getElementById(b.dataset.p).classList.add('active');
        };
    });

    const I18N = <?= json_encode([
        'refreshing' => __('info_refreshing'),
        'refreshed'  => __('info_refreshed'),
        'unavailable'=> __('info_refresh_unavailable'),
        'failed'     => __('info_refresh_failed')
    ], JSON_UNESCAPED_UNICODE) ?>;

    let busy = false;
    let hideTimer = null;

    function fmt(template) {
        const args = Array.prototype.slice.call(arguments, 1);
        let i = 0;
        return String(template).replace(/%[sd]/g, function () { return i < args.length ? args[i++] : ''; });
    }

    function setStatus(text, cls, hideAfterMs) {
        const el = document.getElementById('fileInfoStatus');
        if (!el) return;
        clearTimeout(hideTimer);
        el.textContent = text;
        el.className = 'file-info-status ' + (cls || '');
        el.style.display = text ? 'inline-block' : 'none';
        if (text && hideAfterMs) {
            hideTimer = setTimeout(function () { el.style.display = 'none'; }, hideAfterMs);
        }
    }

    function flash(cell) {
        if (!cell) return;
        cell.classList.add('file-flash');
        setTimeout(function () { cell.classList.remove('file-flash'); }, 1600);
    }

    // manual === true: вызвано кнопкой (показываем ход и результат); иначе — тихое обновление при открытии раздела
    window.refreshFileInfo = function (manual) {
        if (busy) return;
        const rows = {};
        const paths = [];
        document.querySelectorAll('.file-row').forEach(function (row) {
            const p = row.dataset.path;
            if (p && !rows[p]) { rows[p] = row; paths.push(p); }
        });
        if (!paths.length) return;

        const btn = document.getElementById('refreshFileInfoBtn');
        const label = btn ? btn.innerHTML : '';
        busy = true;
        if (manual === true) {
            if (btn) { btn.disabled = true; btn.textContent = '⏳ ' + I18N.refreshing; }
            setStatus('', '');
        }

        const fd = new FormData();
        fd.append('ajax', 'file_info_batch');
        fd.append('paths', JSON.stringify(paths));

        fetch('', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !Array.isArray(d.files)) throw new Error('bad response');
                let changed = 0, failed = 0;
                d.files.forEach(function (f) {
                    const row = rows[f.path];
                    if (!row) return;
                    if (!f.success) { failed++; return; }
                    const size = row.querySelector('.file-size');
                    const mtime = row.querySelector('.file-mtime');
                    const ver = row.querySelector('.file-version');
                    let did = false;
                    if (size && size.textContent !== f.size) { size.textContent = f.size; flash(size); did = true; }
                    if (mtime && mtime.textContent !== f.date) { mtime.textContent = f.date; flash(mtime); did = true; }
                    if (ver && f.version && ver.textContent !== f.version) { ver.textContent = f.version; flash(ver); did = true; }
                    if (did) changed++;
                });
                if (manual === true) {
                    let text = '✅ ' + fmt(I18N.refreshed, d.time || '', paths.length - failed, changed);
                    if (failed) text += fmt(I18N.unavailable, failed);
                    setStatus(text, failed ? 'err' : 'ok', 8000);
                }
            })
            .catch(function (e) {
                console.error('File info error:', e);
                if (manual === true) setStatus('❌ ' + I18N.failed, 'err', 10000);
            })
            .then(function () {
                busy = false;
                if (manual === true && btn) { btn.disabled = false; btn.innerHTML = label; }
            });
    };

    // Тихое обновление при открытии раздела
    document.addEventListener('DOMContentLoaded', function () {
        const section = document.getElementById('section-info');
        if (section && section.classList.contains('active')) {
            setTimeout(window.refreshFileInfo, 500);
        }
    });

    const infoSection = document.getElementById('section-info');
    if (infoSection) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.attributeName === 'class' && infoSection.classList.contains('active')) {
                    setTimeout(window.refreshFileInfo, 500);
                }
            });
        }).observe(infoSection, { attributes: true });
    }
})();
</script>
<?php
?>