<?php
// ============================================
// ФАЙЛ: sections/stats.php
// ВЕРСИЯ: 3.0.0
// ДАТА: 2026-11-17
// @description: Секция "Статистика и отчёты": вкладки «Статистика» и «Отчёты»
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'stats.php') {
    http_response_code(403);
    exit('Access denied');
}

// Один потоковый проход по логу: ip => число подключений
$ipCounts = getLogIpCounts($fullLogPath);
$totalRequests = array_sum($ipCounts);

// ТОП IP
$topIps = $ipCounts;
arsort($topIps);
$topIps = array_slice($topIps, 0, 4, true);

// ТОП стран: данные ТОЛЬКО из кэша, сетевых запросов при отрисовке страницы нет.
// IP без кэша определяются в фоне через AJAX (см. скрипт ниже).
$countryCounts = [];
$geoPending = 0;
foreach ($ipCounts as $ip => $cnt) {
    $country = getGeoCachedCountry($ip);
    if ($country === null) {
        if (geoLookupFailedRecently($ip)) {
            $country = __('geo_unknown');
        } else {
            $geoPending++;
            continue;
        }
    }
    $countryCounts[$country] = ($countryCounts[$country] ?? 0) + $cnt;
}
arsort($countryCounts);
$topCountries = array_slice($countryCounts, 0, 4, true);

// Статистика по группам (устройства из конфига)
$groupStats = [];
foreach ($config['groupColors'] as $group => $color) {
    $groupStats[$group] = isset($config['devices'][$group]) ? count($config['devices'][$group]) : 0;
}
arsort($groupStats);
$totalDevices = array_sum($groupStats);
$totalGroups = count($groupStats);
?>

