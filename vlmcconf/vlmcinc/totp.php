<?php
// ============================================
// ФАЙЛ: vlmcinc/totp.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-19
// @description: Двухфакторная аутентификация (пункт 34): одноразовые коды TOTP (RFC 6238, SHA-1, 6 цифр, шаг 30 с),
//               секрет хранится зашифрованным, коды восстановления одноразовые (хранятся только хеши).
//               Совместимо с Google Authenticator, Microsoft Authenticator, Authy, 1Password и другими.
// @description: Two-factor authentication (item 34): TOTP (RFC 6238) + single-use recovery codes
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'totp.php') {
    http_response_code(403);
    exit('Access denied');
}

const TOTP_STEP = 30;
const TOTP_DIGITS = 6;
const TOTP_WINDOW = 1;          // допустимый сдвиг часов: ±1 шаг (±30 с)
const TOTP_RECOVERY_COUNT = 8;

function totpB32Encode($bin) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $ch) {
        $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

function totpB32Decode($b32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[\s=-]+/', '', (string)$b32));
    $bits = '';
    foreach (str_split($b32) as $ch) {
        $p = strpos($alphabet, $ch);
        if ($p === false) {
            return '';
        }
        $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/** Новый секрет: 20 случайных байт (160 бит) в Base32 — 32 символа. */
function totpNewSecret() {
    return totpB32Encode(random_bytes(20));
}

/** Код для заданного счётчика (HOTP, RFC 4226). */
function totpCodeAt($secretBin, $counter) {
    $hash = hash_hmac('sha1', pack('J', $counter), $secretBin, true);
    $off = ord($hash[19]) & 0x0F;
    $n = ((ord($hash[$off]) & 0x7F) << 24) | (ord($hash[$off + 1]) << 16) | (ord($hash[$off + 2]) << 8) | ord($hash[$off + 3]);
    return str_pad((string)($n % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Проверка кода. Один и тот же шаг времени принимается только один раз ($lastUsed — последний принятый счётчик),
 * поэтому подсмотренный код повторно не сработает. При успехе $usedCounter получает принятый счётчик.
 */
function totpVerify($secretB32, $code, $lastUsed, &$usedCounter, $now = null) {
    $code = preg_replace('/\s+/', '', (string)$code);
    if (!preg_match('/^\d{' . TOTP_DIGITS . '}$/', $code)) {
        return false;
    }
    $bin = totpB32Decode($secretB32);
    if ($bin === '') {
        return false;
    }
    $now = $now === null ? time() : (int)$now;
    $cur = intdiv($now, TOTP_STEP);
    for ($i = -TOTP_WINDOW; $i <= TOTP_WINDOW; $i++) {
        $c = $cur + $i;
        if ($c <= (int)$lastUsed) {
            continue;
        }
        if (hash_equals(totpCodeAt($bin, $c), $code)) {
            $usedCounter = $c;
            return true;
        }
    }
    return false;
}

/** Адрес для QR-кода и ручного ввода (формат Key URI). */
function totpUri($secret, $account, $issuer = 'KMS Monitor') {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
        . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=' . TOTP_DIGITS . '&period=' . TOTP_STEP;
}

// ---------- секрет и коды восстановления в записи пользователя ----------

function totpEnc($plain) {
    require_once __DIR__ . '/notify.php';
    return notifyEncrypt($plain);
}

function totpDec($stored) {
    require_once __DIR__ . '/notify.php';
    return notifyDecrypt($stored);
}

/** Включена ли у пользователя двухфакторная проверка. */
function totpIsEnabled($user) {
    return is_array($user) && !empty($user['totp']['enabled']) && !empty($user['totp']['secret']);
}

/** Коды восстановления: человекочитаемые (xxxxx-xxxxx) и их хеши для хранения. */
function totpNewRecovery() {
    $plain = [];
    $hashes = [];
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    for ($i = 0; $i < TOTP_RECOVERY_COUNT; $i++) {
        $s = '';
        for ($j = 0; $j < 10; $j++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $plain[] = substr($s, 0, 5) . '-' . substr($s, 5);
        $hashes[] = totpRecoveryHash($s);
    }
    return [$plain, $hashes];
}

function totpRecoveryNorm($code) {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$code));
}

function totpRecoveryHash($code) {
    return hash('sha256', 'kms-recovery|' . totpRecoveryNorm($code));
}

/** Включить двухфакторную проверку: проверяет код по секрету из сессии, сохраняет, возвращает коды восстановления (или null). */
function totpEnable($userId, $secretB32, $code) {
    $used = 0;
    if (!totpVerify($secretB32, $code, 0, $used)) {
        return null;
    }
    list($plain, $hashes) = totpNewRecovery();
    $ok = updateUser($userId, ['totp' => [
        'enabled' => true,
        'secret' => totpEnc($secretB32),
        'last' => $used,
        'recovery' => $hashes,
        'enabled_at' => date('Y-m-d H:i:s'),
    ]]);
    return $ok ? $plain : null;
}

function totpDisable($userId) {
    return updateUser($userId, ['totp' => null]);
}

/** Новые коды восстановления (старые перестают действовать). */
function totpRegenerateRecovery($userId) {
    $user = getUserById($userId);
    if (!totpIsEnabled($user)) {
        return null;
    }
    list($plain, $hashes) = totpNewRecovery();
    $t = $user['totp'];
    $t['recovery'] = $hashes;
    return updateUser($userId, ['totp' => $t]) ? $plain : null;
}

/**
 * Проверка при входе: код из приложения либо один из кодов восстановления.
 * Возвращает 'totp', 'recovery' или false. Принятый код погашается.
 */
function totpCheckLogin($userId, $input) {
    $user = getUserById($userId);
    if (!totpIsEnabled($user)) {
        return false;
    }
    $t = $user['totp'];
    $input = trim((string)$input);
    $secret = totpDec($t['secret']);
    $used = 0;
    if ($secret !== '' && preg_match('/^\s*\d[\d\s]*$/', $input) && totpVerify($secret, $input, (int)($t['last'] ?? 0), $used)) {
        $t['last'] = $used;
        updateUser($userId, ['totp' => $t]);
        return 'totp';
    }
    $norm = totpRecoveryNorm($input);
    if (strlen($norm) === 10) {
        $h = totpRecoveryHash($norm);
        foreach ((array)($t['recovery'] ?? []) as $i => $stored) {
            if (hash_equals((string)$stored, $h)) {
                unset($t['recovery'][$i]);
                $t['recovery'] = array_values($t['recovery']);
                updateUser($userId, ['totp' => $t]);
                return 'recovery';
            }
        }
    }
    return false;
}

/** Сколько кодов восстановления осталось. */
function totpRecoveryLeft($user) {
    return totpIsEnabled($user) ? count((array)($user['totp']['recovery'] ?? [])) : 0;
}
