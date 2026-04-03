<?php
// ============================================
// ФАЙЛ: vlmcinc/users.php
// ВЕРСИЯ: 3.7.0
// ДАТА: 2026-03-28
// @description: Управление пользователями с детальной валидацией
// ============================================

// Права доступа (битовые маски)
define('PERM_GROUPS_NONE', 0);
define('PERM_GROUPS_VIEW', 1);
define('PERM_GROUPS_EDIT', 2);

define('PERM_DEVICES_NONE', 0);
define('PERM_DEVICES_VIEW', 4);
define('PERM_DEVICES_EDIT', 8);

define('PERM_LOGS_NONE', 0);
define('PERM_LOGS_VIEW', 16);
define('PERM_LOGS_EDIT', 32);

define('PERM_USERS_NONE', 0);
define('PERM_USERS_VIEW', 64);
define('PERM_USERS_EDIT', 128);

define('PERM_INFO_NONE', 0);
define('PERM_INFO_VIEW', 256);

// Полные права администратора
define('PERM_ADMIN_FULL', 
    PERM_GROUPS_VIEW | PERM_GROUPS_EDIT |
    PERM_DEVICES_VIEW | PERM_DEVICES_EDIT |
    PERM_LOGS_VIEW | PERM_LOGS_EDIT |
    PERM_USERS_VIEW | PERM_USERS_EDIT |
    PERM_INFO_VIEW
);

/**
 * Получить путь к файлу с пользователями
 */
function getUsersFile() {
    return dirname(__DIR__) . '/users.json';
}

/**
 * Проверка логина
 */
function validateUsername($username) {
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = __('username_min_length');
    }
    if (!preg_match('/^[a-zA-Zа-яА-Я0-9_-]+$/u', $username)) {
        $errors[] = __('username_invalid_chars');
    }
    
    return $errors;
}

/**
 * Проверка сложности пароля с детальными сообщениями
 */
function checkPasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = __('password_min_length');
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = __('password_need_uppercase');
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = __('password_need_lowercase');
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = __('password_need_digit');
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|]/', $password)) {
        $errors[] = __('password_need_special');
    }
    
    return $errors;
}

function isStrongPassword($password) {
    return empty(checkPasswordStrength($password));
}

/**
 * Инициализация файла с пользователями
 */
function initUsers() {
    $file = getUsersFile();
    if (file_exists($file)) return;
    
    $hash = password_hash('root', PASSWORD_DEFAULT);
    $data = [
        'users' => [
            [
                'id' => 1,
                'username' => 'root',
                'password_hash' => $hash,
                'permissions' => PERM_ADMIN_FULL,
                'must_change_password' => true,
                'created' => date('Y-m-d H:i:s'),
                'last_login' => null
            ]
        ]
    ];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Получить всех пользователей
 */
function getAllUsers() {
    initUsers();
    $file = getUsersFile();
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    return $data['users'] ?? [];
}

function getUserById($id) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['id'] == $id) return $user;
    }
    return null;
}

function getUserByUsername($username) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['username'] === $username) return $user;
    }
    return null;
}

function verifyUser($username, $password) {
    $user = getUserByUsername($username);
    if (!$user) return false;
    return password_verify($password, $user['password_hash']);
}

function updateUser($id, $data) {
    $users = getAllUsers();
    $found = false;
    foreach ($users as &$user) {
        if ($user['id'] == $id) {
            $found = true;
            if (isset($data['password'])) {
                $user['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $user['must_change_password'] = false;
            }
            if (isset($data['permissions'])) $user['permissions'] = (int)$data['permissions'];
            if (isset($data['must_change_password'])) $user['must_change_password'] = $data['must_change_password'];
            if (isset($data['last_login'])) $user['last_login'] = $data['last_login'];
            break;
        }
    }
    if (!$found) return false;
    $file = getUsersFile();
    file_put_contents($file, json_encode(['users' => $users], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}

function updateLastLogin($username) {
    $user = getUserByUsername($username);
    if ($user) updateUser($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
}

/**
 * Добавить пользователя с детальной валидацией
 */
function addUser($username, $password, $permissions = PERM_DEVICES_VIEW) {
    $users = getAllUsers();
    
    // Проверка существования пользователя
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return ['success' => false, 'message' => 'Пользователь уже существует'];
        }
    }
    
    // Валидация логина
    $usernameErrors = validateUsername($username);
    if (!empty($usernameErrors)) {
        return ['success' => false, 'message' => implode('<br>', $usernameErrors)];
    }
    
    // Валидация пароля
    $passwordErrors = checkPasswordStrength($password);
    if (!empty($passwordErrors)) {
        return ['success' => false, 'message' => implode('<br>', $passwordErrors)];
    }
    
    $newId = 1;
    foreach ($users as $user) {
        if ($user['id'] >= $newId) $newId = $user['id'] + 1;
    }
    
    $users[] = [
        'id' => $newId,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'permissions' => (int)$permissions,
        'must_change_password' => false,
        'created' => date('Y-m-d H:i:s'),
        'last_login' => null
    ];
    
    $file = getUsersFile();
    $data = ['users' => $users];
    $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result !== false) {
        return ['success' => true, 'message' => 'Пользователь добавлен'];
    }
    return ['success' => false, 'message' => 'Ошибка сохранения'];
}

function deleteUser($id) {
    if ($id == 1) {
        return ['success' => false, 'message' => 'Нельзя удалить главного администратора'];
    }
    $users = getAllUsers();
    $newUsers = [];
    $deleted = false;
    foreach ($users as $user) {
        if ($user['id'] != $id) {
            $newUsers[] = $user;
        } else {
            $deleted = true;
        }
    }
    if (!$deleted) {
        return ['success' => false, 'message' => 'Пользователь не найден'];
    }
    $file = getUsersFile();
    $result = file_put_contents($file, json_encode(['users' => $newUsers], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($result !== false) {
        return ['success' => true, 'message' => 'Пользователь удален'];
    }
    return ['success' => false, 'message' => 'Ошибка сохранения'];
}

function changeUserPasswordAsAdmin($userId, $newPassword) {
    $user = getUserById($userId);
    if (!$user) return ['success' => false, 'message' => 'Пользователь не найден'];
    
    $errors = checkPasswordStrength($newPassword);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }
    
    if (updateUser($userId, ['password' => $newPassword])) {
        return ['success' => true, 'message' => 'Пароль пользователя изменен'];
    }
    return ['success' => false, 'message' => 'Ошибка при смене пароля'];
}