<div id="section-stats" class="settings-section <?= $activeSection === 'stats' ? 'active' : '' ?>">
    <div class="section-title">
        <span>📊 <?= __('stats_title') ?></span>
        <?php if ($activeSection === 'stats' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="stats-pages">
        <button type="button" class="stats-page-btn active" data-page="overview" onclick="switchStatsPage('overview')">📊 <?= __('stats_tab_overview') ?></button>
        <button type="button" class="stats-page-btn" data-page="reports" onclick="switchStatsPage('reports')">📑 <?= __('stats_tab_reports') ?></button>
    </div>

    <div id="statsPageOverview" class="stats-page active">
    <!-- Общая статистика (3 карточки) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-value"><?= $totalDevices ?></div>
            <div class="stat-card-label"><?= __('stats_total_devices') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value"><?= $totalGroups ?></div>
            <div class="stat-card-label"><?= __('stats_total_groups') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value"><?= number_format($totalRequests, 0, ',', ' ') ?></div>
            <div class="stat-card-label"><?= __('stats_total_requests') ?></div>
        </div>
    </div>
    
    <!-- Три блока в одну строку: Группы | Топ IP | Топ стран -->
    <div class="stats-detailed">
        
        <!-- Блок: По группам -->
		<div class="stat-block">
			<div class="stat-block-title">👥 <?= __('stats_by_groups') ?></div>
			<div class="stat-list">
				<?php if (empty($groupStats)): ?>
					<div class="stat-list-empty"><?= __('stats_no_groups') ?></div>
				<?php else: ?>
					<?php foreach ($groupStats as $group => $count): 
						$groupColor = is_array($config['groupColors'][$group]) 
							? $config['groupColors'][$group]['color'] 
							: $config['groupColors'][$group];
					?>
					<div class="stat-list-item">
						<span class="stat-list-label" style="color: <?= htmlspecialchars($groupColor) ?>;">
							<?= __($group) ?>
						</span>
						<span class="stat-list-value"><?= $count ?></span>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
        
        <!-- Блок: Топ IP адресов -->
        <div class="stat-block">
            <div class="stat-block-title">🌐 <?= __('stats_top_ips') ?></div>
            <div class="stat-list">
                <?php if (empty($topIps)): ?>
                    <div class="stat-list-empty"><?= __('stats_no_data_ips') ?></div>
                <?php else: ?>
                    <?php foreach ($topIps as $ip => $count): ?>
                    <div class="stat-list-item">
                        <span class="stat-list-label stat-copyable" data-copy="<?= htmlspecialchars($ip) ?>" title="<?= htmlspecialchars(__('stats_ip_copy_hint')) ?>" role="button" tabindex="0"><?= htmlspecialchars($ip) ?></span>
                        <span class="stat-list-value"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Блок: Топ стран -->
        <div class="stat-block">
            <div class="stat-block-title">🌍 <?= __('stats_top_countries') ?></div>
            <?php if ($geoPending > 0): ?>
            <div class="stat-list-empty" id="geo-pending-hint">🌐 <?= sprintf(__('stats_geo_pending'), $geoPending) ?></div>
            <script>
            (function () {
                var rounds = 0, resolvedTotal = 0, lastError = '';
                function finish() {
                    var el = document.getElementById('geo-pending-hint');
                    if (!el) return;
                    if (resolvedTotal > 0) {
                        el.textContent = <?= json_encode('🌐 ' . __('stats_geo_done')) ?>;
                    } else if (lastError === 'ssl_ca' || lastError === 'ssl') {
                        el.textContent = <?= json_encode('🔒 ' . __('stats_geo_ssl_error')) ?>;
                    } else {
                        el.remove();
                    }
                }
                function step() {
                    if (rounds++ >= 100) { finish(); return; }
                    var fd = new FormData();
                    fd.append('ajax', 'geo_resolve_portion');
                    fetch('', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (!d || !d.success) { finish(); return; }
                            resolvedTotal += d.resolved;
                            if (d.error) lastError = d.error;
                            if (d.remaining > 0 && (d.resolved + d.failed) > 0) { step(); } else { finish(); }
                        })
                        .catch(finish);
                }
                window.addEventListener('load', function () { setTimeout(step, 500); });
            })();
            </script>
            <?php endif; ?>
            <div class="stat-list">
                <?php if (empty($topCountries)): ?>
                    <div class="stat-list-empty"><?= __('stats_no_data_countries') ?></div>
                <?php else: ?>
                    <?php foreach ($topCountries as $country => $count): ?>
                    <div class="stat-list-item">
                        <span class="stat-list-label">
                            <?= getCountryFlag($country) ?> <?= htmlspecialchars($country) ?>
                        </span>
                        <span class="stat-list-value"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <!-- Активность (на всю ширину) -->
    <div class="stat-block" style="margin-top: 0;">
        <div class="stat-block-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 10px; align-items: center;">
                <button class="stat-tab-btn active" data-tab="timeline" onclick="switchStatTab('timeline')">📅 <?= __('stats_overall') ?></button>
                <button class="stat-tab-btn" data-tab="device" onclick="switchStatTab('device')">📱 <?= __('stats_by_device') ?></button>
                
                <select id="deviceSelect" class="form-control" style="width: 220px; display: none;" onchange="loadDeviceActivity()">
                    <option value="">-- <?= __('stats_select_device') ?> --</option>
                    <?php foreach ($deviceList as $device): ?>
                    <option value="<?= htmlspecialchars((string)$device) ?>"><?= htmlspecialchars((string)$device) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="period-selector" id="periodSelector">
                <button class="period-btn active" data-period="day" onclick="changePeriod('day')"><?= __('stats_days') ?></button>
                <button class="period-btn" data-period="week" onclick="changePeriod('week')"><?= __('stats_weeks') ?></button>
                <button class="period-btn" data-period="month" onclick="changePeriod('month')"><?= __('stats_months') ?></button>
            </div>
        </div>
        
        <div class="chart-container">
            <canvas id="activityChart"></canvas>
        </div>
    </div>
    </div>

    <!-- Вкладка «Отчёты» -->
    <div id="statsPageReports" class="stats-page">
        <div class="stat-block">
            <div class="stat-block-title">🔎 <?= __('report_title') ?></div>
            <div class="report-controls">
                <div class="report-modes">
                    <button type="button" class="report-mode-btn active" data-mode="device" id="reportModeDevice"><?= __('report_mode_device') ?></button>
                    <button type="button" class="report-mode-btn" data-mode="ip" id="reportModeIp"><?= __('report_mode_ip') ?></button>
                </div>
                <input type="text" id="reportInput" class="form-control report-input" list="reportListDevices" autocomplete="off" spellcheck="false" placeholder="<?= htmlspecialchars(__('report_input_device')) ?>">
                <button type="button" class="btn btn-primary" id="reportRun" style="padding: 7px 18px; font-size: 13px;">🔍 <?= __('report_show') ?></button>
            </div>
            <datalist id="reportListDevices"></datalist>
            <datalist id="reportListIps"></datalist>
            <div class="report-hint"><?= __('report_hint') ?></div>
            <div id="reportSummary" class="report-summary" style="display: none;"></div>
            <div id="reportResult" class="report-result"></div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}

