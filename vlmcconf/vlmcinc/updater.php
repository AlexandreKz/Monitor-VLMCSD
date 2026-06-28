<?php
// ============================================
// ФАЙЛ: vlmcinc/updater.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-06-09
// @description: Логика обновления проекта
// ============================================

class Updater {
    
    // GitHub репозиторий
    private $repo = 'AlexandreKz/kms-monitor';
    private $branch = 'main';
    
    /**
     * Проверка наличия обновлений
     * @return array
     */
    public function checkUpdates() {
        $currentVersion = CONFIG_VERSION;
        $latestVersion = $this->getLatestVersion();
        
        if ($latestVersion === null) {
            return [
                'success' => false,
                'message' => __('update_check_failed'),
                'update_available' => false
            ];
        }
        
        $updateAvailable = version_compare($currentVersion, $latestVersion) < 0;
        
        return [
            'success' => true,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => $updateAvailable
        ];
    }
    
    /**
     * Получение последней версии с GitHub
     * @return string|null
     */
    private function getLatestVersion() {
        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'KMS-Monitor-Updater/1.0',
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        $tagName = $data['tag_name'] ?? '';
        
        // Убираем 'v' из начала тега, если есть
        return ltrim($tagName, 'v');
    }
    
    /**
     * Получение списка файлов для обновления
     * @return array
     */
    public function getFilesToUpdate() {
        $files = [];
        
        // Список файлов, которые могут обновляться
        $checkFiles = [
            'vlmc.php',
            'vlmcconf/vlmcconf.php',
            'vlmcconf/login.php',
            'vlmcconf/logout.php',
            'vlmcconf/vlmctheme.php',
            'vlmcconf/vlmcgeoip.php',
            'vlmcconf/vlmcloghandler.php',
            'vlmcconf/flags.php',
            'vlmcconf/sections/general.php',
            'vlmcconf/sections/groups.php',
            'vlmcconf/sections/devices.php',
            'vlmcconf/sections/security.php',
            'vlmcconf/sections/stats.php',
            'vlmcconf/sections/info.php',
            'vlmcconf/sections/tools.php',
            'vlmcconf/sections/documentation.php',
            'vlmcconf/sections/integrations.php',
            'vlmcconf/sections/api.php',
            'vlmcconf/vlmcinc/config.php',
            'vlmcconf/vlmcinc/users.php',
            'vlmcconf/vlmcinc/auth.php',
            'vlmcconf/vlmcinc/analytics.php',
            'vlmcconf/vlmcinc/ajax.php',
            'vlmcconf/vlmcinc/geo_cache.php',
            'vlmcconf/vlmcinc/emoji_manager.php',
            'vlmcconf/locale/ru.php',
            'vlmcconf/locale/en.php',
            'vlmcconf/locale/emoji.php'
        ];
        
        foreach ($checkFiles as $file) {
            $localVersion = $this->getLocalFileVersion($file);
            $remoteVersion = $this->getRemoteFileVersion($file);
            
            if ($remoteVersion && version_compare($localVersion, $remoteVersion) < 0) {
                $files[] = $file;
            }
        }
        
        return $files;
    }
    
    /**
     * Получение версии локального файла
     * @param string $file
     * @return string
     */
    private function getLocalFileVersion($file) {
        $fullPath = dirname(__DIR__, 2) . '/' . $file;
        if (!file_exists($fullPath)) {
            return '0.0.0';
        }
        
        $content = file_get_contents($fullPath);
        if (preg_match('/\/\/ ВЕРСИЯ:\s*([0-9.]+)/', $content, $matches)) {
            return $matches[1];
        }
        
        return '0.0.0';
    }
    
    /**
     * Получение версии удалённого файла
     * @param string $file
     * @return string|null
     */
    private function getRemoteFileVersion($file) {
        $url = "https://raw.githubusercontent.com/{$this->repo}/{$this->branch}/{$file}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'KMS-Monitor-Updater/1.0',
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return null;
        }
        
        if (preg_match('/\/\/ ВЕРСИЯ:\s*([0-9.]+)/', $response, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Создание резервной копии файлов
     * @param array $files
     * @return bool
     */
    public function backupFiles($files) {
        $backupDir = dirname(__DIR__, 2) . '/vlmcconf/backups/update_' . date('Y-m-d_H-i-s');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        foreach ($files as $file) {
            $source = dirname(__DIR__, 2) . '/' . $file;
            $target = $backupDir . '/' . dirname($file);
            
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
            
            if (file_exists($source)) {
                copy($source, $target . '/' . basename($file));
            }
        }
        
        return true;
    }
    
    /**
     * Обновление файлов
     * @param array $files
     * @return array
     */
    public function updateFiles($files) {
        $updated = [];
        $failed = [];
        
        foreach ($files as $file) {
            $url = "https://raw.githubusercontent.com/{$this->repo}/{$this->branch}/{$file}";
            $targetPath = dirname(__DIR__, 2) . '/' . $file;
            $targetDir = dirname($targetPath);
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'KMS-Monitor-Updater/1.0',
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $content) {
                if (file_put_contents($targetPath, $content)) {
                    $updated[] = $file;
                } else {
                    $failed[] = $file;
                }
            } else {
                $failed[] = $file;
            }
        }
        
        return [
            'success' => empty($failed),
            'updated' => $updated,
            'failed' => $failed
        ];
    }
    
    /**
     * Восстановление из резервной копии
     * @param string $backupDir
     * @return bool
     */
    public function rollback($backupDir) {
        // Логика восстановления
        return false;
    }
}
?>