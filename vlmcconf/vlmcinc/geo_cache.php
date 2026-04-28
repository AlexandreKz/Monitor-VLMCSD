<?php
// ============================================
// ФАЙЛ: vlmcinc/geo_cache.php
// ВЕРСИЯ: 1.4.0
// ДАТА: 2026-04-27
// @description: Управление кэшем геолокации
// ============================================

function getGeoCacheStats() {
    $cacheDir = getGeoCacheDir();
    $files = glob($cacheDir . '/*.txt');
    $count = count($files);
    $size = 0;
    
    foreach ($files as $file) {
        $size += filesize($file);
    }
    
    $sizeFormatted = '';
    if ($size < 1024) {
        $sizeFormatted = $size . ' B';
    } elseif ($size < 1048576) {
        $sizeFormatted = round($size / 1024, 1) . ' KB';
    } else {
        $sizeFormatted = round($size / 1048576, 1) . ' MB';
    }
    
    return [
        'count' => $count,
        'size' => $size,
        'size_formatted' => $sizeFormatted
    ];
}

function clearGeoCache() {
    $cacheDir = getGeoCacheDir();
    $files = glob($cacheDir . '/*.txt');
    $deleted = 0;
    
    foreach ($files as $file) {
        if (unlink($file)) {
            $deleted++;
        }
    }
    
    return [
        'success' => true,
        'deleted' => $deleted,
        'message' => __('tools_cache_cleared') . ': ' . $deleted . ' ' . __('tools_cache_files')
    ];
}

function refreshGeoCacheByPortion($ips, $offset, $limit) {
    set_time_limit(120);
    
    $processed = 0;
    $updated = 0;
    $failed = 0;
    
    $ipsToProcess = array_slice($ips, $offset, $limit);
    $totalInPortion = count($ipsToProcess);
    $totalAll = count($ips);
    $nextOffset = $offset + $totalInPortion;
    $hasMore = $nextOffset < $totalAll;
    
    foreach ($ipsToProcess as $ip) {
        $processed++;
        
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $failed++;
            continue;
        }
        
        // Пропускаем локальные IP
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $failed++;
            continue;
        }
        
        $country = getGeoLocationDirect($ip);
        
        if ($country && $country !== __('geo_unknown')) {
            $cacheDir = getGeoCacheDir();
            $cacheFile = $cacheDir . '/' . md5($ip) . '.txt';
            
            $tempFile = $cacheFile . '.tmp';
            @file_put_contents($tempFile, $country);
            @rename($tempFile, $cacheFile);
            
            $updated++;
        } else {
            $failed++;
        }
        
        usleep(20000);
    }
    
    return [
        'success' => true,
        'processed' => $processed,
        'updated' => $updated,
        'failed' => $failed,
        'total_in_portion' => $totalInPortion,
        'total_all' => $totalAll,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'has_more' => $hasMore,
        'message' => __('tools_cache_refreshed') . ': ' . $updated . '/' . $totalInPortion
    ];
}

function getAllIpsFromMonitor($logFile, $config) {
    $ips = [];
    
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if ($content !== false) {
            if (preg_match_all('/connection accepted: ([\d\.]+):/', $content, $matches)) {
                foreach ($matches[1] as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[$ip] = true;
                    }
                }
            }
            if (preg_match_all('/from ([\d\.]+) for/', $content, $matches)) {
                foreach ($matches[1] as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[$ip] = true;
                    }
                }
            }
        }
    }
    
    return array_keys($ips);
}
?>