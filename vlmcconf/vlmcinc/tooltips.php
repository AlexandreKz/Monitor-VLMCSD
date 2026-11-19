<?php
// ============================================
// ФАЙЛ: vlmcinc/tooltips.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-19
// @description: Всплывающие подсказки (пункт 39): атрибут data-tip, один общий скрипт и стиль
// @description: Tooltips (item 39): data-tip attribute, one shared script and style
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'tooltips.php') {
    http_response_code(403);
    exit('Access denied');
}

/**
 * Атрибут подсказки для элемента: <button<?= vlmcTip('tip_key') ?>>.
 * Если перевода нет, подсказка не выводится (ключ в интерфейсе не показываем).
 */
function vlmcTip($key) {
    global $translations;
    if (!isset($translations[$key]) || $translations[$key] === '') {
        return '';
    }
    return ' data-tip="' . htmlspecialchars($translations[$key], ENT_QUOTES, 'UTF-8') . '"';
}


/** Тексты подсказок из таблицы vlmcTipMap(): селектор => текст на текущем языке. */
function vlmcTipTexts() {
    global $translations;
    $out = [];
    foreach (vlmcTipMap() as $sel => $key) {
        if (!empty($translations[$key])) {
            $out[$sel] = $translations[$key];
        }
    }
    return $out;
}


