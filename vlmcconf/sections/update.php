<?php
// ============================================
// ФАЙЛ: sections/update.php
// ВЕРСИЯ: 4.0.0
// ДАТА: 2026-11-01
// @description: Секция обновления проекта: каналы release/early, автопроверка, установка (интерфейс + вызовы Updater через ajax.php)
// @description: Project update section: release/early channels, auto-check, install (interface + Updater calls via ajax.php)
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}

require_once dirname(__DIR__) . '/vlmcinc/updater.php';

$updater = new Updater();
$updSettings = $updater->getSettings();
$sslInfo = vlmcSslDiagnostics();

// Строки для JavaScript
$updI18n = [
    'notChecked'     => __('update_ch_not_checked'),
    'uptodate'       => __('update_ch_uptodate'),
    'availRelease'   => __('update_ch_available_release'),
    'availEarly'     => __('update_ch_available_early'),
    'older'          => __('update_ch_older'),
    'version'        => __('update_ch_version'),
    'revision'       => __('update_ch_revision'),
    'obsolete'       => __('update_obsolete_files'),
    'never'          => __('update_never'),
    'checking'       => __('update_checking'),
    'fileNew'        => __('update_file_new'),
    'fileChanged'    => __('update_file_changed'),
    'noFiles'        => __('update_no_files'),
    'loadError'      => __('error_loading'),
    'inProgress'     => __('update_in_progress'),
    'started'        => __('update_started'),
    'complete'       => __('update_complete'),
    'reload'         => __('update_reload_now'),
    'failedFiles'    => __('update_failed_files'),
    'selected'       => __('update_channel_selected'),
    'checkingGitHub' => __('update_checking_github'),
    'saveError'      => __('msg_save_error'),
    'ready'          => __('update_ready'),
    'cached'         => __('update_cached')
];
?>

