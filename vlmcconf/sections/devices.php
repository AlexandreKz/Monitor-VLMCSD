<?php
// ============================================
// ФАЙЛ: sections/devices.php
// ВЕРСИЯ: 3.0.0
// ДАТА: 2026-11-19
// @description: Секция "Устройства" с групповыми операциями
// @description: "Devices" section with bulk operations
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'devices.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права
$canViewDevices = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_DEVICES_VIEW);
$canEditDevices = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_DEVICES_EDIT);

// Последняя активность и число запросов из лога (по имени устройства без учёта регистра)
require_once __DIR__ . '/../vlmcinc/reports.php';
$dvActivity = [];
foreach (reportDeviceSummary($GLOBALS['fullLogPath'] ?? '') as $dvName => $dvInfo) {
    $dvActivity[function_exists('mb_strtolower') ? mb_strtolower((string)$dvName, 'UTF-8') : strtolower((string)$dvName)] = $dvInfo;
}
$dvDevices = [];
foreach ($allDevices as $dvItem) {
    $dvKey = function_exists('mb_strtolower') ? mb_strtolower((string)$dvItem['name'], 'UTF-8') : strtolower((string)$dvItem['name']);
    $dvItem['last'] = $dvActivity[$dvKey]['last'] ?? '';
    $dvItem['requests'] = $dvActivity[$dvKey]['count'] ?? 0;
    $dvDevices[] = $dvItem;
}

