<?php
// ============================================
// ФАЙЛ: sections/update.php
// ВЕРСИЯ: 5.7.3
// ДАТА: 2026-11-19
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
$tokenInfo = $updater->tokenInfo();

// Строки для JavaScript
$updI18n = [
    'channelSaved'   => __('update_channel_saved'),
    'tokenEmpty'     => __('update_token_empty'),
    'tokenSet'       => __('update_token_set'),
    'tokenUnset'     => __('update_token_unset'),
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
    'settingsSaved'  => __('update_settings_saved'),
    'toastRelease'   => __('update_toast_release'),
    'toastEarly'     => __('update_toast_early'),
    'toastNone'      => __('update_toast_none'),
    'checkingGitHub' => __('update_checking_github'),
    'saveError'      => __('msg_save_error'),
    'ready'          => __('update_ready'),
    'cached'         => __('update_cached'),
    'uptodateMain'   => __('update_ch_uptodate_main'),
    'mainBehind'     => __('update_ch_main_behind')
];
?>

<div class="update-root"><!-- контейнер вкладки (id toolsTabUpdate и класс tools-tab-content задаёт tools.php) -->
    
    <!-- ============================================ -->
    <!-- БЛОК 1: РЕПОЗИТОРИЙ, ВЕРСИЯ, СТАТУС, КНОПКИ  -->
    <!-- ============================================ -->
    <div class="upd-frame" style="margin-bottom: 8px;">
        <div class="upd-info-grid">
            <div class="upd-info-item"><span class="upd-info-label">📦 <?= __('update_repository') ?></span><code>AlexandreKz/Monitor-VLMCSD</code></div>
            <div class="upd-info-item"><span class="upd-info-label">🌿 <?= __('update_branch') ?></span><code>main</code></div>
            <div class="upd-info-item"><span class="upd-info-label">📌 <?= __('update_current_version') ?></span><strong id="currentVersion"><?= CONFIG_VERSION ?></strong></div>
            <div class="upd-info-item"><span class="upd-info-label">🕒 <?= __('update_last_check') ?></span><span id="lastCheckText"><?= __('update_never') ?></span></div>
            <div class="upd-info-item"><span class="upd-info-label">📊 <?= __('update_status') ?></span><span id="updateStatusText"><?= __('update_not_checked') ?></span></div>
        </div>
        <div class="upd-actions">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-primary" id="checkUpdatesBtn">🔍 <?= __('update_check_btn') ?></button>
                <button class="btn btn-warning" id="runUpdateBtn" disabled>📥 <?= __('update_run_btn') ?></button>
            </div>
            <button class="btn btn-secondary btn-small" id="checkGitHubBtn" style="font-size: 11px; padding: 5px 12px;">🌐 <?= __('update_check_github') ?></button>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- БЛОК 2: ТИП ОБНОВЛЕНИЯ (нажатием на карточку) -->
    <!-- ============================================ -->
    <div class="upd-frame" style="margin-bottom: 8px;">
        <div class="upd-frame-title">
            <span>🎯 <?= __('update_type_label') ?></span>
            <span id="channelSavedMsg" style="visibility: hidden; font-size: 12px; white-space: nowrap;"></span>
        </div>
        <input type="hidden" id="updateType" value="<?= htmlspecialchars($updSettings['type']) ?>">
        <div class="update-channels" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div id="chCardRelease" class="update-channel-card" role="button" tabindex="0" aria-pressed="false">
                <div class="uc-title">🏷️ <?= __('update_type_release') ?> <span class="uc-badge" id="chBadgeRelease"></span></div>
                <div class="uc-line" id="chReleaseVersion">—</div>
                <div class="uc-line uc-status" id="chReleaseStatus"><?= __('update_ch_not_checked') ?></div>
                <div class="uc-line uc-small" id="chReleaseExtra"></div>
            </div>
            <div id="chCardEarly" class="update-channel-card" role="button" tabindex="0" aria-pressed="false">
                <div class="uc-title">🧪 <?= __('update_type_early') ?> <span class="uc-badge" id="chBadgeEarly"></span></div>
                <div class="uc-line" id="chEarlyVersion">—</div>
                <div class="uc-line uc-status" id="chEarlyStatus"><?= __('update_ch_not_checked') ?></div>
                <div class="uc-line uc-small" id="chEarlyExtra"></div>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- БЛОК 3: ДОПОЛНИТЕЛЬНЫЕ НАСТРОЙКИ (в одну строку) -->
    <!-- ============================================ -->
    <div class="upd-frame" id="updateSettingsBlock" style="margin-bottom: 8px;">
        <div class="upd-extra-row">
            <!-- Токен GitHub (необязательно): лимит запросов к API 5000/ч вместо 60/ч -->
            <div class="upd-col">
                <div class="upd-col-title" title="<?= htmlspecialchars(__('update_token_hint')) ?>">🔑 <?= __('update_token_label') ?> <span class="upd-i">ⓘ</span></div>
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; min-height: 32px;">
                    <input type="password" id="githubToken" class="form-control" style="flex: 1; min-width: 150px;" autocomplete="new-password" spellcheck="false" placeholder="<?= htmlspecialchars(__('update_token_placeholder')) ?>">
                    <button class="btn btn-primary" id="saveTokenBtn" style="padding: 6px 12px; font-size: 11px;">💾</button>
                    <button class="btn btn-danger" id="clearTokenBtn" style="padding: 6px 12px; font-size: 11px;" title="<?= htmlspecialchars(__('update_token_clear')) ?>">🗑️</button>
                </div>
                <div id="tokenStatus" style="font-size: 11px; margin-top: 4px; min-height: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= $tokenInfo['set'] ? sprintf(__('update_token_set'), htmlspecialchars($tokenInfo['hint'])) : __('update_token_unset') ?></div>
            </div>
            <!-- Автоматическая проверка: не чаще раза в 6 часов, сохраняется сразу -->
            <div class="upd-col">
                <div class="upd-col-title" title="<?= htmlspecialchars(__('update_auto_check_hint')) ?>">🔄 <?= __('update_auto_check_label') ?> <span class="upd-i">ⓘ</span></div>
                <select id="autoCheckInterval" class="form-control" style="width: 100%; box-sizing: border-box; height: 32px; margin: 0;">
                    <?php foreach ([6, 12, 24, 72] as $hours): ?>
                    <option value="<?= $hours ?>" <?= $updSettings['interval'] === $hours ? 'selected' : '' ?>><?= sprintf(__('update_auto_check_every'), $hours) ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; margin-top: 8px;">
                    <input type="checkbox" id="autoCheckEnabled" <?= $updSettings['auto_check'] ? 'checked' : '' ?>> <?= __('update_auto_check_on') ?>
                </label>
                <div id="autoSavedMsg" style="visibility: hidden; min-height: 16px; font-size: 12px; margin-top: 4px;"></div>
            </div>
            <div class="upd-col">
                <div style="opacity: 0.55;">
                <div class="upd-col-title">🛠️ <?= __('update_auto_install_title') ?> <span class="update-dev-badge">🚧 <?= __('update_in_dev') ?></span></div>
                <label class="upd-dev-item"><input type="checkbox" disabled> <?= __('update_mode_auto') ?></label>
                <label class="upd-dev-item"><input type="checkbox" disabled> <?= __('update_schedule_enable') ?></label>
                </div>
                <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid <?= $themeCSS['border'] ?>;">
                    <div style="font-size: 12px; color: <?= $themeCSS['muted'] ?>; line-height: 1.4;"><?= __('notify_update_hint') ?></div>
                </div>
            </div>
        </div>
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
    <div class="update-console" id="updateConsole" style="background: #121212; color: #00ff00; font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 10px 12px; border-radius: 8px; height: 260px; min-height: 160px; resize: vertical; overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>;">
        <div>> <?= __('update_ready') ?></div>
    </div>
    
    <!-- ============================================ -->
    <!-- МОДАЛЬНОЕ ОКНО ПОДТВЕРЖДЕНИЯ ОБНОВЛЕНИЯ      -->
    <!-- ============================================ -->
    <div id="updateConfirmModal" class="tools-modal" style="display: none;">
        <div class="tools-modal-content mc-danger">
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