<div id="toolsTabUpdate" class="tools-tab-content <?= $activeTab === 'update' ? 'active' : '' ?>">
    
    <!-- ============================================ -->
    <!-- ЗАГОЛОВОК С ЭЛЕМЕНТАМИ УПРАВЛЕНИЯ / HEADER   -->
    <!-- ============================================ -->
    <div class="update-header" style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap;">
        <div>
            <button class="btn btn-primary" id="checkUpdatesBtn">🔍 <?= __('update_check_btn') ?></button>
        </div>
        <div>
            <button class="btn btn-warning" id="runUpdateBtn" disabled>📥 <?= __('update_run_btn') ?></button>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- НАСТРОЙКИ ОБНОВЛЕНИЙ / UPDATE SETTINGS      -->
    <!-- ============================================ -->
    <div id="updateSettingsBlock" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 12px 15px; margin-bottom: 15px;">
        <div style="display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; gap: 25px; flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_type_label') ?></label>
                    <select id="updateType" class="form-control" style="width: 200px;">
                        <option value="release" <?= $updSettings['type'] === 'release' ? 'selected' : '' ?>><?= __('update_type_release') ?></option>
                        <option value="early" <?= $updSettings['type'] === 'early' ? 'selected' : '' ?>><?= __('update_type_early') ?></option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">
                        <input type="checkbox" id="autoCheckEnabled" <?= $updSettings['auto_check'] ? 'checked' : '' ?>>
                        <?= __('update_auto_check_label') ?>
                    </label>
                    <select id="autoCheckInterval" class="form-control" style="width: 200px;">
                        <?php foreach ([1, 3, 6, 12, 24, 72] as $hours): ?>
                        <option value="<?= $hours ?>" <?= $updSettings['interval'] === $hours ? 'selected' : '' ?>><?= sprintf(__('update_auto_check_every'), $hours) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="saveUpdateSettings()" style="padding: 6px 14px; font-size: 11px; flex-shrink: 0;">
                💾 <?= __('save') ?>
            </button>
        </div>
        
        <!-- Автоматическая установка, расписание и уведомления: в разработке -->
        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed <?= $themeCSS['border'] ?>; display: flex; gap: 25px; flex-wrap: wrap; align-items: center; opacity: 0.55;">
            <span class="update-dev-badge">🚧 <?= __('update_in_dev') ?></span>
            <label style="font-size: 11px; color: #8aa0bb;"><input type="checkbox" disabled> <?= __('update_mode_auto') ?></label>
            <label style="font-size: 11px; color: #8aa0bb;"><input type="checkbox" disabled> <?= __('update_schedule_enable') ?></label>
            <label style="font-size: 11px; color: #8aa0bb;"><input type="checkbox" disabled> <?= __('update_notify_enable') ?></label>
        </div>
        
        <div id="settingsMessage" class="block-message" style="display: none; margin-top: 10px;"></div>
    </div>
    
    <!-- ============================================ -->
    <!-- НАСТРОЙКИ GITHUB / GITHUB SETTINGS           -->
    <!-- ============================================ -->
    <div id="updateGithubSettings" class="update-info-panel" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 10px 12px; margin-bottom: 15px;">
        <div style="display: flex; gap: 30px; flex-wrap: wrap; font-size: 11px;">
            <div><span style="color: #8aa0bb;">📦 <?= __('update_repository') ?>:</span> <code style="font-size: 11px;">AlexandreKz/Monitor-VLMCSD</code></div>
            <div><span style="color: #8aa0bb;">🌿 <?= __('update_branch') ?>:</span> <code style="font-size: 11px;">main</code></div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- КАНАЛЫ ОБНОВЛЕНИЙ / UPDATE CHANNELS          -->
    <!-- ============================================ -->
    <div class="update-channels" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
        <div id="chCardRelease" class="update-channel-card">
            <div class="uc-title">🏷️ <?= __('update_type_release') ?> <span class="uc-badge" id="chBadgeRelease"></span></div>
            <div class="uc-line" id="chReleaseVersion">—</div>
            <div class="uc-line uc-status" id="chReleaseStatus"><?= __('update_ch_not_checked') ?></div>
            <div class="uc-line uc-small" id="chReleaseExtra"></div>
        </div>
        <div id="chCardEarly" class="update-channel-card">
            <div class="uc-title">🧪 <?= __('update_type_early') ?> <span class="uc-badge" id="chBadgeEarly"></span></div>
            <div class="uc-line" id="chEarlyVersion">—</div>
            <div class="uc-line uc-status" id="chEarlyStatus"><?= __('update_ch_not_checked') ?></div>
            <div class="uc-line uc-small" id="chEarlyExtra"></div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- СТАТУС-БАР / STATUS BAR                      -->
    <!-- ============================================ -->
    <div class="update-status-bar" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 10px 12px; margin-bottom: 15px; display: flex; gap: 30px; flex-wrap: wrap; font-size: 11px;">
        <div><span style="color: #8aa0bb;">📌 <?= __('update_current_version') ?>:</span> <strong><span id="currentVersion"><?= CONFIG_VERSION ?></span></strong></div>
        <div><span style="color: #8aa0bb;">🕒 <?= __('update_last_check') ?>:</span> <span id="lastCheckText"><?= __('update_never') ?></span></div>
        <div><span style="color: #8aa0bb;">📊 <?= __('update_status') ?>:</span> <span id="updateStatusText"><?= __('update_not_checked') ?></span></div>
    </div>
    
    <!-- ============================================ -->
    <!-- ПОДСКАЗКА ПО SSL / SSL HELP                  -->
    <!-- ============================================ -->
    <div id="updateSslHelp" class="block-message error" style="display: none; margin-bottom: 15px; line-height: 1.5;">
        <strong>🔒 <?= __('update_ssl_help_title') ?></strong>
        <pre style="white-space: pre-wrap; margin: 8px 0 0 0; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: inherit;"><?= htmlspecialchars(__($sslInfo['windows'] ? 'update_ssl_help_windows' : 'update_ssl_help_linux')) ?>

