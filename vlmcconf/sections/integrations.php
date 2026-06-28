<?php
// ============================================
// ФАЙЛ: sections/integrations.php
// ВЕРСИЯ: 5.1.0
// ДАТА: 2026-06-05
// @description: Раздел интеграций (в разработке)
// @description: Integrations section (under development)
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}
?>

<div id="section-integrations" class="settings-section <?= $activeSection === 'integrations' ? 'active' : '' ?>">
    <div class="section-title">
        <span>🔌 <?= __('integrations') ?></span>
    </div>

<?php
// Проверка прав
if (!check_permission(PERM_INTEGRATIONS_VIEW)) {
    echo '<div class="alert alert-danger">' . __('access_denied') . '</div>';
    echo '</div>';
    return;
}

// Стилизованный баннер "Раздел в разработке"
?>
<div class="under-development-banner" style="background: linear-gradient(135deg, <?= $themeCSS['primary'] ?>10 0%, <?= $themeCSS['primary'] ?>05 100%); border-left: 4px solid <?= $themeCSS['primary'] ?>; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
    <i class="fas fa-code-branch fa-2x" style="color: <?= $themeCSS['primary'] ?>;"></i>
    <div style="flex: 1;">
        <strong style="font-size: 14px;"><?= __('section_under_development') ?></strong>
        <p style="margin: 4px 0 0 0; font-size: 12px; color: #8aa0bb;"><?= __('integrations_coming_soon') ?></p>
    </div>
    <span class="badge" style="background: <?= $themeCSS['primary'] ?>20; color: <?= $themeCSS['primary'] ?>; padding: 4px 10px; border-radius: 20px; font-size: 11px;">Coming soon</span>
</div>

