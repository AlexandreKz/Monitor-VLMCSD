<?php
// ============================================
// ФАЙЛ: vlmcinc/config.php
// ВЕРСИЯ: 1.1.0
// @description: Общие функции и утилиты
// ============================================

/**
 * Получение даты первого события в логе
 */
function getFirstLogEvent($logFile) {
    if (!file_exists($logFile)) {
        return ['date' => '—', 'event' => 'Файл не найден'];
    }
    
    $content = file_get_contents($logFile);
    if ($content === false) {
        return ['date' => '—', 'event' => 'Ошибка чтения'];
    }
    
    $lines = explode("\n", $content);
    $firstLine = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && preg_match('/\d{4}-\d{2}-\d{2}/', $line)) {
            $firstLine = $line;
            break;
        }
    }
    
    if (!$firstLine) {
        return ['date' => '—', 'event' => 'Лог пуст'];
    }
    
    // Возвращаем полную строку как событие, дату извлекаем из неё
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $firstLine, $matches)) {
        return [
            'date' => $matches[1],
            'event' => $firstLine
        ];
    }
    
    return ['date' => 'Дата не найдена', 'event' => $firstLine];
}

/**
 * Форматирование размера файла
 */
function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

/**
 * Глобальная функция перевода
 */
function __($key) {
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : $key;
}