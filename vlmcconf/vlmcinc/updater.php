<?php
// ============================================
// ФАЙЛ: vlmcinc/updater.php
// ВЕРСИЯ: 2.0.0
// ДАТА: 2026-06-29
// @description: Логика обновления проекта
// @description: Project update logic
// ============================================

class Updater {
    
    // GitHub репозиторий / GitHub repository
    private $repo = 'AlexandreKz/Monitor-VLMCSD';
    private $branch = 'main';
    private $baseDir;
    
    public function __construct() {
        $this->baseDir = dirname(__DIR__, 2);
    }
    
    /**
     * Проверка наличия обновлений / Check for updates
     * @return array
     */
    public function checkUpdates() {
        $currentVersion = CONFIG_VERSION;
        $latestVersion = $this->getLatestVersion();
        
        if ($latestVersion === null) {
            return [
                'success' => false,
                'message' => 'Не удалось проверить обновления',
                'update_available' => false,
                'current_version' => $currentVersion,
                'latest_version' => '—'
            ];
        }
        
        $updateAvailable = version_compare($currentVersion, $latestVersion) < 0;
        
        return [
            'success' => true,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => $updateAvailable,
            'message' => $updateAvailable ? 'Доступна новая версия' : 'У вас последняя версия'
        ];
    }
    
    /**
     * Проверка доступности GitHub API / Check GitHub API availability
     * @return array
     */
    public function checkGitHubAccess() {
        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'KMS-Monitor-Updater/2.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'message' => 'CURL ошибка: ' . $curlError
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => 'GitHub API вернул HTTP ' . $httpCode
            ];
        }
        
        $data = json_decode($response, true);
        $tagName = ltrim($data['tag_name'] ?? '', 'v');
        
        return [
            'success' => true,
            'message' => 'GitHub API доступен',
            'repository' => $this->repo,
            'latest_version' => $tagName,
            'release_url' => $data['html_url'] ?? ''
        ];
    }
    
    /**
     * Получение последней версии с GitHub / Get latest version from GitHub
     * @return string|null
     */
    private function getLatestVersion() {
        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'KMS-Monitor-Updater/2.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200 || !$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        $tagName = $data['tag_name'] ?? '';
        
        return ltrim($tagName, 'v');
    }
    
    /**
     * Получение списка файлов для обновления (сравнение по размеру)
     * Get files to update (compare by size)
     * @return array
     */
    public function getFilesToUpdate() {
        $excludePaths = [
            '.git', '.gitignore', '.github', 'README.md', 'LICENSE',
            'backups', 'cache', 'screen', 'pic',
            'vlmcconf/cache', 'vlmcconf/backups'
        ];
        
        $repoFiles = $this->getRepoFilesWithSize('', $excludePaths);
        $filesToUpdate = [];
        
        foreach ($repoFiles as $filePath => $repoSize) {
            $localPath = $this->baseDir . '/' . $filePath;
            
            if (file_exists($localPath)) {
                $localSize = filesize($localPath);
                // Сравниваем размер файла / Compare file size
                if ($localSize !== $repoSize) {
                    $filesToUpdate[] = $filePath;
                }
            } else {
                // Новый файл / New file
                $filesToUpdate[] = $filePath . ' (новый)';
            }
        }
        
        sort($filesToUpdate);
        
        return [
            'success' => true,
            'files' => $filesToUpdate,
            'total' => count($filesToUpdate)
        ];
    }
    
    /**
     * Получение файлов из репозитория с их размером
     * Get files from repository with their size
     */
    private function getRepoFilesWithSize($path = '', $excludePaths = [], &$allFiles = []) {
        $url = "https://api.github.com/repos/{$this->repo}/contents/{$path}?ref={$this->branch}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'KMS-Monitor-Updater/2.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200 || !$response) {
            return $allFiles;
        }
        
        $items = json_decode($response, true);
        if (!is_array($items)) {
            return $allFiles;
        }
        
        foreach ($items as $item) {
            $name = $item['name'];
            $fullPath = $item['path'];
            
            // Проверяем исключения / Check excludes
            $skip = false;
            foreach ($excludePaths as $ex) {
                if ($fullPath === $ex || strpos($fullPath, $ex . '/') === 0 || $name === $ex) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            if ($item['type'] === 'file') {
                $allFiles[$fullPath] = $item['size'];
            } elseif ($item['type'] === 'dir') {
                $this->getRepoFilesWithSize($fullPath, $excludePaths, $allFiles);
            }
        }
        
        return $allFiles;
    }
    
 /**
 * Создание резервной копии файлов с детальным логом
 * @param array $files
 * @return array
 */