<?php if ($sslInfo['windows']): ?>
php.ini: <?= htmlspecialchars($sslInfo['php_ini'] !== '' ? $sslInfo['php_ini'] : '—') ?>

curl.cainfo = "<?= htmlspecialchars($sslInfo['curl_cainfo']) ?>" <?= $sslInfo['curl_cainfo'] === '' ? '(не задан)' : ($sslInfo['curl_cainfo_exists'] ? '(файл найден)' : '(файл НЕ найден)') ?>

openssl.cafile = "<?= htmlspecialchars($sslInfo['openssl_cafile']) ?>" <?= $sslInfo['openssl_cafile'] === '' ? '(не задан)' : ($sslInfo['openssl_cafile_exists'] ? '(файл найден)' : '(файл НЕ найден)') ?>
<?php endif; ?></pre>
    </div>
    
    <!-- ============================================ -->
    <!-- КОНСОЛЬ ВЫВОДА / OUTPUT CONSOLE             -->
    <!-- ============================================ -->
    <div class="update-console" id="updateConsole" style="background: #121212; color: #00ff00; font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 10px 12px; border-radius: 6px; height: 250px; overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>;">
        <div>> <?= __('update_ready') ?></div>
    </div>
    
    <!-- ============================================ -->
    <!-- КНОПКА ПРОВЕРКИ GITHUB / GITHUB CHECK       -->
    <!-- ============================================ -->
    <div style="margin-top: 15px; text-align: right;">
        <button class="btn btn-secondary btn-small" id="checkGitHubBtn" style="opacity: 0.6; font-size: 10px; padding: 2px 8px;">🌐 <?= __('update_check_github') ?></button>
    </div>
    
    <!-- ============================================ -->
    <!-- МОДАЛЬНОЕ ОКНО ПОДТВЕРЖДЕНИЯ ОБНОВЛЕНИЯ      -->
    <!-- ============================================ -->
    <div id="updateConfirmModal" class="tools-modal" style="display: none;">
        <div class="tools-modal-content">
            <div class="tools-modal-header">
                <h2>⚠️ <?= __('update_confirm_title') ?></h2>
                <span class="tools-modal-close" onclick="closeUpdateConfirmModal()">&times;</span>
            </div>
            <div class="tools-modal-body">
                <p><?= __('update_confirm_warning') ?></p>
                <p class="tools-modal-estimate"><?= __('update_confirm_desc') ?></p>
                <div id="updateFilesList" style="margin-top: 10px; max-height: 200px; overflow-y: auto; font-size: 11px;"></div>
            </div>
            <div class="tools-modal-footer">
                <button class="btn btn-secondary" onclick="closeUpdateConfirmModal()">❌ <?= __('cancel') ?></button>
                <button class="btn btn-primary" id="startUpdateBtn" disabled>✅ <?= __('update_confirm_yes') ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.update-console::-webkit-scrollbar { width: 6px; }