/** Подсказки для существующих элементов: селектор => ключ перевода (применяются скриптом при загрузке страницы). */
function vlmcTipMap() {
    return [
        '.menu-item[onclick*="showSection(\'general\'"]' => 'tip_menu_general',
        '.menu-item[onclick*="showSection(\'groups\'"]' => 'tip_menu_groups',
        '.menu-item[onclick*="showSection(\'devices\'"]' => 'tip_menu_devices',
        '.menu-item[onclick*="showSection(\'security\'"]' => 'tip_menu_security',
        '.menu-item[onclick*="showSection(\'tools\'"]' => 'tip_menu_tools',
        '.menu-item[onclick*="showSection(\'notifications\'"]' => 'tip_menu_notifications',
        '.menu-item[onclick*="showSection(\'integrations\'"]' => 'tip_menu_integrations',
        '.menu-item[onclick*="showSection(\'api\'"]' => 'tip_menu_api',
        '.menu-item[onclick*="showSection(\'stats\'"]' => 'tip_menu_stats',
        '.menu-item[onclick*="showSection(\'audit\'"]' => 'tip_menu_audit',
        '.menu-item[onclick*="showSection(\'info\'"]' => 'tip_menu_info',
        '.menu-item[onclick*="showSection(\'documentation\'"]' => 'tip_menu_documentation',
        '.menu-item.logout-btn' => 'tip_menu_logout',
        '.back-link' => 'tip_back_monitor',
        '.tools-tab-btn[onclick*="project"]' => 'tip_tools_project',
        '.tools-tab-btn[onclick*="update"]' => 'tip_tools_update',
        '.tools-tab-btn[onclick*="\'log\'"]' => 'tip_tools_log',
        '.tools-tab-btn[onclick*="cache"]' => 'tip_tools_cache',
        '.ntf-tab[data-p="ntfChannels"]' => 'tip_ntf_channels',
        '.ntf-tab[data-p="ntfRules"]' => 'tip_ntf_rules',
        '.ntf-tab[data-p="ntfHistory"]' => 'tip_ntf_history',
        '.ntf-tab[data-p="ntfTemplates"]' => 'tip_ntf_templates',
        '.info-tab[data-p="infoStructure"]' => 'tip_info_structure',
        '.info-tab[data-p="infoData"]' => 'tip_info_data',
        '.info-tab[data-p="infoStatus"]' => 'tip_info_status',
        '#stRefresh' => 'tip_st_refresh',
        '.stats-page-btn[data-page="overview"]' => 'tip_st_overview',
        '.stats-page-btn[data-page="reports"]' => 'tip_st_reports',
        '.stat-tab-btn[data-tab="timeline"]' => 'tip_st_timeline',
        '.stat-tab-btn[data-tab="device"]' => 'tip_st_device',
        '#deviceSelect' => 'tip_st_devsel',
        '.period-btn[data-period="day"]' => 'tip_st_p_day',
        '.period-btn[data-period="week"]' => 'tip_st_p_week',
        '.period-btn[data-period="month"]' => 'tip_st_p_month',
        '#reportRun' => 'tip_rep_run',
        '#reportInput' => 'tip_rep_input',
        '.sec-tab-btn[data-for="users"]' => 'tip_sec_users',
        '.sec-tab-btn[data-for="access"]' => 'tip_sec_access',
        '.sec-tab-btn[data-for="firewall"]' => 'tip_sec_fw',
        '.sec-tab-btn[data-for="ad"]' => 'tip_int_ad',
        '.sec-tab-btn[data-for="cloud"]' => 'tip_int_cloud',
        '.sec-tab-btn[data-for="webhook"]' => 'tip_int_wh',
        '.sec-tab-btn[data-for="multiserver"]' => 'tip_int_ms',
        '.sec-tab-btn[data-for="keys"]' => 'tip_api_keys',
        '.sec-tab-btn[data-for="settings"]' => 'tip_api_settings',
        '.sec-tab-btn[data-for="docs"]' => 'tip_api_docs',
        '.sec-tab-btn[data-for="general"]' => 'tip_aud_general',
        '.sec-tab-btn[data-for="bg"]' => 'tip_aud_bg',
        '.sec-sub-btn[data-sub-for="conn"]' => 'tip_ld_conn',
        '.sec-sub-btn[data-sub-for="users"]' => 'tip_ld_users',
        '.sec-sub-btn[data-sub-for="log"]' => 'tip_ld_log',
        'button[onclick*="toolsExportProject(\'clean\')"]' => 'tip_x_clean',
        'button[onclick*="toolsExportProject(\'config\')"]' => 'tip_x_config',
        'button[onclick*="toolsExportProject(\'full\')"]' => 'tip_x_full',
        '.reset-config-btn' => 'tip_x_reset',
        '#toolsStartDate' => 'tip_x_d1',
        '#toolsEndDate' => 'tip_x_d2',
        'button[onclick*="showClearLogModal(\'date_range\')"]' => 'tip_x_clr_range',
        'button[onclick*="showClearLogModal(\'all\')"]' => 'tip_x_clr_all',
        'button[onclick*="toolsBackupLog"]' => 'tip_x_bk',
        '#clearCacheBtn' => 'tip_x_cc',
        '#refreshCacheBtn' => 'tip_x_rc',
        '#checkCacheBtn' => 'tip_x_chk',
        '#clearEmojiCacheBtn' => 'tip_x_ce',
        '#refreshEmojiCacheBtn' => 'tip_x_re',
        '#checkUpdatesBtn' => 'tip_u_chk',
        '#runUpdateBtn' => 'tip_u_run',
        '#checkGitHubBtn' => 'tip_u_gh',
        '#githubToken' => 'tip_u_tok',
        '#saveTokenBtn' => 'tip_u_tsave',
        '#autoCheckInterval' => 'tip_u_int',
        '#autoCheckEnabled' => 'tip_u_auto',
        '#emojiPickerBtnAdd' => 'tip_g_emoji',
        'input[name="groupName"]' => 'tip_g_name',
        'input[name="groupColor"]' => 'tip_g_color',
        '.btn-edit-group' => 'tip_g_edit',
        '#editGroupColor' => 'tip_g_color2',
        'input[name="logPath"]' => 'tip_gen_log',
        '#ntfNewRule' => 'tip_ntf_nrule',
        '#ntfNewChannel' => 'tip_ntf_nch',
        '#auditSearch' => 'tip_a_search',
        '#auditUser' => 'tip_a_user',
        '#auditAction' => 'tip_a_act',
        '#auditResult' => 'tip_a_res',
        '#auditRangeInput' => 'tip_a_range',
        '#auditResetBtn' => 'tip_a_reset',
        '#auditAuto' => 'tip_a_auto',
        '#auditExportBtn' => 'tip_a_exp',
        '#auditPrev' => 'tip_a_prev',
        '#auditNext' => 'tip_a_next',
        '#auditRetSel' => 'tip_a_ret',
        '#auditRetSave' => 'tip_a_retsave',
        '#auditClearBtn' => 'tip_a_clear',
        'button[onclick*="showAddUserModal"]' => 'tip_s_adduser',
        '#sessionTimeoutMin' => 'tip_s_sess',
        '#hideIps' => 'tip_s_hide',
        'button[onclick*="showAddWhitelistIpModal"]' => 'tip_s_wl_add',
        '#pwEnabled' => 'tip_s_pw_on',
        '#pwNewIp' => 'tip_s_pw_ip',
        '#pwNewComment' => 'tip_s_pw_c',
        'button[onclick*="pwAddMine"]' => 'tip_s_pw_mine',
        'button[onclick*="pwSave"]' => 'tip_s_pw_save',
        '#fwBackend' => 'tip_s_fw_b',
        '#fwScope' => 'tip_s_fw_s',
        '#fwNewIp' => 'tip_s_fw_ip',
        '#fwNewReason' => 'tip_s_fw_r',
        'button[onclick*="fwBlockNew"]' => 'tip_s_fw_blk',
        'button[onclick*="fwApply(false)"]' => 'tip_s_fw_ap',
        'button[onclick*="fwApply(true)"]' => 'tip_s_fw_re',
        '.toggle-password-btn' => 'tip_s_eye',
        '#modalUsername' => 'tip_s_u_name',
        '#ldEnabled' => 'tip_l_en',
        '#ldServer' => 'tip_l_srv',
        '#ldSecurity' => 'tip_l_sec',
        '#ldPort' => 'tip_l_port',
        '#ldVerify' => 'tip_l_ver',
        '#ldBase' => 'tip_l_base',
        '#ldSvcUser' => 'tip_l_svc',
        '#ldSvcPass' => 'tip_l_svcp',
        '#ldDomain' => 'tip_l_dom',
        'button[onclick*="ldSave()"]' => 'tip_l_save',
        'button[onclick*="ldTest()"]' => 'tip_l_test',
        '#ldAuth' => 'tip_l_auth',
        '#ldAutoCreate' => 'tip_l_auto',
        '#ldPerms' => 'tip_l_perm',
        'button[onclick*="ldImport()"]' => 'tip_l_imp',
        'button[onclick*="ldSync()"]' => 'tip_l_sync',
        '#ldQuery' => 'tip_l_q',
        'button[onclick*="clEdit(\'\')"]' => 'tip_c_add',
        'button[onclick*="whEdit(\'\')"]' => 'tip_w_add',
        'button[onclick*="msEdit(\'\')"]' => 'tip_m_add',
        '#apiBaseUrl' => 'tip_p_base',
        'button[onclick*="apiNewOpen"]' => 'tip_p_new',
        '#apiKeyName' => 'tip_p_name',
        '#apiKeyIps' => 'tip_p_ips',
        '#apiKeyExpiresShow' => 'tip_p_exp',
        'button[onclick*="apiCreateKey"]' => 'tip_p_create',
        '#apiEnabled' => 'tip_p_on',
        '#apiRate' => 'tip_p_rate',
        'button[onclick*="apiSaveSettings"]' => 'tip_p_save',
        '#apiDocsSearch' => 'tip_p_search',
        '#apiSpecLink' => 'tip_p_spec',
        '.api-copy' => 'tip_p_copy',
        '#editDeviceName' => 'tip_d_name',
        '#editDeviceGroup' => 'tip_d_group',
        '#editDeviceComment' => 'tip_d_comment',
        '#section-general form:has(#selectedTheme) button[type="submit"]' => 'tip_gen_theme',
        '#section-general form:has(input[name="logPath"]) button[type="submit"]' => 'tip_gen_logsave',
        '#section-general form:has(input[name="language"]) button[type="submit"]' => 'tip_gen_langsave',
        '#section-groups form:has(input[name="groupName"]) button[type="submit"]' => 'tip_g_add',
        '#section-devices form:has(input[name="deviceName"]) button[type="submit"]' => 'tip_d_add',
        'button[onclick*="saveSessionSettings"]' => 'tip_s_sess_save',
        '#ldBkAddBtn' => 'tip_l_bk',
        'button[onclick*="ldGroupsOpen"]' => 'tip_l_groups',
        'button[onclick*="secCopy"]' => 'tip_p_copy2',
        '.stats-page-btn[data-page="analytics"]' => 'tip_st_analytics',
        '[data-doc-tab="inst"]' => 'tip_doc_inst',
        '[data-doc-tab="desc"]' => 'tip_doc_desc',
        '[data-doc-lang="ru"]' => 'tip_doc_ru',
        '[data-doc-lang="en"]' => 'tip_doc_en',
        '#downloadDocBtn' => 'tip_doc_dl',
        'button[onclick="filterLog()"]' => 'tip_m_search',
        'button[onclick="resetSearch()"]' => 'tip_m_reset',
        '#searchInput' => 'tip_m_input',
        '#eventFilter' => 'tip_m_event',
        '#groupFilter' => 'tip_m_group',
        '#scrollBottomBtn' => 'tip_m_bottom',
        '.copy-ip-btn' => 'tip_m_copy',
    ];
}