public function backupFiles($files) {
    $backupDir = $this->baseDir . '/vlmcconf/backups/update_' . date('Y-m-d_H-i-s');
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backedUp = [];
    $failed = [];
    $log = [];
    
    foreach ($files as $file) {
        $cleanFile = trim($file);
        if (strpos($cleanFile, ' (') !== false) {
            $cleanFile = substr($cleanFile, 0, strpos($cleanFile, ' ('));
        }
        
        $source = $this->baseDir . '/' . $cleanFile;
        $target = $backupDir . '/' . $cleanFile;
        $targetDir = dirname($target);
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        if (file_exists($source)) {
            if (copy($source, $target)) {
                $backedUp[] = $cleanFile;
                $log[] = '✅ ' . basename($cleanFile) . ' — backup created';
            } else {
                $failed[] = $cleanFile;
                $log[] = '❌ ' . basename($cleanFile) . ' — backup FAILED (copy error)';
            }
        } else {
            $log[] = '⚠️ ' . basename($cleanFile) . ' — file not found (skip backup)';
        }
    }
    
    return [
        'success' => empty($failed),
        'backup_dir' => $backupDir,
        'backed_up' => $backedUp,
        'failed' => $failed,
        'count' => count($backedUp),
        'log' => $log
    ];
}

/**
 * Обновление файлов с детальным логом
 * @param array $files
 * @return array
 */
public function updateFiles($files) {
    $updated = [];
    $failed = [];
    $log = [];
    
    foreach ($files as $file) {
        $cleanFile = trim($file);
        if (strpos($cleanFile, ' (') !== false) {
            $cleanFile = substr($cleanFile, 0, strpos($cleanFile, ' ('));
        }
        
        $url = "https://raw.githubusercontent.com/{$this->repo}/{$this->branch}/{$cleanFile}";
        $targetPath = $this->baseDir . '/' . $cleanFile;
        $targetDir = dirname($targetPath);
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $log[] = '📥 ' . basename($cleanFile) . ' — downloading...';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'KMS-Monitor-Updater/2.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if ($curlError) {
            $failed[] = $cleanFile . ': CURL error - ' . $curlError;
            $log[] = '❌ ' . basename($cleanFile) . ' — download FAILED (CURL: ' . $curlError . ')';
            continue;
        }
        
        if ($httpCode !== 200) {
            $failed[] = $cleanFile . ': HTTP ' . $httpCode;
            $log[] = '❌ ' . basename($cleanFile) . ' — download FAILED (HTTP ' . $httpCode . ')';
            continue;
        }
        
        $log[] = '✅ ' . basename($cleanFile) . ' — downloaded (' . strlen($content) . ' bytes)';
        
        if (file_put_contents($targetPath, $content) !== false) {
            $updated[] = $cleanFile;
            $log[] = '✅ ' . basename($cleanFile) . ' — installed successfully';
        } else {
            $failed[] = $cleanFile . ': cannot write file';
            $log[] = '❌ ' . basename($cleanFile) . ' — installation FAILED (write error)';
        }
    }
    
    return [
        'success' => empty($failed),
        'updated' => $updated,
        'failed' => $failed,
        'updated_count' => count($updated),
        'failed_count' => count($failed),
        'log' => $log
    ];
}
    
public function performUpdate($files) {
    if (empty($files)) {
        return [
            'success' => false,
            'message' => 'Нет файлов для обновления'
        ];
    }
    
    $allLog = [];
    $allLog[] = '📦 Creating backup...';
    
    $backupResult = $this->backupFiles($files);
    $allLog = array_merge($allLog, $backupResult['log']);
    
    $allLog[] = '📥 Downloading new files...';
    
    $updateResult = $this->updateFiles($files);
    $allLog = array_merge($allLog, $updateResult['log']);
    
    $allLog[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $allLog[] = '✅ Updated: ' . $updateResult['updated_count'] . ' files';
    if ($updateResult['failed_count'] > 0) {
        $allLog[] = '❌ Failed: ' . $updateResult['failed_count'] . ' files';
    }
    
    // === ЗАПИСЬ ЛОГА ПЕРЕД RETURN ===
    $logFile = $this->baseDir . '/vlmcconf/backups/update_log_' . date('Y-m-d_H-i-s') . '.txt';
    file_put_contents($logFile, implode("\n", $allLog));
    // =================================
    
    if ($updateResult['success']) {
        return [
            'success' => true,
            'message' => 'Обновлено ' . $updateResult['updated_count'] . ' файлов. Бэкап: ' . basename($backupResult['backup_dir']),
            'backup_dir' => $backupResult['backup_dir'],
            'updated' => $updateResult['updated'],
            'failed' => $updateResult['failed'],
            'log' => $allLog
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Обновлено ' . $updateResult['updated_count'] . ' файлов, ошибок: ' . $updateResult['failed_count'],
            'backup_dir' => $backupResult['backup_dir'],
            'updated' => $updateResult['updated'],
            'failed' => $updateResult['failed'],
            'log' => $allLog
        ];
    }
}
}
?>