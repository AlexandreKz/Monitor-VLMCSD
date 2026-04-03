<?php
// ============================================
// ФАЙЛ: vlmcconf/vlmcloghandler.php
// ВЕРСИЯ: 1.2.0
// ДАТА: 2026-03-27
// @description: Обработчик и форматирование логов KMS с поддержкой tooltip
// ============================================

// Подключаем geoip для функции hex2rgb
require_once __DIR__ . '/vlmcgeoip.php';

/**
 * Обработка одной строки лога
 */
function processLine($line, $devices, $deviceComments, $groups, $groupColors, $filter, $groupFilter, &$lastConnectionId = null) {
    static $connectionCounter = 0;
    static $lastDisplayedConnectionId = null;
    
    // Фильтр по типу событий
    if ($filter !== 'all') {
        if ($filter === 'requests' && strpos($line, 'KMS v6.0 request') === false && strpos($line, 'KMS v4.0 request') === false) return null;
        if ($filter === 'connections' && strpos($line, 'connection') === false) return null;
    }
    
    $lineWithHighlights = $line;
    $foundGroup = null;
    $foundDeviceName = null;
    $foundDeviceComment = null;
    $foundIpAddress = null;
    $currentConnectionId = null;
    
    // Определяем ID соединения для группировки
    if (preg_match('/connection accepted: ([\d\.]+):\d+/', $line, $matches)) {
        $currentConnectionId = 'conn_' . $matches[1] . '_' . time();
    }
    
    // Форматируем строку запроса для лучшего отображения
    if (preg_match('/KMS v[46]\.0 request from/', $line)) {
        $lineWithHighlights = preg_replace('/from(\S+?)for/', 'from $1 for', $lineWithHighlights);
    }
    
    // Ищем устройства в строке
    foreach ($devices as $device => $group) {
        $isIpAddress = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $device);
        
        if (strpos($line, $device) !== false) {
            if (!$isIpAddress) {
                $foundDeviceName = $device;
                $foundGroup = $group;
                $foundDeviceComment = $deviceComments[$device] ?? null;
            } else {
                $foundIpAddress = $device;
                $foundGroup = $group;
            }
        }
    }
    
    // Применяем фильтр по группе
    if ($groupFilter !== 'all') {
        if (!$foundGroup || $foundGroup !== $groupFilter) {
            return null;
        }
    }
    
    // Подсвечиваем найденное устройство
    $highlightTarget = null;
    $highlightGroup = null;
    
    if ($foundDeviceName) {
        $highlightTarget = $foundDeviceName;
        $highlightGroup = $devices[$foundDeviceName];
    } elseif ($foundIpAddress) {
        $highlightTarget = $foundIpAddress;
        $highlightGroup = $devices[$foundIpAddress];
    }
    
    if ($highlightTarget) {
        $titleAttr = '';
        if ($foundDeviceComment && !empty($foundDeviceComment)) {
            $titleAttr = ' title="' . htmlspecialchars($foundDeviceComment) . '"';
        }
        
        $lineWithHighlights = str_replace(
            $highlightTarget,
            sprintf(
                '<span style="color:%s; font-weight:700; text-decoration:underline; text-underline-offset:2px; cursor:help;"%s data-group="%s">%s</span>',
                $groupColors[$highlightGroup],
                $titleAttr,
                $highlightGroup,
                $highlightTarget
            ),
            $lineWithHighlights
        );
    }
    
    // Добавляем бейдж группы
    $badgeGroup = $foundGroup;
    $result = '';
    
    if ($currentConnectionId && $lastDisplayedConnectionId !== $currentConnectionId) {
        $lastDisplayedConnectionId = $currentConnectionId;
        $result .= '<div class="connection-separator" title="Разделитель соединений"></div>';
    }
    
    if ($badgeGroup && isset($groupColors[$badgeGroup])) {
        $groupBadge = sprintf(
            ' <span style="background:%s; color:%s; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:600; text-transform:uppercase; border:1px solid %s; margin-left:10px;">%s</span>',
            "rgba(" . hex2rgb($groupColors[$badgeGroup]) . ", 0.15)",
            $groupColors[$badgeGroup],
            $groupColors[$badgeGroup],
            strtoupper(__($badgeGroup))
        );
        
        $result .= sprintf(
            '<div style="background-color:%s; border-left: 6px solid %s; padding: 4px 0 4px 10px; margin: 0; border-radius: 0 4px 4px 0; display: flex; align-items: center; flex-wrap: wrap;">%s%s</div>',
            "rgba(" . hex2rgb($groupColors[$badgeGroup]) . ", 0.15)",
            $groupColors[$badgeGroup],
            $lineWithHighlights,
            $groupBadge
        );
    } else {
        $result .= '<div style="padding: 4px 0 4px 16px; margin: 0;">' . $lineWithHighlights . '</div>';
    }
    
    return $result;
}

/**
 * Обработка всего лога
 */
function processLog($logFile, $devices, $deviceComments, $groups, $groupColors, $filter = 'all', $groupFilter = 'all') {
    if (!file_exists($logFile)) {
        return 'Ошибка: файл лога не найден';
    }
    
    $content = file_get_contents($logFile);
    if ($content === false) {
        return 'Ошибка: не удалось прочитать файл лога';
    }
    
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $content = preg_replace('/(from)(\S+?)(for)/', '$1 $2 $3', $content);
    
    $lines = array_filter(explode("\n", $content), function($line) {
        return trim($line) !== '';
    });
    
    $processedLines = [];
    $lastConnectionId = null;
    
    foreach ($lines as $line) {
        $processed = processLine($line, $devices, $deviceComments, $groups, $groupColors, $filter, $groupFilter, $lastConnectionId);
        if ($processed !== null) {
            $processedLines[] = $processed;
        }
    }
    
    if (!empty($processedLines) && strpos($processedLines[0], 'connection-separator') !== false) {
        array_shift($processedLines);
    }
    
    return implode("\n", $processedLines);
}