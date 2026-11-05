<?php
// ============================================
// ФАЙЛ: sections/info.php
// ВЕРСИЯ: 1.6.0
// ДАТА: 2026-11-03
// @description: Секция "Информация" (доступ по праву PERM_INFO_VIEW)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'info.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права на просмотр информации
$canViewInfo = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_INFO_VIEW);

if (!$canViewInfo) {
    echo '<div class="settings-section" id="section-info"><div class="section-title"><span>ℹ️ ' . __('info_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
?>

<div id="section-info" class="settings-section <?= $activeSection === 'info' ? 'active' : '' ?>">
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <span>ℹ️ <?= __('info_title') ?></span>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span id="fileInfoStatus" class="file-info-status" style="display: none;"></span>
            <button id="refreshFileInfoBtn" class="btn btn-primary btn-small" onclick="refreshFileInfo(true)" style="font-size: 11px; padding: 4px 10px;">🔄 <?= __('refresh') ?></button>
        </div>
        <?php if ($activeSection === 'info' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Блок с версией, темой, конфигом -->
    <div style="background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 10px; margin-bottom: 20px; font-size: 12px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; flex: 1;">
                <div><strong style="color: #8aa0bb;"><?= __('info_version') ?>:</strong> <?= CONFIG_VERSION ?></div>
                <div><strong style="color: #8aa0bb;"><?= __('info_theme') ?>:</strong> <?= __($themeCSS['name']) ?></div>
                <div><strong style="color: #8aa0bb;"><?= __('info_config') ?>:</strong> <?= htmlspecialchars(basename($configFile)) ?></div>
                <div><strong style="color: #8aa0bb;"><?= __('info_log') ?>:</strong> <span style="color: <?= $logFileExists ? $themeCSS['success'] : $themeCSS['danger'] ?>;"><?= $logFileExists ? '✓' : '✗' ?></span></div>
            </div>
        </div>
    </div>
    
    <!-- Информация о сервере -->
    <div class="settings-card">
        <div class="settings-card-title">🖥️ <?= __('info_server') ?></div>
        <div class="server-info-grid">
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_os') ?>:</span><span><?= htmlspecialchars($osName) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_kernel') ?>:</span><span><?= htmlspecialchars(php_uname('r')) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_host') ?>:</span><span><?= htmlspecialchars($hostname) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_ip') ?>:</span><span><?= htmlspecialchars($serverIp) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_web_server') ?>:</span><span><?= htmlspecialchars($serverSoftware) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_php') ?>:</span><span><?= htmlspecialchars($phpVersion) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_uptime') ?>:</span><span><?= $uptimeFormatted ?></span></div>
        </div>
    </div>
    
    <!-- Структура проекта -->
    <?php renderFileStructure($themeCSS, $fileVersions, $configFile); ?>
</div>

<style>
.server-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px 30px;
    font-size: 12px;
}

.server-info-item {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
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
    color: #8aa0bb;
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
    color: white;
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