.update-console::-webkit-scrollbar-track { background: #2a2a2a; border-radius: 3px; }
.update-console::-webkit-scrollbar-thumb { background: #00ff00; border-radius: 3px; }
.update-console::-webkit-scrollbar-thumb:hover { background: #00cc00; }

.update-channel-card { background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 12px 14px; border: 1px solid transparent; font-size: 12px; }
.update-channel-card.selected { border-color: <?= $themeCSS['primary'] ?>; }
.update-channel-card .uc-title { font-weight: 600; margin-bottom: 6px; }
.update-channel-card .uc-line { margin-top: 3px; }
.update-channel-card .uc-small { font-size: 11px; color: #8aa0bb; word-break: break-word; }
.update-channel-card .uc-status.ok { color: <?= $themeCSS['success'] ?>; }
.update-channel-card .uc-status.avail { color: #e0a800; font-weight: 600; }
.update-channel-card .uc-status.err { color: <?= $themeCSS['danger'] ?>; }
.uc-badge { font-size: 10px; font-weight: 400; color: <?= $themeCSS['primary'] ?>; margin-left: 6px; }
.update-dev-badge { font-size: 11px; color: #e0a800; }

.update-settings .form-group { margin-bottom: 8px; }
.update-settings .form-control { padding: 6px 10px; font-size: 12px; }
.block-message { padding: 8px 12px; border-radius: 6px; font-size: 12px; }
.block-message.success { background: <?= $themeCSS['success'] ?>20; color: <?= $themeCSS['success'] ?>; border: 1px solid <?= $themeCSS['success'] ?>; }
.block-message.error { background: <?= $themeCSS['danger'] ?>20; color: <?= $themeCSS['danger'] ?>; border: 1px solid <?= $themeCSS['danger'] ?>; }
.block-message.info { background: <?= $themeCSS['primary'] ?>20; color: <?= $themeCSS['primary'] ?>; border: 1px solid <?= $themeCSS['primary'] ?>; }
</style>

<script>
(function () {
    'use strict';

    const I18N = <?= json_encode($updI18n, JSON_UNESCAPED_UNICODE) ?>;
    const $ = function (id) { return document.getElementById(id); };

    let lastData = null;       // последний ответ проверки (оба канала)
    let updateFiles = [];      // пути файлов, которые подтверждены в окне

    // «Подстановка» %s / %d по порядку
    function fmt(template) {
        const args = Array.prototype.slice.call(arguments, 1);
        let i = 0;
        return String(template).replace(/%[sd]/g, function () { return i < args.length ? args[i++] : ''; });
    }

    function log(text, color) {
        const box = $('updateConsole');
        if (!box) return;
        const line = document.createElement('div');
        line.textContent = '> ' + text;
        if (color) line.style.color = color;
        box.appendChild(line);
        box.scrollTop = box.scrollHeight;
    }

    function post(params) {
        const fd = new FormData();
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch('', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    function selectedChannel() {
        const el = $('updateType');
        return el ? el.value : 'release';
    }

    function fmtDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return isNaN(d.getTime()) ? '' : d.toLocaleDateString();
    }

    function setSslHelp(show) {
        const box = $('updateSslHelp');
        if (box) box.style.display = show ? 'block' : 'none';
    }

    // --- Карточки каналов ---
    function renderChannel(name, info) {
        const Name = name === 'release' ? 'Release' : 'Early';
        const ver = $('ch' + Name + 'Version');
        const st = $('ch' + Name + 'Status');
        const extra = $('ch' + Name + 'Extra');
        if (!ver || !st || !extra) return;
        st.className = 'uc-line uc-status';
        extra.textContent = '';

        if (!info) {
            ver.textContent = '—';
            st.textContent = I18N.notChecked;
            return;
        }
        if (!info.success) {
            ver.textContent = '—';
            st.textContent = info.message || info.error || '';
            st.classList.add('err');
            return;
        }

        if (name === 'release') {
            ver.textContent = I18N.version + ': ' + info.version + (info.published_at ? ' (' + fmtDate(info.published_at) + ')' : '');
            if (info.available) {
                st.textContent = fmt(I18N.availRelease, info.version, info.new_count + info.changed_count);
                st.classList.add('avail');
            } else if (info.newer === false) {
                st.textContent = I18N.older;
            } else {
                st.textContent = I18N.uptodate;
                st.classList.add('ok');
            }
        } else {
            ver.textContent = I18N.revision + ': ' + info.commit + (info.committed_at ? ' (' + fmtDate(info.committed_at) + ')' : '');
            if (info.available) {
                st.textContent = fmt(I18N.availEarly, info.new_count, info.changed_count);
                st.classList.add('avail');
            } else {
                st.textContent = I18N.uptodate;
                st.classList.add('ok');
            }
            extra.textContent = info.subject || '';
        }
        if (info.obsolete_count > 0) {
            extra.textContent = (extra.textContent ? extra.textContent + ' · ' : '') + fmt(I18N.obsolete, info.obsolete_count);
        }
    }

    function markSelected() {
        const sel = selectedChannel();
        const cr = $('chCardRelease'), ce = $('chCardEarly');
        if (cr) cr.classList.toggle('selected', sel === 'release');
        if (ce) ce.classList.toggle('selected', sel === 'early');
        const br = $('chBadgeRelease'), be = $('chBadgeEarly');
        if (br) br.textContent = sel === 'release' ? '· ' + I18N.selected : '';
        if (be) be.textContent = sel === 'early' ? '· ' + I18N.selected : '';
    }

    function renderState(data) {
        lastData = data;
        markSelected();
        if (!data || !data.enabled) {
            return;
        }
        const ch = data.channels || {};
        renderChannel('release', ch.release);
        renderChannel('early', ch.early);

        const last = $('lastCheckText');
        if (last) last.textContent = data.checked_at ? new Date(data.checked_at * 1000).toLocaleString() : I18N.never;

        const sel = ch[selectedChannel()];
        const statusEl = $('updateStatusText');
        if (statusEl) {
            const target = sel && sel.success ? (selectedChannel() === 'release' ? $('chReleaseStatus') : $('chEarlyStatus')) : null;
            statusEl.textContent = sel ? (sel.success ? (target ? target.textContent : '') : (sel.message || '')) : I18N.notChecked;
        }
        const runBtn = $('runUpdateBtn');
        if (runBtn) runBtn.disabled = !(sel && sel.success && sel.available);
        setSslHelp(!!data.ssl_help);
    }

    // --- Проверка обоих каналов ---
    function checkForUpdates() {
        const btn = $('checkUpdatesBtn');
        if (btn) btn.disabled = true;
        log(I18N.checking);
        post({ ajax: 'check_updates' }).then(function (data) {
            if (data && data.success === false) {
                log(data.message || I18N.loadError, '#ff6666');
                return;
            }
            renderState(data);
            ['release', 'early'].forEach(function (name) {
                const c = data.channels[name];
                if (!c) return;
                if (!c.success) { log(name + ': ' + c.message, '#ff6666'); return; }
                log(name + ': ' + (c.available ? (name === 'release' ? fmt(I18N.availRelease, c.version, c.new_count + c.changed_count) : fmt(I18N.availEarly, c.new_count, c.changed_count)) : I18N.uptodate));
            });
        }).catch(function () {
            log(I18N.loadError, '#ff6666');
        }).then(function () {
            if (btn) btn.disabled = false;
        });
    }

    // --- Проверка связи с GitHub и SSL ---
    function checkGitHubAccess() {
        log(I18N.checkingGitHub);
        post({ ajax: 'check_github_access' }).then(function (data) {
            log(data.message || '', data.success ? '#00ff00' : '#ff6666');
            setSslHelp(!!data.ssl_help);
        }).catch(function () { log(I18N.loadError, '#ff6666'); });
    }

    // --- Окно подтверждения со списком файлов ---
    function runUpdate() {
        post({ ajax: 'get_update_files', channel: selectedChannel() }).then(function (data) {
            if (!data.success) {
                log(data.message || I18N.loadError, '#ff6666');
                return;
            }
            const list = $('updateFilesList');
            list.textContent = '';
            updateFiles = [];
            data.items.forEach(function (item) {
                updateFiles.push(item.path);
                const row = document.createElement('div');
                row.textContent = '📄 ' + item.path + ' (' + (item.status === 'new' ? I18N.fileNew : I18N.fileChanged) + ')';
                list.appendChild(row);
            });
            if (!data.items.length) {
                list.textContent = I18N.noFiles;
            }
            $('startUpdateBtn').disabled = data.items.length === 0;
            $('updateConfirmModal').style.display = 'flex';
        }).catch(function () { log(I18N.loadError, '#ff6666'); });
    }

    function closeUpdateConfirmModal() {
        const m = $('updateConfirmModal');
        if (m) m.style.display = 'none';
    }

    // --- Установка ---
    function startUpdate() {
        const startBtn = $('startUpdateBtn');
        startBtn.disabled = true;
        closeUpdateConfirmModal();
        log(I18N.started);
        const runBtn = $('runUpdateBtn');
        if (runBtn) runBtn.disabled = true;

        post({ ajax: 'perform_update', channel: selectedChannel(), files: JSON.stringify(updateFiles) }).then(function (data) {
            (data.log || []).forEach(function (line) { log(line); });
            if (data.success) {
                log(data.message || I18N.complete, '#00ff00');
                const box = $('updateConsole');
                const btn = document.createElement('button');
                btn.className = 'btn btn-primary btn-small';
                btn.style.marginTop = '8px';
                btn.textContent = I18N.reload;
                btn.onclick = function () { location.reload(); };
                box.appendChild(btn);
                box.scrollTop = box.scrollHeight;
            } else {
                log(data.message || I18N.failedFiles, '#ff6666');
                setSslHelp(data.error === 'ssl_ca' || data.error === 'ssl');
                if (runBtn) runBtn.disabled = false;
            }
        }).catch(function () {
            log(I18N.loadError, '#ff6666');
            if (runBtn) runBtn.disabled = false;
        });
    }

    // --- Настройки ---
    function saveUpdateSettings() {
        const msg = $('settingsMessage');
        post({
            ajax: 'save_update_settings',
            type: selectedChannel(),
            auto_check: $('autoCheckEnabled').checked ? 'true' : 'false',
            check_interval: $('autoCheckInterval').value
        }).then(function (data) {
            msg.className = 'block-message ' + (data.success ? 'success' : 'error');
            msg.textContent = data.message || '';
            msg.style.display = 'block';
            setTimeout(function () { msg.style.display = 'none'; }, 3000);
        }).catch(function () {
            msg.className = 'block-message error';
            msg.textContent = I18N.saveError;
            msg.style.display = 'block';
        });
    }

    // Подтянуть сохранённый результат автопроверки (при свежем — без запросов к GitHub)
    function loadState() {
        post({ ajax: 'update_auto_check' }).then(function (data) {
            if (data && data.success && data.enabled) {
                renderState(data);
            } else {
                markSelected();
            }
        }).catch(function () { markSelected(); });
    }

    function initUpdateTab() {
        const checkBtn = $('checkUpdatesBtn');
        const gitHubBtn = $('checkGitHubBtn');
        const runBtn = $('runUpdateBtn');
        const startBtn = $('startUpdateBtn');
        const typeSel = $('updateType');

        if (checkBtn) checkBtn.onclick = checkForUpdates;
        if (gitHubBtn) gitHubBtn.onclick = checkGitHubAccess;
        if (runBtn) runBtn.onclick = runUpdate;
        if (startBtn) startBtn.onclick = startUpdate;
        if (typeSel) typeSel.onchange = function () { renderState(lastData); markSelected(); };

        markSelected();
        if (!lastData) loadState();
    }

    window.initUpdateTab = initUpdateTab;
    window.checkForUpdates = checkForUpdates;
    window.checkGitHubAccess = checkGitHubAccess;
    window.runUpdate = runUpdate;
    window.startUpdate = startUpdate;
    window.closeUpdateConfirmModal = closeUpdateConfirmModal;
    window.saveUpdateSettings = saveUpdateSettings;

    // Наблюдатель за активацией вкладки
    function checkAndInit() {
        const tab = $('toolsTabUpdate');
        if (tab && tab.classList.contains('active')) {
            setTimeout(initUpdateTab, 10);
        }
    }
    checkAndInit();

    const tab = $('toolsTabUpdate');
    if (tab) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.attributeName === 'class' && tab.classList.contains('active')) {
                    setTimeout(initUpdateTab, 10);
                }
            });
        }).observe(tab, { attributes: true });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            const t = $('toolsTabUpdate');
            if (t && t.classList.contains('active')) initUpdateTab();
        }, 100);
    });
})();
</script>
<?php
?>