.stat-card-value {
    font-size: 28px;
    font-weight: 600;
    color: <?= $themeCSS['primary'] ?>;
    margin-bottom: 4px;
}

.stat-card-label {
    font-size: 10px;
    color: #8aa0bb;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.stats-detailed {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-block {
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 12px;
}

.stat-block-title {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #8aa0bb;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
    padding-bottom: 6px;
}

.stat-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.stat-list-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    padding: 4px 0;
    border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
}

.stat-copyable {
    cursor: pointer;
    border-radius: 3px;
    transition: background 0.15s;
}
.stat-copyable:hover,
.stat-copyable:focus {
    background: rgba(61, 139, 253, 0.18);
    outline: none;
}
.stat-copy-badge {
    margin-left: 8px;
    font-size: 10px;
    white-space: nowrap;
}
.stat-copy-badge.ok { color: #2ea043; }
.stat-copy-badge.err { color: #f85149; }

.stat-list-label {
    color: #8aa0bb;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-list-value {
    font-weight: 600;
    color: <?= $themeCSS['primary'] ?>;
}

.stat-list-empty {
    text-align: center;
    color: #8aa0bb;
    font-size: 11px;
    padding: 15px 0;
}

.stat-tab-btn {
    padding: 4px 12px;
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.stat-tab-btn.active {
    background: <?= $themeCSS['primary'] ?>;
    color: white;
    border-color: <?= $themeCSS['primary'] ?>;
}

.chart-container {
    position: relative;
    height: 280px;
    width: 100%;
    margin-top: 8px;
}

.period-selector {
    display: flex;
    gap: 5px;
}

.period-btn {
    padding: 3px 8px;
    border: 1px solid <?= $themeCSS['border'] ?>;
    background: <?= $themeCSS['input'] ?>;
    color: <?= $themeCSS['text'] ?>;
    border-radius: 4px;
    cursor: pointer;
    font-size: 10px;
}

.period-btn.active {
    background: <?= $themeCSS['primary'] ?>;
    color: white;
    border-color: <?= $themeCSS['primary'] ?>;
}

.form-control {
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['inputBorder'] ?>;
    border-radius: 4px;
    color: <?= $themeCSS['text'] ?>;
    padding: 4px 8px;
    font-size: 12px;
}
/* Вкладки раздела */
.stats-pages { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.stats-page-btn { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 20px; padding: 6px 20px; font-size: 13px; font-weight: 500; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.7; transition: all 0.2s; font-family: inherit; }
.stats-page-btn:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.stats-page-btn.active { opacity: 1; color: <?= $themeCSS['primary'] ?>; background: <?= $themeCSS['card'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.stats-page { display: none; }
.stats-page.active { display: block; }

/* Отчёты */
.report-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
.report-modes { display: inline-flex; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; overflow: hidden; }
.report-mode-btn { background: <?= $themeCSS['input'] ?>; color: <?= $themeCSS['text'] ?>; border: none; padding: 7px 14px; font-size: 12px; cursor: pointer; opacity: 0.75; font-family: inherit; }
.report-mode-btn + .report-mode-btn { border-left: 1px solid <?= $themeCSS['border'] ?>; }
.report-mode-btn:hover { opacity: 1; }
.report-mode-btn.active { background: <?= $themeCSS['primary'] ?>; color: #fff; opacity: 1; }
.report-input { flex: 1; min-width: 200px; max-width: 360px; width: auto; }
.report-hint { font-size: 11px; color: #8aa0bb; margin-top: 8px; }
.report-summary { font-size: 12px; color: <?= $themeCSS['text'] ?>; margin: 12px 0 8px; font-weight: 600; }
.report-result { margin-top: 10px; }
.report-empty { font-size: 12px; color: #8aa0bb; padding: 12px 0; }
.report-table-wrap { overflow: auto; max-height: 420px; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; background: <?= $themeCSS['input'] ?>; }
.report-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.report-table th { position: sticky; top: 0; text-align: left; padding: 8px 10px; background: <?= $themeCSS['card'] ?>; color: #8aa0bb; font-weight: 600; white-space: nowrap; }
.report-table td { padding: 7px 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; }
.report-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.report-table td.mono { font-family: monospace; white-space: nowrap; }
.report-table tr:hover td { background: <?= $themeCSS['hover'] ?>; }
.report-link { color: <?= $themeCSS['primary'] ?>; cursor: pointer; }
.report-link:hover { text-decoration: underline; }
</style>

<script>
// ============================================
// ГРАФИК АКТИВНОСТИ
// ============================================

let statActivityChart = null;
let currentStatTab = 'timeline';
let currentStatPeriod = 'day';
let currentStatDevice = '';

function switchStatTab(tab) {
    currentStatTab = tab;
    
    document.querySelectorAll('.stat-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.stat-tab-btn[data-tab="${tab}"]`).classList.add('active');
    
    const deviceSelect = document.getElementById('deviceSelect');
    if (tab === 'device') {
        deviceSelect.style.display = 'inline-block';
        if (currentStatDevice) {
            loadDeviceActivity();
        } else {
            updateStatChart({});
        }
    } else {
        deviceSelect.style.display = 'none';
        loadTimelineActivity();
    }
}

function changePeriod(period) {
    currentStatPeriod = period;
    
    document.querySelectorAll('.period-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.period-btn[data-period="${period}"]`).classList.add('active');
    
    if (currentStatTab === 'device' && currentStatDevice) {
        loadDeviceActivity();
    } else {
        loadTimelineActivity();
    }
}

function loadTimelineActivity() {
    const fd = new FormData();
    fd.append('ajax', 'get_activity');
    fd.append('period', currentStatPeriod);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { 
            if (d.success) {
                updateStatChart(d.data);
            }
        })
        .catch(e => console.error('Timeline error:', e));
}

function loadDeviceActivity() {
    const deviceSelect = document.getElementById('deviceSelect');
    currentStatDevice = deviceSelect.value;
    if (!currentStatDevice) return;
    
    const fd = new FormData();
    fd.append('ajax', 'get_device_activity');
    fd.append('device', currentStatDevice);
    fd.append('period', currentStatPeriod);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { 
            if (d.success) {
                updateStatChart(d.data);
            }
        })
        .catch(e => console.error('Device activity error:', e));
}

function updateStatChart(data) {
    const labels = Object.keys(data);
    const values = Object.values(data);
    
    if (statActivityChart) {
        statActivityChart.destroy();
    }
    
    const ctx = document.getElementById('activityChart').getContext('2d');
    if (ctx) {
        let maxValue = Math.max(...values, 10);
        if (maxValue > 100) maxValue = 100;
        
        let stepSize = 1;
        if (maxValue > 50) stepSize = 10;
        else if (maxValue > 20) stepSize = 5;
        else if (maxValue > 10) stepSize = 2;
        
        statActivityChart = new Chart(ctx, {
            type: 'line',
            data: { 
                labels: labels, 
                datasets: [{ 
                    data: values, 
                    borderColor: '<?= $themeCSS['primary'] ?>', 
                    backgroundColor: '<?= $themeCSS['primary'] ?>20', 
                    borderWidth: 2, 
                    tension: 0.1, 
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        mode: 'index', 
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.raw;
                                let word = '<?= __('graph_requests') ?>';
                                if (label === 1) word = '<?= __('graph_request') ?>';
                                else if (label >= 2 && label <= 4) word = '<?= __('graph_requests_few') ?>';
                                return `📊 ${label} ${word}`;
                            }
                        }
                    } 
                }, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '<?= $themeCSS['border'] ?>' }, 
                        ticks: { 
                            color: '<?= $themeCSS['text'] ?>', 
                            stepSize: stepSize,
                            max: maxValue,
                            callback: function(value) {
                                if (Number.isInteger(value)) return value;
                                return null;
                            }
                        } 
                    }, 
                    x: { 
                        grid: { display: false }, 
                        ticks: { 
                            color: '<?= $themeCSS['text'] ?>', 
                            maxRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 15
                        } 
                    } 
                } 
            }
        });
    }
}

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================

(function() {
    function checkAndLoad() {
        const statsSection = document.getElementById('section-stats');
        if (statsSection && statsSection.classList.contains('active')) {
            if (typeof loadTimelineActivity === 'function') {
                loadTimelineActivity();
                return true;
            }
        }
        return false;
    }
    
    if (!checkAndLoad()) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    const target = mutation.target;
                    if (target.id === 'section-stats' && target.classList.contains('active')) {
                        if (typeof loadTimelineActivity === 'function') {
                            loadTimelineActivity();
                            observer.disconnect();
                        }
                    }
                }
            });
        });
        
        const statsSection = document.getElementById('section-stats');
        if (statsSection) {
            observer.observe(statsSection, { attributes: true });
        }
        
        setTimeout(function() {
            observer.disconnect();
            if (typeof loadTimelineActivity === 'function') {
                const section = document.getElementById('section-stats');
                if (section && section.classList.contains('active')) {
                    loadTimelineActivity();
                }
            }
        }, 500);
    }
})();
</script>
<script>
// Копирование IP из блока «Топ IP адресов» по клику (работает и без HTTPS: запасной вариант через execCommand)
(function () {
    if (window.__statCopyInit) return;
    window.__statCopyInit = true;

    const I18N = <?= json_encode(['copied' => __('stats_ip_copied'), 'failed' => __('stats_ip_copy_failed')], JSON_UNESCAPED_UNICODE) ?>;

    function fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return fallbackCopy(text); });
        }
        return Promise.resolve(fallbackCopy(text));
    }

    function showBadge(el, ok) {
        const old = el.parentNode.querySelector('.stat-copy-badge');
        if (old) old.remove();
        const badge = document.createElement('span');
        badge.className = 'stat-copy-badge ' + (ok ? 'ok' : 'err');
        badge.textContent = ok ? '✅ ' + I18N.copied : '❌ ' + I18N.failed;
        el.insertAdjacentElement('afterend', badge);
        setTimeout(function () { badge.remove(); }, 1600);
    }

    function handle(el) {
        copyText(el.getAttribute('data-copy') || '').then(function (ok) { showBadge(el, ok); });
    }

    document.addEventListener('click', function (e) {
        const el = e.target.closest ? e.target.closest('.stat-copyable') : null;
        if (el) handle(el);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const el = e.target.closest ? e.target.closest('.stat-copyable') : null;
        if (el) { e.preventDefault(); handle(el); }
    });
})();
</script>
<?php
?>