/** Стиль и скрипт подсказок; выводится один раз на страницу. */
function vlmcTipsAssets($themeCSS) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
<style>
.vlmc-tip { position: fixed; z-index: 2147483000; max-width: 280px; padding: 7px 11px; border-radius: 8px; font-size: 12px; line-height: 1.45; font-weight: 400; text-transform: none; letter-spacing: 0; text-align: left; white-space: normal; pointer-events: none; opacity: 0; transform: translateY(3px); transition: opacity .14s, transform .14s; background: <?= $themeCSS['card'] ?>; color: <?= $themeCSS['text'] ?>; border: 1px solid <?= $themeCSS['primary'] ?>; box-shadow: 0 6px 22px rgba(0,0,0,.4); }
.vlmc-tip.on { opacity: 1; transform: none; }
.vlmc-tip::after { content: ''; position: absolute; left: var(--ax, 50%); width: 8px; height: 8px; margin-left: -4px; background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['primary'] ?>; transform: rotate(45deg); }
.vlmc-tip.below::after { top: -5px; border-right: 0; border-bottom: 0; }
.vlmc-tip.above::after { bottom: -5px; border-left: 0; border-top: 0; }
</style>
<script>
(function () {
    var box = null, timer = null, cur = null;
    // Подсказки для готовых элементов: селектор => текст; обычные title превращаются в такие же подсказки
    var MAP = <?= json_encode(vlmcTipTexts(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var applyTimer = null;
    function applyTips() {
        Object.keys(MAP).forEach(function (sel) {
            try {
                document.querySelectorAll(sel).forEach(function (el) {
                    if (!el.hasAttribute('data-tip')) { el.setAttribute('data-tip', MAP[sel]); }
                });
            } catch (e) { /* неверный селектор не должен ломать страницу */ }
        });
        document.querySelectorAll('[title]').forEach(function (el) {
            var t = el.getAttribute('title');
            if (t && !el.hasAttribute('data-tip') && el.tagName !== 'IFRAME' && el.tagName !== 'svg') { el.setAttribute('data-tip', t); }
            if (t) { el.removeAttribute('title'); }
        });
    }
    function scheduleApply() { clearTimeout(applyTimer); applyTimer = setTimeout(applyTips, 120); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', applyTips); } else { applyTips(); }
    new MutationObserver(scheduleApply).observe(document.documentElement, { childList: true, subtree: true });
    function ensure() {
        if (!box) {
            box = document.createElement('div');
            box.className = 'vlmc-tip';
            box.setAttribute('role', 'tooltip');
            document.body.appendChild(box);
        }
        return box;
    }
    function hide() {
        clearTimeout(timer);
        timer = null;
        cur = null;
        if (box) { box.classList.remove('on'); }
    }
    function place(el) {
        var b = ensure(), r = el.getBoundingClientRect();
        b.textContent = el.getAttribute('data-tip');
        b.style.left = '0px'; b.style.top = '0px';
        var w = b.offsetWidth, h = b.offsetHeight, vw = window.innerWidth, vh = window.innerHeight;
        var cx = r.left + r.width / 2;
        var left = Math.max(8, Math.min(cx - w / 2, vw - w - 8));
        var below = r.bottom + 10 + h <= vh || r.top - 10 - h < 8;
        var top = below ? r.bottom + 9 : r.top - h - 9;
        b.className = 'vlmc-tip ' + (below ? 'below' : 'above');
        b.style.left = left + 'px';
        b.style.top = top + 'px';
        b.style.setProperty('--ax', Math.max(14, Math.min(cx - left, w - 14)) + 'px');
        void b.offsetWidth;
        b.classList.add('on');
    }
    function find(t) {
        return t && t.closest ? t.closest('[data-tip]') : null;
    }
    function show(el, delay) {
        if (!el) { return; }
        hide();
        cur = el;
        timer = setTimeout(function () { if (cur === el && document.body.contains(el)) { place(el); } }, delay);
    }
    document.addEventListener('mouseover', function (e) {
        var el = find(e.target);
        if (el && el !== cur) { show(el, 350); }
        else if (!el && cur) { hide(); }
    });
    document.addEventListener('mouseout', function (e) {
        if (cur && !(e.relatedTarget && cur.contains(e.relatedTarget))) { hide(); }
    });
    document.addEventListener('focusin', function (e) { var el = find(e.target); if (el) { show(el, 150); } });
    document.addEventListener('focusout', hide);
    document.addEventListener('mousedown', hide);
    document.addEventListener('keydown', hide);
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
})();
</script>
    <?php
}