// Если нет прав на просмотр — не показываем секцию
if (!$canViewDevices) {
    echo '<div class="settings-section" id="section-devices"><div class="section-title"><span>📱 ' . __('devices_title') . '</span></div><div style="text-align: center; padding: 40px; color: ' . $themeCSS['muted'] . ';">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
require_once __DIR__ . '/../vlmcinc/section_ui.php';
vlmcSectionUi($themeCSS);
?>

<div id="section-devices" class="settings-section <?= $activeSection === 'devices' ? 'active' : '' ?>">
    <div class="section-title">
        <span>📱 <?= __('devices_title') ?></span>
        <?php if ($activeSection === 'devices' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Форма добавления устройства -->
    <?php if ($canEditDevices): ?>
    <div class="settings-card" style="margin-bottom: 15px;">
        <div class="settings-card-title">➕ <?= __('devices_add') ?></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_device">
            <div style="display: grid; grid-template-columns: 2fr 1fr 2fr auto; gap: 10px; align-items: flex-end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label><?= __('label_device_name') ?></label>
                    <input type="text" name="deviceName" class="form-control" placeholder="<?= __('devices_name_placeholder') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label><?= __('label_device_group') ?></label>
                    <select name="deviceGroup" class="form-control">
                        <?php foreach ($config['groupColors'] as $group => $data): ?>
                        <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label><?= __('label_device_comment') ?></label>
                    <input type="text" name="deviceComment" class="form-control" placeholder="<?= __('devices_comment') ?>">
                </div>
                <button type="submit" class="btn btn-primary">➕ <?= __('add') ?></button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Список всех устройств -->
    <div class="settings-card dv-card" style="display: flex; flex-direction: column; flex: 1; min-height: 0; margin-bottom: 0;">
        <div class="settings-card-title sec-th">📋 <?= __('devices_list') ?><span class="sec-msg" id="dvMsg"></span></div>

        <!-- Поиск и фильтры -->
        <div class="dv-filters">
            <div class="dv-search"<?= vlmcTip('tip_dv_search') ?>>
                <svg class="dv-search-ic" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="text" id="dvSearch" class="dv-search-in" autocomplete="off" spellcheck="false" placeholder="<?= htmlspecialchars(__('devices_search_ph')) ?>">
                <button type="button" class="dv-search-x" id="dvSearchClear" hidden>✕</button>
            </div>
            <button type="button" class="sec-btn" id="dvReset"<?= vlmcTip('tip_dv_reset') ?>>↺ <?= __('devices_reset') ?></button>
            <select id="deviceGroupFilter" class="form-control dv-sel"<?= vlmcTip('tip_dv_group') ?>>
                <option value="all"><?= __('devices_all_groups') ?></option>
                <?php foreach ($config['groupColors'] as $group => $data): ?>
                <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="dvAdded" class="form-control dv-sel"<?= vlmcTip('tip_dv_added') ?>>
                <option value="any"><?= __('devices_f_added_any') ?></option>
                <option value="today"><?= __('devices_f_added_today') ?></option>
                <option value="7"><?= __('devices_f_added_7') ?></option>
                <option value="30"><?= __('devices_f_added_30') ?></option>
                <option value="90"><?= __('devices_f_added_90') ?></option>
                <option value="old90"><?= __('devices_f_added_old90') ?></option>
                <option value="old365"><?= __('devices_f_added_old365') ?></option>
            </select>
            <select id="dvActive" class="form-control dv-sel"<?= vlmcTip('tip_dv_active') ?>>
                <option value="any"><?= __('devices_f_act_any') ?></option>
                <option value="a7"><?= __('devices_f_act_a7') ?></option>
                <option value="a30"><?= __('devices_f_act_a30') ?></option>
                <option value="i30"><?= __('devices_f_act_i30') ?></option>
                <option value="i90"><?= __('devices_f_act_i90') ?></option>
                <option value="never"><?= __('devices_f_act_never') ?></option>
            </select>
            <select id="deviceSort" class="form-control dv-sel"<?= vlmcTip('tip_dv_sort') ?>>
                <option value="name"><?= __('devices_sort_name') ?></option>
                <option value="group"><?= __('devices_sort_group') ?></option>
                <option value="date_asc"><?= __('devices_sort_date_asc') ?></option>
                <option value="date_desc"><?= __('devices_sort_date_desc') ?></option>
                <option value="last_desc"><?= __('devices_sort_last_desc') ?></option>
                <option value="last_asc"><?= __('devices_sort_last_asc') ?></option>
                <option value="req_desc"><?= __('devices_sort_req_desc') ?></option>
            </select>
        </div>

        <!-- Массовые действия и счётчики -->
        <div class="dv-bar">
            <?php if ($canEditDevices): ?>
            <div class="dv-mass">
                <button type="button" class="sec-btn" id="selectAllBtn"<?= vlmcTip('tip_dv_select_all') ?>>☐ <?= __('devices_select_all') ?></button>
                <button type="button" class="sec-btn" id="dvMoveBtn" disabled<?= vlmcTip('tip_dv_move') ?>>↔️ <?= __('devices_move_selected') ?></button>
                <button type="button" class="sec-btn" id="dvCommentBtn" disabled<?= vlmcTip('tip_dv_comment') ?>>💬 <?= __('devices_comment_btn') ?></button>
                <button type="button" class="sec-btn danger" id="dvDeleteBtn" disabled<?= vlmcTip('tip_dv_delete') ?>>🗑️ <?= __('delete') ?></button>
            </div>
            <?php endif; ?>
            <div class="dv-count">
                <span id="deviceCount"></span>
                <span><?= __('devices_selected') ?>: <b id="selectedCount">0</b></span>
            </div>
        </div>

        <!-- Список устройств -->
        <div class="devices-list" id="devicesList" style="flex: 1; overflow-y: auto; min-height: 0; padding-right: 5px;"></div>
    </div>
</div>

<?php if ($canEditDevices): ?>
<!-- Окно: перемещение -->
<div id="dvMoveModal" class="dv-modal" style="display: none;">
    <div class="dv-modal-box" style="--mc: #f59e0b; --mt: #f59e0b;">
        <div class="dv-modal-head"><h2>↔️ <?= __('devices_move_title') ?></h2><span class="dv-modal-x" data-close="dvMoveModal">&times;</span></div>
        <div class="dv-modal-body">
            <p id="dvMoveText"></p>
            <select id="dvMoveGroup" class="form-control">
                <option value=""><?= __('devices_move_to') ?></option>
                <?php foreach ($config['groupColors'] as $group => $data): ?>
                <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="sec-msg" id="dvMoveErr"></div>
        </div>
        <div class="dv-modal-foot">
            <button type="button" class="sec-btn" data-close="dvMoveModal"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" id="dvMoveGo"><?= __('devices_move_selected') ?></button>
        </div>
    </div>
</div>

<!-- Окно: комментарий -->
<div id="dvCommentModal" class="dv-modal" style="display: none;">
    <div class="dv-modal-box" style="--mc: #8b5cf6; --mt: #8b5cf6;">
        <div class="dv-modal-head"><h2>💬 <?= __('devices_comment_title') ?></h2><span class="dv-modal-x" data-close="dvCommentModal">&times;</span></div>
        <div class="dv-modal-body">
            <p id="dvCommentText"></p>
            <select id="dvCommentMode" class="form-control">
                <option value="set"><?= __('devices_comment_set') ?></option>
                <option value="append"><?= __('devices_comment_append') ?></option>
                <option value="clear"><?= __('devices_comment_clear') ?></option>
            </select>
            <input type="text" id="dvCommentText2" class="form-control" maxlength="200" placeholder="<?= htmlspecialchars(__('devices_comment')) ?>">
            <div class="sec-msg" id="dvCommentErr"></div>
        </div>
        <div class="dv-modal-foot">
            <button type="button" class="sec-btn" data-close="dvCommentModal"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" id="dvCommentGo"><?= __('save') ?></button>
        </div>
    </div>
</div>

<!-- Окно: удаление -->
<div id="dvDeleteModal" class="dv-modal" style="display: none;">
    <div class="dv-modal-box" style="--mc: <?= $themeCSS['danger'] ?>; --mt: <?= $themeCSS['dangerText'] ?>;">
        <div class="dv-modal-head"><h2>⚠️ <?= __('devices_del_title') ?></h2><span class="dv-modal-x" data-close="dvDeleteModal">&times;</span></div>
        <div class="dv-modal-body">
            <p id="dvDeleteText"></p>
            <div class="dv-modal-list" id="dvDeleteList"></div>
            <div class="sec-msg" id="dvDeleteErr"></div>
        </div>
        <div class="dv-modal-foot">
            <button type="button" class="sec-btn" data-close="dvDeleteModal"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn danger" id="dvDeleteGo">🗑️ <?= __('delete') ?></button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Стили для списка устройств */
.devices-list { display: flex; flex-direction: column; gap: 4px; }
.device-item { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 6px 10px; display: flex; align-items: center; gap: 10px; transition: all 0.2s; min-height: 36px; }
.device-item:hover { box-shadow: <?= $themeCSS['shadow'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.device-item.sel { border-color: <?= $themeCSS['primary'] ?>; background: <?= $themeCSS['primary'] ?>14; }
.device-checkbox { flex-shrink: 0; width: 16px; height: 16px; cursor: pointer; margin: 0; accent-color: <?= $themeCSS['primary'] ?>; }
.device-color { width: 16px; height: 16px; border-radius: 4px; flex-shrink: 0; }
.device-info { flex: 2; line-height: 1.2; min-width: 0; }
.device-name { font-weight: 600; font-size: 12px; }
.device-comment { font-size: 10px; color: <?= $themeCSS['muted'] ?>; margin-top: 2px; }
.device-meta { flex: 1; font-size: 11px; color: <?= $themeCSS['muted'] ?>; }
.device-date { font-size: 10px; color: <?= $themeCSS['muted'] ?>; font-family: 'JetBrains Mono', monospace; white-space: nowrap; }
.device-last { width: 200px; flex-shrink: 0; font-size: 11px; line-height: 1.25; white-space: nowrap; }
.device-last small { display: block; font-size: 10px; color: <?= $themeCSS['muted'] ?>; }
.device-last.fresh { color: <?= $themeCSS['okText'] ?>; }
.device-last.stale { color: <?= $themeCSS['warning'] ?>; }
.device-last.none { color: <?= $themeCSS['muted'] ?>; }
.device-actions { display: flex; gap: 4px; flex-shrink: 0; }
.edit-device-btn, .delete-device-btn { padding: 3px 6px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; transition: all 0.2s; min-width: 24px; }
.edit-device-btn { background: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['onPrimary'] ?>; }
.delete-device-btn { background: <?= $themeCSS['danger'] ?>; color: <?= $themeCSS['onDanger'] ?>; }

/* Поиск, фильтры, массовые действия */
.dv-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto; gap: 8px; margin-bottom: 10px; align-items: center; flex-shrink: 0; }
.dv-search { position: relative; grid-column: 1 / 5; }
#dvReset { grid-column: 5; }
.dv-search-in { width: 100%; height: 32px; box-sizing: border-box; padding: 0 30px 0 34px; border-radius: 16px; border: 1px solid <?= $themeCSS['border'] ?>; background: <?= $themeCSS['input'] ?>; color: <?= $themeCSS['text'] ?>; font: inherit; font-size: 13px; outline: none; transition: border-color .15s, box-shadow .15s; }
.dv-search-in::placeholder { color: <?= $themeCSS['muted'] ?>; }
.dv-search-in:focus { border-color: <?= $themeCSS['primary'] ?>; box-shadow: 0 0 0 3px <?= $themeCSS['primary'] ?>40; }
.dv-search-ic { position: absolute; left: 12px; top: 8px; color: <?= $themeCSS['muted'] ?>; pointer-events: none; }
.dv-search:focus-within .dv-search-ic { color: <?= $themeCSS['primary'] ?>; }
.dv-search-x { position: absolute; right: 7px; top: 5px; width: 22px; height: 22px; border: 0; border-radius: 50%; background: <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; cursor: pointer; font-size: 10px; line-height: 22px; padding: 0; }
.dv-search-x:hover { background: <?= $themeCSS['hover'] ?>; }
.dv-search-x[hidden] { display: none; }
.dv-sel { width: 100%; min-width: 0; height: 32px; padding-top: 0; padding-bottom: 0; }
.dv-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; flex-shrink: 0; }
.dv-mass { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.sec-btn:disabled { opacity: .45; cursor: not-allowed; }
.sec-btn:disabled:hover { background: <?= $themeCSS['input'] ?>; border-color: <?= $themeCSS['border'] ?>; }
.sec-btn.danger:disabled:hover { background: <?= $themeCSS['danger'] ?>; border-color: <?= $themeCSS['danger'] ?>; }
.dv-count { margin-left: auto; display: flex; gap: 14px; font-size: 12px; color: <?= $themeCSS['muted'] ?>; }
.dv-count b { color: <?= $themeCSS['text'] ?>; }

/* Окна массовых действий: рамка 2 px и заголовок в цвете важности */
.dv-modal { position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; }
.dv-modal-box { background: <?= $themeCSS['card'] ?>; border: 2px solid var(--mc, <?= $themeCSS['primary'] ?>); border-radius: 12px; width: 460px; max-width: 92%; color: <?= $themeCSS['text'] ?>; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.dv-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; }
.dv-modal-head h2 { margin: 0; font-size: 17px; font-weight: 600; color: var(--mt, <?= $themeCSS['accentText'] ?>); }
.dv-modal-x { color: <?= $themeCSS['muted'] ?>; font-size: 24px; font-weight: bold; cursor: pointer; line-height: 1; }
.dv-modal-x:hover { color: <?= $themeCSS['text'] ?>; }
.dv-modal-body { padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; font-size: 13px; }
.dv-modal-body p { margin: 0; line-height: 1.5; }
.dv-modal-list { font-size: 12px; color: <?= $themeCSS['muted'] ?>; background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 8px 10px; max-height: 110px; overflow-y: auto; word-break: break-word; }
.dv-modal-foot { padding: 12px 20px; border-top: 1px solid <?= $themeCSS['border'] ?>; display: flex; justify-content: flex-end; gap: 10px; }
</style>

<script>
// Данные из PHP
const allDevicesData = <?= json_encode($dvDevices, JSON_UNESCAPED_UNICODE) ?>;
const canEditDevices = <?= $canEditDevices ? 'true' : 'false' ?>;
const groupColors = <?= json_encode($config['groupColors'], JSON_UNESCAPED_UNICODE) ?>;
const dvLocale = '<?= ($config['language'] ?? 'ru') === 'en' ? 'en-GB' : 'ru-RU' ?>';
const dvLang = <?= json_encode([
    'edit' => __('edit'), 'delete' => __('delete'), 'readonly' => __('readonly'), 'deleteConfirm' => __('devices_delete_confirm'),
    'showing' => __('showing'), 'of' => __('of'), 'empty' => __('devices_empty'), 'nothing' => __('devices_nothing_found'),
    'selectAll' => __('devices_select_all'), 'deselectAll' => __('devices_deselect_all'),
    'moveText' => __('devices_move_text'), 'commentText' => __('devices_comment_text'), 'delText' => __('devices_del_text'),
    'pickGroup' => __('devices_select_group'), 'commentEmpty' => __('devices_comment_empty'), 'saveError' => __('msg_save_error'),
    'never' => __('devices_never'), 'today' => __('devices_today'), 'yesterday' => __('devices_yesterday'), 'daysAgo' => __('devices_days_ago'),
    'requests' => __('devices_requests'), 'more' => __('devices_and_more'), 'moved' => __('devices_moved'),
], JSON_UNESCAPED_UNICODE) ?>;

let selectedDevices = new Set();
const $dv = id => document.getElementById(id);
const DAY = 86400000;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}
function dvParse(s) {
    if (!s) return null;
    const t = Date.parse(String(s).replace(' ', 'T'));
    return isNaN(t) ? null : t;
}
function formatDate(dateStr) {
    const t = dvParse(dateStr);
    if (t === null) return dateStr ? dateStr : '—';
    return new Date(t).toLocaleString(dvLocale, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
function dvLastInfo(d) {
    const t = dvParse(d.last);
    if (t === null) return { cls: 'none', text: dvLang.never, sub: '' };
    const days = Math.floor((Date.now() - t) / DAY);
    let text;
    if (days <= 0) text = dvLang.today;
    else if (days === 1) text = dvLang.yesterday;
    else text = dvLang.daysAgo.replace('%d', days);
    return { cls: days <= 7 ? 'fresh' : (days > 30 ? 'stale' : ''), text: text, sub: formatDate(d.last) };
}

// ---- Фильтры ----
function dvFilterState() {
    return {
        q: $dv('dvSearch').value.trim().toLowerCase(),
        group: $dv('deviceGroupFilter').value,
        added: $dv('dvAdded').value,
        active: $dv('dvActive').value,
        sort: $dv('deviceSort').value
    };
}
function dvMatch(d, f, now) {
    if (f.q) {
        const hay = (d.name + ' ' + (d.comment || '')).toLowerCase();
        if (hay.indexOf(f.q) === -1) return false;
    }
    if (f.group !== 'all' && d.group !== f.group) return false;
    if (f.added !== 'any') {
        const t = dvParse(d.added);
        if (t === null) return false;
        const age = (now - t) / DAY;
        if (f.added === 'today') { if (new Date(t).toDateString() !== new Date(now).toDateString()) return false; }
        else if (f.added === 'old90') { if (age <= 90) return false; }
        else if (f.added === 'old365') { if (age <= 365) return false; }
        else if (age > parseInt(f.added, 10)) return false;
    }
    if (f.active !== 'any') {
        const t = dvParse(d.last);
        const age = t === null ? null : (now - t) / DAY;
        if (f.active === 'never') { if (t !== null) return false; }
        else if (f.active === 'a7') { if (age === null || age > 7) return false; }
        else if (f.active === 'a30') { if (age === null || age > 30) return false; }
        else if (f.active === 'i30') { if (age === null || age <= 30) return false; }
        else if (f.active === 'i90') { if (age === null || age <= 90) return false; }
    }
    return true;
}
function getSortedDevices() {
    const f = dvFilterState();
    const now = Date.now();
    const list = allDevicesData.filter(d => dvMatch(d, f, now));
    const lastOf = d => { const t = dvParse(d.last); return t === null ? -1 : t; };
    list.sort((a, b) => {
        switch (f.sort) {
            case 'group': return a.group.localeCompare(b.group) || a.name.localeCompare(b.name);
            case 'date_asc': return (a.added || '').localeCompare(b.added || '');
            case 'date_desc': return (b.added || '').localeCompare(a.added || '');
            case 'last_desc': return lastOf(b) - lastOf(a) || a.name.localeCompare(b.name);
            case 'last_asc': return lastOf(a) - lastOf(b) || a.name.localeCompare(b.name);
            case 'req_desc': return (b.requests || 0) - (a.requests || 0) || a.name.localeCompare(b.name);
            default: return a.name.localeCompare(b.name);
        }
    });
    return list;
}
function renderDevicesList() {
    const devices = getSortedDevices();
    const container = $dv('devicesList');
    // выбор не должен «прятаться» за фильтром: снимаем отметку с устройств, которых не видно
    const visible = new Set(devices.map(d => d.name));
    selectedDevices.forEach(n => { if (!visible.has(n)) selectedDevices.delete(n); });

    $dv('deviceCount').textContent = `${dvLang.showing} ${devices.length} ${dvLang.of} ${allDevicesData.length}`;
    $dv('dvSearchClear').hidden = $dv('dvSearch').value === '';

    if (devices.length === 0) {
        container.innerHTML = `<div class="sec-empty">${escapeHtml(allDevicesData.length ? dvLang.nothing : dvLang.empty)}</div>`;
        dvUpdateSelection();
        return;
    }
    let html = '';
    devices.forEach(d => {
        const color = (groupColors[d.group] && groupColors[d.group].color) || '#888888';
        const sel = selectedDevices.has(d.name);
        const li = dvLastInfo(d);
        const n = escapeHtml(d.name);
        html += `
            <div class="device-item${sel ? ' sel' : ''}" data-group="${escapeHtml(d.group)}">
                ${canEditDevices ? `<input type="checkbox" class="device-checkbox" data-name="${n}" ${sel ? 'checked' : ''}>` : ''}
                <div class="device-color" style="background: ${color};"></div>
                <div class="device-info">
                    <div class="device-name">${n}</div>
                    ${d.comment ? `<div class="device-comment">${escapeHtml(d.comment)}</div>` : ''}
                </div>
                <div class="device-meta">${escapeHtml(d.group)}</div>
                <div class="device-last ${li.cls}">${escapeHtml(li.text)}<small>${[li.sub ? escapeHtml(li.sub) : '', d.requests ? escapeHtml(dvLang.requests) + ': ' + d.requests : ''].filter(Boolean).join(' · ')}</small></div>
                <div class="device-date">${formatDate(d.added)}</div>
                <div class="device-actions">
                    ${canEditDevices ? `
                    <button type="button" class="edit-device-btn" data-act="edit" data-name="${n}" data-group="${escapeHtml(d.group)}" data-comment="${escapeHtml(d.comment || '')}" data-tip="${escapeHtml(dvLang.edit)}">✎</button>
                    <form method="POST" style="display: inline;" onsubmit="return confirm(${escapeHtml(JSON.stringify(dvLang.deleteConfirm))});">
                        <input type="hidden" name="action" value="delete_device">
                        <input type="hidden" name="deviceName" value="${n}">
                        <input type="hidden" name="deviceGroup" value="${escapeHtml(d.group)}">
                        <button type="submit" class="delete-device-btn" data-tip="${escapeHtml(dvLang.delete)}">✕</button>
                    </form>` : `<span style="color: <?= $themeCSS['muted'] ?>; font-size: 10px;">${escapeHtml(dvLang.readonly)}</span>`}
                </div>
            </div>`;
    });
    container.innerHTML = html;
    dvUpdateSelection();
}

// ---- Выбор и массовые действия ----
function dvUpdateSelection() {
    const cnt = selectedDevices.size;
    $dv('selectedCount').textContent = cnt;
    ['dvMoveBtn', 'dvCommentBtn', 'dvDeleteBtn'].forEach(id => { const b = $dv(id); if (b) b.disabled = cnt === 0; });
    const all = $dv('selectAllBtn');
    if (all) {
        const boxes = document.querySelectorAll('.device-checkbox');
        const allOn = boxes.length > 0 && Array.from(boxes).every(c => c.checked);
        all.textContent = (allOn ? '✓ ' + dvLang.deselectAll : '☐ ' + dvLang.selectAll);
        all.dataset.all = allOn ? '1' : '';
    }
    document.querySelectorAll('.device-item').forEach(el => {
        const cb = el.querySelector('.device-checkbox');
        el.classList.toggle('sel', !!(cb && cb.checked));
    });
}
function dvToast(text, ok) {
    const m = $dv('dvMsg');
    m.textContent = text;
    m.className = 'sec-msg ' + (ok ? 'ok' : 'err');
    clearTimeout(dvToast.t);
    dvToast.t = setTimeout(() => { m.className = 'sec-msg'; m.textContent = ''; }, 5000);
}
function dvOpen(id) { $dv(id).style.display = 'flex'; }
function dvClose(id) { $dv(id).style.display = 'none'; }
function dvNames() { return Array.from(selectedDevices); }
function dvPost(action, extra, errId, done) {
    const fd = new FormData();
    fd.append('ajax', action);
    fd.append('device_names', JSON.stringify(dvNames()));
    Object.keys(extra).forEach(k => fd.append(k, extra[k]));
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { done(data); }
            else { const e = $dv(errId); e.textContent = data.message || dvLang.saveError; e.className = 'sec-msg err'; }
        })
        .catch(() => { const e = $dv(errId); e.textContent = dvLang.saveError; e.className = 'sec-msg err'; });
}
function dvFinish(modal, data) {
    dvClose(modal);
    selectedDevices.clear();
    dvToast(data.message, true);
    renderDevicesList();
}

document.addEventListener('DOMContentLoaded', function () {
    ['dvSearch'].forEach(id => $dv(id).addEventListener('input', renderDevicesList));
    ['deviceGroupFilter', 'dvAdded', 'dvActive', 'deviceSort'].forEach(id => $dv(id).addEventListener('change', renderDevicesList));
    $dv('dvSearchClear').addEventListener('click', () => { $dv('dvSearch').value = ''; renderDevicesList(); $dv('dvSearch').focus(); });
    $dv('dvReset').addEventListener('click', () => {
        $dv('dvSearch').value = ''; $dv('deviceGroupFilter').value = 'all'; $dv('dvAdded').value = 'any';
        $dv('dvActive').value = 'any'; $dv('deviceSort').value = 'name';
        renderDevicesList();
    });

    $dv('devicesList').addEventListener('change', function (e) {
        if (!e.target.classList.contains('device-checkbox')) return;
        const name = e.target.dataset.name;
        if (e.target.checked) selectedDevices.add(name); else selectedDevices.delete(name);
        dvUpdateSelection();
    });
    $dv('devicesList').addEventListener('click', function (e) {
        const b = e.target.closest('[data-act="edit"]');
        if (b && window.parentEditDevice) window.parentEditDevice(b.dataset.name, b.dataset.group, b.dataset.comment);
    });

    if (!canEditDevices) { renderDevicesList(); return; }

    $dv('selectAllBtn').addEventListener('click', function () {
        const turnOn = !this.dataset.all;
        document.querySelectorAll('.device-checkbox').forEach(cb => {
            cb.checked = turnOn;
            if (turnOn) selectedDevices.add(cb.dataset.name); else selectedDevices.delete(cb.dataset.name);
        });
        dvUpdateSelection();
    });
    document.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', () => dvClose(el.dataset.close)));
    ['dvMoveModal', 'dvCommentModal', 'dvDeleteModal'].forEach(id => $dv(id).addEventListener('mousedown', e => { if (e.target === $dv(id)) dvClose(id); }));

    // переместить
    $dv('dvMoveBtn').addEventListener('click', () => {
        $dv('dvMoveText').textContent = dvLang.moveText.replace('{count}', selectedDevices.size);
        $dv('dvMoveGroup').value = '';
        $dv('dvMoveErr').className = 'sec-msg';
        dvOpen('dvMoveModal');
    });
    $dv('dvMoveGo').addEventListener('click', () => {
        const g = $dv('dvMoveGroup').value;
        if (!g) { const e = $dv('dvMoveErr'); e.textContent = dvLang.pickGroup; e.className = 'sec-msg err'; return; }
        const names = new Set(dvNames());
        dvPost('mass_move_devices', { target_group: g }, 'dvMoveErr', data => {
            allDevicesData.forEach(d => { if (names.has(d.name)) d.group = g; });
            dvFinish('dvMoveModal', data);
        });
    });

    // комментарий
    function commentSync() { $dv('dvCommentText2').style.display = $dv('dvCommentMode').value === 'clear' ? 'none' : ''; }
    $dv('dvCommentMode').addEventListener('change', commentSync);
    $dv('dvCommentBtn').addEventListener('click', () => {
        $dv('dvCommentText').textContent = dvLang.commentText.replace('{count}', selectedDevices.size);
        $dv('dvCommentMode').value = 'set'; $dv('dvCommentText2').value = '';
        $dv('dvCommentErr').className = 'sec-msg';
        commentSync();
        dvOpen('dvCommentModal');
        $dv('dvCommentText2').focus();
    });
    $dv('dvCommentGo').addEventListener('click', () => {
        const mode = $dv('dvCommentMode').value, text = $dv('dvCommentText2').value.trim();
        if (mode !== 'clear' && text === '') { const e = $dv('dvCommentErr'); e.textContent = dvLang.commentEmpty; e.className = 'sec-msg err'; return; }
        const names = new Set(dvNames());
        dvPost('mass_comment_devices', { mode: mode, comment: text }, 'dvCommentErr', data => {
            allDevicesData.forEach(d => {
                if (!names.has(d.name)) return;
                const old = d.comment || '';
                d.comment = mode === 'clear' ? '' : (mode === 'append' ? (old ? old + '; ' + text : text) : text);
                d.comment = d.comment.substring(0, 200);
            });
            dvFinish('dvCommentModal', data);
        });
    });

    // удалить
    $dv('dvDeleteBtn').addEventListener('click', () => {
        const names = dvNames();
        $dv('dvDeleteText').textContent = dvLang.delText.replace('{count}', names.length);
        const shown = names.slice(0, 8).join(', ');
        $dv('dvDeleteList').textContent = shown + (names.length > 8 ? ' ' + dvLang.more.replace('{n}', names.length - 8) : '');
        $dv('dvDeleteErr').className = 'sec-msg';
        dvOpen('dvDeleteModal');
    });
    $dv('dvDeleteGo').addEventListener('click', () => {
        const names = new Set(dvNames());
        dvPost('mass_delete_devices', {}, 'dvDeleteErr', data => {
            for (let i = allDevicesData.length - 1; i >= 0; i--) { if (names.has(allDevicesData[i].name)) allDevicesData.splice(i, 1); }
            dvFinish('dvDeleteModal', data);
        });
    });

    renderDevicesList();
});
</script>
