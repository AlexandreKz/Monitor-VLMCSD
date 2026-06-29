<?php
// ============================================
// ФАЙЛ: vlmcinc/emoji_manager.php
// ВЕРСИЯ: 1.1.0
// ДАТА: 2026-06-05
// @description: Управление списком emoji (кэширование без срока годности)
// ============================================

define('EMOJI_BASE_FILE', __DIR__ . '/../locale/emoji.php');
define('EMOJI_CACHE_FILE', __DIR__ . '/../cache/emoji_cache.php');
define('EMOJI_SOURCE_URL', 'https://raw.githubusercontent.com/github/gemoji/master/db/emoji.json');

/**
 * Загружает список emoji из интернета
 */
function fetch_online_emoji_list() {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => EMOJI_SOURCE_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'KMS-Monitor-Emoji-Updater/1.0'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }
    
    $emojiList = array_column($data, 'emoji');
    $emojiList = array_filter($emojiList);
    
    return empty($emojiList) ? null : $emojiList;
}

/**
 * Сохраняет кэш emoji
 */
function save_emoji_cache($emojiList) {
    $content = "<?php\n// Автоматически сгенерированный кэш emoji\n// Дата: " . date('Y-m-d H:i:s') . "\n\nreturn " . var_export($emojiList, true) . ";\n?>";
    return file_put_contents(EMOJI_CACHE_FILE, $content) !== false;
}

/**
 * Получает список emoji (кэш -> интернет -> базовый)
 * Кэш бессрочный, удаляется только вручную или при ошибке
 */
function get_emoji_list() {
    // Проверяем, есть ли кэш
    if (file_exists(EMOJI_CACHE_FILE)) {
        $cached = include EMOJI_CACHE_FILE;
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }
    }
    
    // Кэша нет — пробуем скачать из интернета
    $onlineList = fetch_online_emoji_list();
    if ($onlineList !== null) {
        save_emoji_cache($onlineList);
        return $onlineList;
    }
    
    // Нет ни кэша, ни интернета — возвращаем базовый список
    return include EMOJI_BASE_FILE;
}

/**
 * Очищает кэш emoji
 */
function clear_emoji_cache() {
    if (file_exists(EMOJI_CACHE_FILE)) {
        return unlink(EMOJI_CACHE_FILE);
    }
    return true;
}

/**
 * Принудительно обновляет кэш emoji
 */
function refresh_emoji_cache() {
    $onlineList = fetch_online_emoji_list();
    if ($onlineList === null) {
        return ['success' => false, 'message' => __('emoji_refresh_failed'), 'count' => 0];
    }
    
    if (save_emoji_cache($onlineList)) {
        return ['success' => true, 'message' => __('emoji_refresh_success'), 'count' => count($onlineList)];
    }
    
    return ['success' => false, 'message' => __('emoji_cache_write_error'), 'count' => 0];
}
?>