<script>
(function () {
    const T = {
<?php foreach (['report_input_device', 'report_input_ip', 'report_empty_query', 'report_bad_ip', 'report_not_found', 'report_error', 'report_loading', 'report_col_device', 'report_col_ip', 'report_col_country', 'report_col_count', 'report_col_first', 'report_col_last', 'report_sum_device', 'report_sum_devices', 'report_sum_ip'] as $k): ?>
        <?= $k ?>: <?= json_encode(__($k), JSON_UNESCAPED_UNICODE) ?>,
<?php endforeach; ?>
    };
    let mode = 'device';
    let listsLoaded = false;
    const $ = function (id) { return document.getElementById(id); };

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    }
    function fmt(str, args) {
        let i = 0;
        return str.replace(/%[sd]/g, function () { const v = args[i++]; return v === undefined ? '' : String(v); });
    }
    function post(params) {
        const fd = new FormData();
        fd.append('ajax', 'get_report');
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch('', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    window.switchStatsPage = function (page) {
        document.querySelectorAll('.stats-page-btn').forEach(function (b) { b.classList.toggle('active', b.dataset.page === page); });
        $('statsPageOverview').classList.toggle('active', page === 'overview');
        $('statsPageReports').classList.toggle('active', page === 'reports');
        if (page === 'reports') loadLists();
    };

    function loadLists() {
        if (listsLoaded) return;
        listsLoaded = true;
        post({ report: 'lists' }).then(function (d) {
            if (!d.success) { listsLoaded = false; return; }
            fill($('reportListDevices'), d.devices);
            fill($('reportListIps'), d.ips);
        }).catch(function () { listsLoaded = false; });
    }
    function fill(list, items) {
        list.textContent = '';
        (items || []).forEach(function (v) { const o = document.createElement('option'); o.value = v; list.appendChild(o); });
    }

    function clearResult() {
        $('reportSummary').style.display = 'none';
        $('reportResult').textContent = '';
    }

    function setMode(m) {
        mode = m;
        $('reportModeDevice').classList.toggle('active', m === 'device');
        $('reportModeIp').classList.toggle('active', m === 'ip');
        const inp = $('reportInput');
        inp.setAttribute('list', m === 'device' ? 'reportListDevices' : 'reportListIps');
        inp.placeholder = m === 'device' ? T.report_input_device : T.report_input_ip;
    }

    function showMessage(text) {
        $('reportSummary').style.display = 'none';
        const r = $('reportResult');
        r.textContent = '';
        r.appendChild(el('div', 'report-empty', text));
    }

    function render(d, q) {
        const rows = d.rows || [];
        if (!rows.length) { showMessage(T.report_not_found); return; }
        let requests = 0;
        rows.forEach(function (x) { requests += x.count; });
        const multi = mode === 'device' && d.matched && d.matched.length > 1;
        const sum = $('reportSummary');
        if (mode === 'device') {
            const ips = {};
            rows.forEach(function (x) { ips[x.ip] = 1; });
            sum.textContent = multi
                ? fmt(T.report_sum_devices, [d.matched.length, Object.keys(ips).length, requests])
                : fmt(T.report_sum_device, [rows[0].device, Object.keys(ips).length, requests]);
        } else {
            sum.textContent = fmt(T.report_sum_ip, [(d.flag ? d.flag + ' ' : '') + q + (d.country ? ' (' + d.country + ')' : ''), rows.length, requests]);
        }
        sum.style.display = 'block';

        const cols = [];
        if (mode === 'ip' || multi) cols.push(T.report_col_device);
        if (mode === 'device') { cols.push(T.report_col_ip); cols.push(T.report_col_country); }
        cols.push(T.report_col_count, T.report_col_first, T.report_col_last);

        const wrap = el('div', 'report-table-wrap');
        const table = el('table', 'report-table');
        const trh = el('tr');
        cols.forEach(function (c) { trh.appendChild(el('th', '', c)); });
        const thead = el('thead'); thead.appendChild(trh); table.appendChild(thead);
        const tbody = el('tbody');
        rows.forEach(function (x) {
            const tr = el('tr');
            if (mode === 'ip' || multi) {
                const td = el('td');
                const a = el('span', 'report-link', x.device);
                a.onclick = function () { setMode('device'); $('reportInput').value = x.device; run(); };
                td.appendChild(a); tr.appendChild(td);
            }
            if (mode === 'device') {
                const td = el('td', 'mono');
                const a = el('span', 'report-link', x.ip);
                a.onclick = function () { setMode('ip'); $('reportInput').value = x.ip; run(); };
                td.appendChild(a); tr.appendChild(td);
                tr.appendChild(el('td', '', x.country ? ((x.flag ? x.flag + ' ' : '') + x.country) : '—'));
            }
            tr.appendChild(el('td', 'num', String(x.count)));
            tr.appendChild(el('td', 'mono', x.first || '—'));
            tr.appendChild(el('td', 'mono', x.last || '—'));
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        const r = $('reportResult');
        r.textContent = '';
        r.appendChild(wrap);
    }

    function run() {
        const q = $('reportInput').value.trim();
        if (!q) { showMessage(T.report_empty_query); return; }
        showMessage(T.report_loading);
        const m = mode;
        post({ report: m === 'device' ? 'device_ips' : 'ip_devices', q: q }).then(function (d) {
            if (m !== mode) return;   // режим уже переключили
            if (!d.success) { showMessage(m === 'ip' ? T.report_bad_ip : T.report_error); return; }
            render(d, q);
        }).catch(function () { showMessage(T.report_error); });
    }

    function switchMode(m) {
        if (mode === m) return;
        setMode(m);
        $('reportInput').value = '';
        clearResult();
    }
    $('reportModeDevice').onclick = function () { switchMode('device'); };
    $('reportModeIp').onclick = function () { switchMode('ip'); };
    $('reportRun').onclick = run;
    $('reportInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
})();
</script>