/* фиксированная высота: содержимое подгружается позже, но блок не меняет размер */
.update-channel-card { display: grid; grid-template-columns: auto minmax(0, 1fr); grid-template-rows: 18px 18px 16px; align-content: start; height: 76px; box-sizing: border-box; overflow: hidden; gap: 2px 12px; background: <?= $themeCSS['card'] ?>; border-radius: 8px; padding: 8px 12px; border: 1px solid <?= $themeCSS['border'] ?>; font-size: 12px; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; }
.update-channel-card:hover { border-color: <?= $themeCSS['primary'] ?>; }
.update-channel-card:focus-visible { outline: 2px solid <?= $themeCSS['primary'] ?>; outline-offset: 2px; }
.update-channel-card.selected { box-shadow: 0 0 0 1px <?= $themeCSS['primary'] ?> inset; }
/* компактная раскладка: больше места под консоль */
.upd-frame { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 8px 12px; }
.upd-frame-title { display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; margin-bottom: 6px; gap: 10px; }
.upd-info-grid { display: flex; flex-wrap: nowrap; align-items: baseline; gap: 4px 22px; font-size: 11px; overflow: hidden; min-height: 16px; }
@media (max-width: 800px) { .upd-info-grid { flex-wrap: wrap; } }
.upd-info-item { display: inline-flex; align-items: baseline; gap: 5px; min-width: 0; flex: 0 0 auto; white-space: nowrap; }
.upd-info-item:last-child { flex: 1 1 auto; overflow: hidden; }
#updateStatusText { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.upd-info-label { color: <?= $themeCSS['muted'] ?>; white-space: nowrap; }
.upd-info-label::after { content: ':'; }
.upd-info-item code { font-size: 11px; }
.upd-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed <?= $themeCSS['border'] ?>; }
.upd-extra-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
@media (max-width: 1000px) { .upd-extra-row { grid-template-columns: 1fr; } .update-channels { grid-template-columns: 1fr !important; } }
.upd-col { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 8px 10px; min-width: 0; }
.upd-i { color: <?= $themeCSS['muted'] ?>; font-weight: 400; cursor: help; }
.upd-col-title { font-size: 12px; font-weight: 600; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.upd-hint { font-size: 11px; color: <?= $themeCSS['muted'] ?>; margin-top: 6px; line-height: 1.4; }
.upd-dev-item { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: <?= $themeCSS['muted'] ?>; margin: 2px 12px 0 0; }
.update-channel-card .uc-title { font-weight: 600; margin-bottom: 6px; }
.update-channel-card .uc-line { margin-top: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 18px; min-width: 0; }
.update-channel-card .uc-title { grid-column: 1 / -1; margin-bottom: 0 !important; line-height: 18px; white-space: nowrap; overflow: hidden; }
.update-channel-card .uc-small { grid-column: 1 / -1; line-height: 16px; }
.update-channel-card #chReleaseVersion, .update-channel-card #chEarlyVersion { grid-column: 1; }
.update-channel-card .uc-status { grid-column: 2; }
#section-tools .section-title { min-height: 34px; }
.update-channel-card .uc-small { font-size: 11px; color: <?= $themeCSS['muted'] ?>; word-break: break-word; }
.update-channel-card .uc-status.ok { color: <?= $themeCSS['success'] ?>; }
.update-channel-card .uc-status.avail { color: #e0a800; font-weight: 600; }
.update-channel-card .uc-status.err { color: <?= $themeCSS['danger'] ?>; }
.uc-badge { font-size: 10px; font-weight: 400; color: <?= $themeCSS['accentText'] ?>; margin-left: 6px; }
.section-message.update-persist { animation: none; opacity: 1; max-width: 560px; }
.update-dev-badge { font-size: 11px; color: #e0a800; }

.update-settings .form-group { margin-bottom: 8px; }
.update-settings .form-control { padding: 6px 10px; font-size: 12px; }
.block-message { padding: 8px 12px; border-radius: 6px; font-size: 12px; }
.block-message.success { background: <?= $themeCSS['success'] ?>20; color: <?= $themeCSS['success'] ?>; border: 1px solid <?= $themeCSS['success'] ?>; }
.block-message.error { background: <?= $themeCSS['danger'] ?>20; color: <?= $themeCSS['danger'] ?>; border: 1px solid <?= $themeCSS['danger'] ?>; }
.block-message.info { background: <?= $themeCSS['primary'] ?>20; color: <?= $themeCSS['accentText'] ?>; border: 1px solid <?= $themeCSS['primary'] ?>; }
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
                st.textContent = I18N.uptodateMain;
                st.classList.add('ok');
            }
            extra.textContent = info.subject || '';
        }
        if (info.main_behind > 0) {
            extra.textContent = (extra.textContent ? extra.textContent + ' · ' : '') + '⚠️ ' + fmt(I18N.mainBehind, info.main_behind);
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
        if (cr) cr.setAttribute('aria-pressed', sel === 'release' ? 'true' : 'false');
        if (ce) ce.setAttribute('aria-pressed', sel === 'early' ? 'true' : 'false');
        const br = $('chBadgeRelease'), be = $('chBadgeEarly');
        if (br) br.textContent = sel === 'release' ? '· ' + I18N.selected : '';
        if (be) be.textContent = sel === 'early' ? '· ' + I18N.selected : '';
    }

    function renderState(data) {
        lastData = data;
        markSelected();
        setTimeout(fitConsole, 0);   // высота карточек могла измениться
        setTimeout(function () {
            ['chReleaseVersion', 'chReleaseStatus', 'chReleaseExtra', 'chEarlyVersion', 'chEarlyStatus', 'chEarlyExtra', 'updateStatusText'].forEach(function (id) {
                const e = $(id);
                if (e) e.title = e.textContent;
            });
        }, 0);
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

    // --- Консоль занимает всё оставшееся место до низа окна (не уходит за экран) ---
    function fitConsole() {
        const box = $('updateConsole');
        if (!box || box.offsetParent === null) return;   // вкладка скрыта
        // Прокручивается не окно, а область .settings-content (под ней ещё подвал страницы), поэтому
        // считаем по ней: высота видимой области минус положение консоли внутри неё и нижний отступ
        const cont = box.closest('.settings-content');
        let h;
        if (cont) {
            const top = box.getBoundingClientRect().top - cont.getBoundingClientRect().top + cont.scrollTop;
            const pad = parseFloat(getComputedStyle(cont).paddingBottom) || 0;
            h = cont.clientHeight - top - pad - 4;
        } else {
            h = window.innerHeight - (box.getBoundingClientRect().top + window.scrollY) - 24;
        }
        box.style.height = Math.max(160, Math.floor(h)) + 'px';
    }
    window.addEventListener('resize', fitConsole);
    window.fitUpdateConsole = fitConsole;

    // --- Сообщения в шапке раздела «Инструменты» ---
    function toastTitle() { return document.querySelector('#section-tools .section-title'); }

    // Постоянное сообщение «Доступно обновление: файлов N»: висит, пока обновление не установлено, число обновляется
    function setPersistentToast(text) {
        let el = $('updateToast');
        if (!text) { if (el) el.remove(); return; }
        const title = toastTitle();
        if (!title) return;
        if (!el) {
            el = document.createElement('div');
            el.id = 'updateToast';
            el.className = 'section-message warning update-persist';
            title.appendChild(el);
        }
        el.textContent = text;
    }

    // Временное сообщение «Обновлений нет» (5 секунд)
    let tempToastTimer = null;
    function tempToast(text) {
        const title = toastTitle();
        if (!title) return;
        title.querySelectorAll('.update-temp').forEach(function (m) { m.remove(); });
        const msg = document.createElement('div');
        msg.className = 'section-message success update-temp';
        msg.textContent = text;
        title.appendChild(msg);
        clearTimeout(tempToastTimer);
        tempToastTimer = setTimeout(function () { msg.remove(); }, 5200);
    }

    // По результату проверки выбранного канала (сбой проверки сообщение не трогает).
    // manual — проверка по кнопке: при отсутствии обновлений показывается временное сообщение.
    function renderToast(data, manual) {
        const c = data && data.channels ? data.channels[selectedChannel()] : null;
        if (!c || !c.success) return;
        if (c.available) {
            const files = (c.new_count || 0) + (c.changed_count || 0);
            setPersistentToast(selectedChannel() === 'release' ? fmt(I18N.toastRelease, c.version, files) : fmt(I18N.toastEarly, files));
        } else {
            setPersistentToast('');
            if (manual) tempToast(I18N.toastNone);
        }
    }
    window.vlmcUpdateToast = renderToast;

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
            renderToast(data, true);
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
                setPersistentToast('');
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

    // --- Автосохранение (канал, автопроверка, интервал) с подписью рядом ---
    const savedTimers = {};
    function saveAuto(msgId, okText) {
        const el = $(msgId);
        if (!el) return;
        post({
            ajax: 'save_update_settings',
            type: selectedChannel(),
            auto_check: $('autoCheckEnabled').checked ? 'true' : 'false',
            check_interval: $('autoCheckInterval').value
        }).then(function (data) {
            el.style.color = data.success ? '#2ecc71' : '#e74c3c';
            el.textContent = data.success ? '✔ ' + okText : '❌ ' + (data.message || I18N.saveError);
        }).catch(function () {
            el.style.color = '#e74c3c';
            el.textContent = '❌ ' + I18N.saveError;
        }).then(function () {
            el.style.visibility = 'visible';
            clearTimeout(savedTimers[msgId]);
            savedTimers[msgId] = setTimeout(function () { el.style.visibility = 'hidden'; }, 4000);
        });
    }

    function chooseChannel(name) {
        const inp = $('updateType');
        if (!inp || inp.value === name) return;
        inp.value = name;
        renderState(lastData);
        renderToast(lastData, false);
        markSelected();
        saveAuto('channelSavedMsg', I18N.channelSaved);
    }

    // --- Токен GitHub ---
    function showTokenStatus(data) {
        const st = $('tokenStatus');
        if (!st) return;
        st.style.color = data.success ? '#2ecc71' : '#e74c3c';
        const base = data.set ? I18N.tokenSet.replace('%s', data.hint) : I18N.tokenUnset;
        st.textContent = (data.message || '') + ' (' + base + ')';
        st.title = st.textContent;
    }

    function saveGithubToken(clear) {
        const inp = $('githubToken');
        const value = clear ? '' : (inp.value || '').trim();
        if (!clear && value === '') {
            showTokenStatus({ success: false, message: I18N.tokenEmpty, set: false, hint: '' });
            return;
        }
        post({ ajax: 'save_github_token', token: value }).then(function (data) {
            if (data.success) inp.value = '';
            showTokenStatus(data);
        }).catch(function () { showTokenStatus({ success: false, message: I18N.saveError }); });
    }

    // Подтянуть сохранённый результат автопроверки (при свежем — без запросов к GitHub)
    function loadState() {
        post({ ajax: 'update_auto_check' }).then(function (data) {
            if (data && data.success && data.enabled) {
                renderState(data);
                renderToast(data, false);
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

        if (checkBtn) checkBtn.onclick = checkForUpdates;
        if (gitHubBtn) gitHubBtn.onclick = checkGitHubAccess;
        if (runBtn) runBtn.onclick = runUpdate;
        if (startBtn) startBtn.onclick = startUpdate;
        [['chCardRelease', 'release'], ['chCardEarly', 'early']].forEach(function (c) {
            const card = $(c[0]);
            if (!card) return;
            card.onclick = function () { chooseChannel(c[1]); };
            card.onkeydown = function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); chooseChannel(c[1]); }
            };
        });
        const autoOn = $('autoCheckEnabled'), autoInt = $('autoCheckInterval');
        if (autoOn) autoOn.onchange = function () { saveAuto('autoSavedMsg', I18N.settingsSaved); };
        if (autoInt) autoInt.onchange = function () { saveAuto('autoSavedMsg', I18N.settingsSaved); };
        const tokBtn = $('saveTokenBtn');
        const tokClr = $('clearTokenBtn');
        if (tokBtn) tokBtn.onclick = function () { saveGithubToken(false); };
        if (tokClr) tokClr.onclick = function () { saveGithubToken(true); };

        markSelected();
        fitConsole();
        if (!lastData) loadState();
    }

    window.initUpdateTab = initUpdateTab;
    window.checkForUpdates = checkForUpdates;
    window.checkGitHubAccess = checkGitHubAccess;
    window.runUpdate = runUpdate;
    window.startUpdate = startUpdate;
    window.closeUpdateConfirmModal = closeUpdateConfirmModal;

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