<?php
// Обработка POST-запросов (сохранена, но кнопки disabled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_permission(PERM_INTEGRATIONS_EDIT)) {
    
    // Сохранение настроек AD
    if (isset($_POST['save_ad_settings'])) {
        $config['integrations']['ad']['enabled'] = isset($_POST['ad_enabled']) ? true : false;
        $config['integrations']['ad']['auth_enabled'] = isset($_POST['ad_auth_enabled']) ? true : false;
        $config['integrations']['ad']['server'] = trim($_POST['ad_server'] ?? '');
        $config['integrations']['ad']['port'] = intval($_POST['ad_port'] ?? 389);
        $config['integrations']['ad']['use_ssl'] = isset($_POST['ad_use_ssl']) ? true : false;
        $config['integrations']['ad']['base_dn'] = trim($_POST['ad_base_dn'] ?? '');
        $config['integrations']['ad']['domain'] = trim($_POST['ad_domain'] ?? '');
        $config['integrations']['ad']['service_user'] = trim($_POST['ad_service_user'] ?? '');
        
        if (!empty($_POST['ad_service_password']) && $_POST['ad_service_password'] !== '********') {
            $config['integrations']['ad']['service_password'] = encrypt_password($_POST['ad_service_password']);
        }
        
        save_config($configFile, $config);
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              ' . __('settings_saved') . '
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
    
    // Тест подключения к AD
    if (isset($_POST['test_ad_connection'])) {
        $result = test_ad_connection();
        $alert_class = $result['success'] ? 'success' : 'danger';
        echo '<div class="alert alert-' . $alert_class . ' alert-dismissible fade show" role="alert">
              ' . htmlspecialchars($result['message']) . '
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
    
    // Синхронизация пользователей из AD
    if (isset($_POST['sync_ad_now'])) {
        $result = sync_ad_users();
        $alert_class = $result['success'] ? 'success' : 'danger';
        echo '<div class="alert alert-' . $alert_class . ' alert-dismissible fade show" role="alert">
              ' . htmlspecialchars($result['message']) . '
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
}

// Загрузка текущих настроек
$ad_enabled = $config['integrations']['ad']['enabled'] ?? false;
$ad_auth_enabled = $config['integrations']['ad']['auth_enabled'] ?? false;
$ad_server = $config['integrations']['ad']['server'] ?? '';
$ad_port = $config['integrations']['ad']['port'] ?? 389;
$ad_use_ssl = $config['integrations']['ad']['use_ssl'] ?? false;
$ad_base_dn = $config['integrations']['ad']['base_dn'] ?? '';
$ad_domain = $config['integrations']['ad']['domain'] ?? '';
$ad_service_user = $config['integrations']['ad']['service_user'] ?? '';
$ad_service_password_placeholder = !empty($config['integrations']['ad']['service_password']) ? '********' : '';
?>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="integrationTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="ad-tab" data-toggle="tab" href="#ad" role="tab"><?php echo __('active_directory'); ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sync-tab" data-toggle="tab" href="#sync" role="tab"><?php echo __('sync_log'); ?></a>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <div class="tab-content">
            
            <!-- Вкладка Active Directory -->
            <div class="tab-pane fade show active" id="ad" role="tabpanel">
                <form method="POST" action="" class="mb-3">
                    <!-- ОСНОВНОЙ ПЕРЕКЛЮЧАТЕЛЬ ВКЛЮЧЕНИЯ ИНТЕГРАЦИИ -->
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="ad_enabled" name="ad_enabled" <?php echo $ad_enabled ? 'checked' : ''; ?> disabled>
                                <label class="custom-control-label" for="ad_enabled">
                                    <strong><?php echo __('enable_ad_integration'); ?></strong>
                                    <br><small class="text-muted"><?php echo __('enable_ad_integration_desc'); ?></small>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="ad_settings" style="<?php echo !$ad_enabled ? 'display: none;' : ''; ?>">
                        
                        <!-- ПЕРЕКЛЮЧАТЕЛЬ АУТЕНТИФИКАЦИИ -->
                        <div class="form-group row">
                            <div class="col-sm-12">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="ad_auth_enabled" name="ad_auth_enabled" <?php echo $ad_auth_enabled ? 'checked' : ''; ?> disabled>
                                    <label class="custom-control-label" for="ad_auth_enabled">
                                        <strong><?php echo __('enable_ad_auth'); ?></strong>
                                        <br><small class="text-muted"><?php echo __('enable_ad_auth_desc'); ?></small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label"><?php echo __('ldap_server'); ?> *</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="ad_server" value="<?php echo htmlspecialchars($ad_server); ?>" placeholder="dc.example.com" disabled>
                                <small class="form-text text-muted"><?php echo __('ldap_server_hint'); ?></small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label"><?php echo __('port'); ?></label>
                            <div class="col-sm-3">
                                <input type="number" class="form-control" name="ad_port" value="<?php echo $ad_port; ?>" disabled>
                            </div>
                            <div class="col-sm-6">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="ad_use_ssl" name="ad_use_ssl" <?php echo $ad_use_ssl ? 'checked' : ''; ?> disabled>
                                    <label class="custom-control-label" for="ad_use_ssl"><?php echo __('use_ssl_tls'); ?></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label"><?php echo __('base_dn'); ?> *</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="ad_base_dn" value="<?php echo htmlspecialchars($ad_base_dn); ?>" placeholder="dc=example,dc=com" disabled>
                                <small class="form-text text-muted"><?php echo __('base_dn_hint'); ?></small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label"><?php echo __('domain'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="ad_domain" value="<?php echo htmlspecialchars($ad_domain); ?>" placeholder="EXAMPLE" disabled>
                                <small class="form-text text-muted"><?php echo __('domain_hint'); ?></small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label"><?php echo __('service_account'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="ad_service_user" value="<?php echo htmlspecialchars($ad_service_user); ?>" placeholder="cn=Administrator,cn=Users,dc=example,dc=com" disabled>
                                <small class="form-text text-muted"><?php echo __('service_account_hint'); ?></small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label"><?php echo __('service_password'); ?></label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" name="ad_service_password" value="<?php echo $ad_service_password_placeholder; ?>" placeholder="<?php echo __('password_leave_empty'); ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="test_ad_connection" class="btn btn-info" disabled><?php echo __('test_connection'); ?></button>
                                <button type="submit" name="save_ad_settings" class="btn btn-primary" disabled><?php echo __('save_settings'); ?></button>
                            </div>
                        </div>
                        
                        <?php if ($ad_auth_enabled && $ad_enabled): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> 
                            <?php echo __('ad_auth_info'); ?>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </form>
            </div>
            
            <!-- Вкладка Журнал синхронизации -->
            <div class="tab-pane fade" id="sync" role="tabpanel">
                <?php if ($ad_enabled && check_permission(PERM_INTEGRATIONS_EDIT)): ?>
                    <form method="POST" action="" class="mb-4">
                        <button type="submit" name="sync_ad_now" class="btn btn-warning" onclick="return confirm('<?php echo __('sync_confirm'); ?>')" disabled>
                            <i class="fas fa-sync-alt"></i> <?php echo __('sync_now'); ?>
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('type'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('details'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sync_log = get_sync_log();
                            if (empty($sync_log)):
                            ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted"><?php echo __('no_sync_logs'); ?></td>
                            </tr>
                            <?php
                            else:
                                foreach ($sync_log as $log):
                            ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', $log['timestamp']); ?></td>
                                <td><?php echo htmlspecialchars($log['type']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo $log['status'] === 'success' ? __('success') : __('failed'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['message']); ?></td>
                            </tr>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
document.getElementById('ad_enabled').addEventListener('change', function() {
    document.getElementById('ad_settings').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php
/**
 * Тестирование подключения к AD
 * @return array
 */
function test_ad_connection() {
    global $config;
    
    if (!extension_loaded('ldap')) {
        return ['success' => false, 'message' => __('ldap_extension_missing')];
    }
    
    $server = $config['integrations']['ad']['server'] ?? '';
    $port = $config['integrations']['ad']['port'] ?? 389;
    $use_ssl = $config['integrations']['ad']['use_ssl'] ?? false;
    $base_dn = $config['integrations']['ad']['base_dn'] ?? '';
    $service_user = $config['integrations']['ad']['service_user'] ?? '';
    $service_password = decrypt_password($config['integrations']['ad']['service_password'] ?? '');
    
    if (empty($server) || empty($base_dn)) {
        return ['success' => false, 'message' => __('ad_config_incomplete')];
    }
    
    $ldap_server = $use_ssl ? "ldaps://{$server}:{$port}" : "ldap://{$server}:{$port}";
    
    $ldapconn = @ldap_connect($ldap_server);
    if (!$ldapconn) {
        return ['success' => false, 'message' => __('ad_connect_failed')];
    }
    
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
    
    if (!empty($service_user) && !empty($service_password)) {
        $bind = @ldap_bind($ldapconn, $service_user, $service_password);
    } else {
        $bind = @ldap_bind($ldapconn);
    }
    
    if (!$bind) {
        $error = ldap_error($ldapconn);
        ldap_close($ldapconn);
        return ['success' => false, 'message' => __('ad_bind_failed') . ': ' . $error];
    }
    
    $search = @ldap_search($ldapconn, $base_dn, '(objectClass=user)', ['cn'], 0, 1);
    if (!$search) {
        $error = ldap_error($ldapconn);
        ldap_close($ldapconn);
        return ['success' => false, 'message' => __('ad_search_failed') . ': ' . $error];
    }
    
    $entries = ldap_get_entries($ldapconn, $search);
    $count = $entries['count'] ?? 0;
    
    ldap_close($ldapconn);
    
    return ['success' => true, 'message' => sprintf(__('ad_connected_users'), $count)];
}

/**
 * Синхронизация пользователей из AD
 * @return array
 */
function sync_ad_users() {
    global $config;
    
    if (!extension_loaded('ldap')) {
        $msg = __('ldap_extension_missing');
        add_sync_log('ad_sync', false, $msg);
        return ['success' => false, 'message' => $msg];
    }
    
    $server = $config['integrations']['ad']['server'] ?? '';
    $port = $config['integrations']['ad']['port'] ?? 389;
    $use_ssl = $config['integrations']['ad']['use_ssl'] ?? false;
    $base_dn = $config['integrations']['ad']['base_dn'] ?? '';
    $service_user = $config['integrations']['ad']['service_user'] ?? '';
    $service_password = decrypt_password($config['integrations']['ad']['service_password'] ?? '');
    
    if (empty($server) || empty($base_dn)) {
        $msg = __('ad_config_incomplete');
        add_sync_log('ad_sync', false, $msg);
        return ['success' => false, 'message' => $msg];
    }
    
    $ldap_server = $use_ssl ? "ldaps://{$server}:{$port}" : "ldap://{$server}:{$port}";
    
    $ldapconn = @ldap_connect($ldap_server);
    if (!$ldapconn) {
        $msg = __('ad_connect_failed');
        add_sync_log('ad_sync', false, $msg);
        return ['success' => false, 'message' => $msg];
    }
    
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
    
    $bind = @ldap_bind($ldapconn, $service_user, $service_password);
    if (!$bind) {
        $error = ldap_error($ldapconn);
        ldap_close($ldapconn);
        $msg = __('ad_bind_failed') . ': ' . $error;
        add_sync_log('ad_sync', false, $msg);
        return ['success' => false, 'message' => $msg];
    }
    
    $filter = '(&(objectClass=user)(objectCategory=person))';
    $attributes = ['cn', 'sAMAccountName', 'mail', 'displayName'];
    $search = @ldap_search($ldapconn, $base_dn, $filter, $attributes);
    
    if (!$search) {
        $error = ldap_error($ldapconn);
        ldap_close($ldapconn);
        $msg = __('ad_search_failed') . ': ' . $error;
        add_sync_log('ad_sync', false, $msg);
        return ['success' => false, 'message' => $msg];
    }
    
    $entries = ldap_get_entries($ldapconn, $search);
    $imported = 0;
    $updated = 0;
    
    $users = getAllUsers();
    
    for ($i = 0; $i < $entries['count']; $i++) {
        $username = $entries[$i]['samaccountname'][0] ?? '';
        $fullname = $entries[$i]['displayname'][0] ?? $entries[$i]['cn'][0] ?? $username;
        $email = $entries[$i]['mail'][0] ?? '';
        
        if (empty($username)) continue;
        
        if (!isset($users[$username])) {
            $users[] = [
                'id' => count($users) + 1,
                'username' => $username,
                'password_hash' => '',
                'permissions' => PERM_LOGS_VIEW,
                'must_change_password' => false,
                'source' => 'ad',
                'fullname' => $fullname,
                'email' => $email,
                'created' => date('Y-m-d H:i:s'),
                'last_sync' => time()
            ];
            $imported++;
        } else {
            $users[$username]['fullname'] = $fullname;
            $users[$username]['email'] = $email;
            $users[$username]['last_sync'] = time();
            if (!isset($users[$username]['source']) || $users[$username]['source'] !== 'ad') {
                $users[$username]['source'] = 'ad_local';
            }
            $updated++;
        }
    }
    
    $users_file = __DIR__ . '/../users.json';
    file_put_contents($users_file, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    ldap_close($ldapconn);
    
    $message = sprintf(__('ad_sync_completed'), $imported, $updated);
    add_sync_log('ad_sync', true, $message);
    
    return ['success' => true, 'imported' => $imported, 'updated' => $updated, 'message' => $message];
}

/**
 * Получение журнала синхронизации
 * @return array
 */
function get_sync_log() {
    $log_file = __DIR__ . '/../cache/sync_log.json';
    if (!file_exists($log_file)) {
        return [];
    }
    $log = json_decode(file_get_contents($log_file), true);
    return is_array($log) ? array_slice($log, 0, 100) : [];
}

/**
 * Добавление записи в журнал синхронизации
 * @param string $type
 * @param bool $status
 * @param string $message
 */
function add_sync_log($type, $status, $message) {
    $log_file = __DIR__ . '/../cache/sync_log.json';
    
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log = [];
    if (file_exists($log_file)) {
        $log = json_decode(file_get_contents($log_file), true);
        if (!is_array($log)) $log = [];
    }
    
    array_unshift($log, [
        'timestamp' => time(),
        'type' => $type,
        'status' => $status ? 'success' : 'failed',
        'message' => $message
    ]);
    
    $log = array_slice($log, 0, 100);
    file_put_contents($log_file, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Простое шифрование пароля
 * @param string $password
 * @return string
 */
function encrypt_password($password) {
    if (empty($password)) return '';
    return base64_encode($password);
}

/**
 * Дешифрование пароля
 * @param string $encrypted
 * @return string
 */
function decrypt_password($encrypted) {
    if (empty($encrypted)) return '';
    return base64_decode($encrypted);
}
?>

</div>