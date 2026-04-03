<?php
// ============================================
// ФАЙЛ: sections/stats.php
// ВЕРСИЯ: 1.8.0
// ДАТА: 2026-03-27
// @description: Секция "Статистика" (доступна всем)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'stats.php') {
    http_response_code(403);
    exit('Access denied');
}
?>
<div id="section-stats" class="settings-section <?= $activeSection === 'stats' ? 'active' : '' ?>">
    <div class="section-title">
        <span>📊 <?= __('stats_title') ?></span>
        <?php if ($activeSection === 'stats' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
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
            <div class="stat-card-value"><?= $customGroups ?></div>
            <div class="stat-card-label"><?= __('stats_custom_groups') ?></div>
        </div>
    </div>
    
    <!-- Три блока в одну строку -->
    <div class="stats-detailed">
        <!-- Статистика по группам -->
        <div class="stat-block">
            <div class="stat-block-title">📊 <?= __('stats_by_groups') ?></div>
            <div class="stat-list">
                <?php foreach ($groupStats as $group => $count): ?>
                <div class="stat-list-item">
                    <span class="stat-list-label" style="color: <?= htmlspecialchars($config['groupColors'][$group]) ?>;"><?= __($group) ?>:</span>
                    <span class="stat-list-value"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Рекорды -->
        <div class="stat-block">
            <div class="stat-block-title">🏆 <?= __('stats_records') ?></div>
            <div class="stat-list">
                <div class="stat-list-item">
                    <span class="stat-list-label"><?= __('stats_largest_group') ?>:</span>
                    <span class="stat-list-value"><?= __($largestGroup) ?> (<?= $largestGroupCount ?>)</span>
                </div>
                <?php if ($smallestGroup): ?>
                <div class="stat-list-item">
                    <span class="stat-list-label"><?= __('stats_smallest_group') ?>:</span>
                    <span class="stat-list-value"><?= __($smallestGroup) ?> (<?= $smallestGroupCount ?>)</span>
                </div>
                <?php endif; ?>
                <div class="stat-list-item">
                    <span class="stat-list-label"><?= __('stats_oldest_device') ?>:</span>
                    <span class="stat-list-value"><?= htmlspecialchars($oldestDevice) ?> (<?= $oldestDate ? date('d.m.Y', $oldestDate) : '—' ?>)</span>
                </div>
                <div class="stat-list-item">
                    <span class="stat-list-label"><?= __('stats_newest_device') ?>:</span>
                    <span class="stat-list-value"><?= htmlspecialchars($newestDevice) ?> (<?= $newestDate ? date('d.m.Y', $newestDate) : '—' ?>)</span>
                </div>
            </div>
        </div>
        
        <!-- Комментарии -->
        <div class="stat-block">
            <div class="stat-block-title">📝 <?= __('stats_comments') ?></div>
            <div class="stat-list">
                <div class="stat-list-item">
                    <span class="stat-list-label"><?= __('stats_with_comments') ?>:</span>
                    <span class="stat-list-value"><?= $devicesWithComments ?> / <?= $totalDevices ?></span>
                </div>
                <div class="stat-list-item">
                    <span class="stat-list-label"><?= __('stats_avg_length') ?>:</span>
                    <span class="stat-list-value"><?= $avgCommentLength ?> <?= __('characters') ?></span>
                </div>
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

<style>
.stats-detailed {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
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
</style>

<script>
// ... (JavaScript остается без изменений из предыдущей версии)
// Глобальные переменные для статистики
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

// Автоматическая загрузка графика
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