<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$user   = currentUser();
$userId = (int)$user['id'];
$db     = getDB();
$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $data['action'] ?? '';

// ── Change password ───────────────────────────────────────────────────────────
if ($action === 'change_password') {
    $current = $data['current'] ?? '';
    $new1    = $data['new1']    ?? '';
    $new2    = $data['new2']    ?? '';

    if (!$current || !$new1 || !$new2) {
        echo json_encode(['ok' => false, 'error' => 'Vul alle velden in.']); exit;
    }
    if ($new1 !== $new2) {
        echo json_encode(['ok' => false, 'error' => 'Nieuwe wachtwoorden komen niet overeen.']); exit;
    }
    if (($pwErr = validatePasswordStrength($new1)) !== null) {
        echo json_encode(['ok' => false, 'error' => $pwErr]); exit;
    }

    $row = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $row->execute([$userId]);
    $row = $row->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        echo json_encode(['ok' => false, 'error' => 'Huidig wachtwoord onjuist.']); exit;
    }

    $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
       ->execute([password_hash($new1, PASSWORD_DEFAULT), $userId]);
    // Clear force-change flag from session so the redirect stops
    unset($_SESSION['must_change_password']);
    auditLog('user.password_change', 'user', $userId);
    echo json_encode(['ok' => true]); exit;
}

// ── 2FA: start setup (generate + return QR URL) ───────────────────────────────
if ($action === 'enable_2fa_start') {
    // Als 2FA al actief is moet de gebruiker eerst expliciet disable_2fa doen
    // (vereist huidige TOTP-code). Hierdoor kan een gekaapte sessie niet ongezien
    // de authenticator vervangen.
    $cur = $db->prepare('SELECT totp_enabled FROM users WHERE id = ?');
    $cur->execute([$userId]);
    if ((int)$cur->fetchColumn() === 1) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Schakel eerst de huidige 2FA uit voordat je een nieuwe instelt.']);
        exit;
    }

    // Generate a fresh secret and store it in the session (not DB yet — unconfirmed)
    $secret = Totp::generateSecret();
    $_SESSION['totp_setup_secret'] = $secret;

    // De secret én otpauth-URI worden lokaal in de UI getoond.
    // Geen externe call — de secret verlaat de server nooit.
    echo json_encode([
        'ok'           => true,
        'secret'       => $secret,
        'otpauth_uri'  => Totp::otpauth($secret, $user['username']),
    ]);
    exit;
}

// ── 2FA: confirm setup ────────────────────────────────────────────────────────
if ($action === 'enable_2fa_confirm') {
    // Dubbele check: ook hier blokkeren als 2FA inmiddels actief is geworden.
    $cur = $db->prepare('SELECT totp_enabled FROM users WHERE id = ?');
    $cur->execute([$userId]);
    if ((int)$cur->fetchColumn() === 1) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => '2FA is al actief. Schakel deze eerst uit.']);
        exit;
    }

    rateLimitCheck('2fa_confirm:user:' . $userId, 5, 600);
    rateLimitCheck('2fa_confirm:ip:'   . clientIp(), 20, 600);

    $secret = $_SESSION['totp_setup_secret'] ?? '';
    $code   = preg_replace('/\D/', '', $data['code'] ?? '');

    if (!$secret) {
        echo json_encode(['ok' => false, 'error' => 'Sessie verlopen. Ververs de pagina.']); exit;
    }
    if (!Totp::verify($secret, $code)) {
        rateLimitRecord('2fa_confirm:user:' . $userId);
        rateLimitRecord('2fa_confirm:ip:'   . clientIp());
        echo json_encode(['ok' => false, 'error' => 'Ongeldige code. Probeer opnieuw.']); exit;
    }

    $db->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?')
       ->execute([$secret, $userId]);
    unset($_SESSION['totp_setup_secret']);
    $backupCodes = generateTotpBackupCodes($userId);
    auditLog('user.2fa_enable', 'user', $userId);
    echo json_encode(['ok' => true, 'backup_codes' => $backupCodes]); exit;
}

// ── 2FA: disable ──────────────────────────────────────────────────────────────
if ($action === 'disable_2fa') {
    rateLimitCheck('2fa_disable:user:' . $userId, 5, 600);
    rateLimitCheck('2fa_disable:ip:'   . clientIp(), 20, 600);

    $code = preg_replace('/\D/', '', $data['code'] ?? '');
    $row  = $db->prepare('SELECT totp_secret FROM users WHERE id = ? AND totp_enabled = 1');
    $row->execute([$userId]);
    $row  = $row->fetch();

    if (!$row || !Totp::verify($row['totp_secret'], $code)) {
        rateLimitRecord('2fa_disable:user:' . $userId);
        rateLimitRecord('2fa_disable:ip:'   . clientIp());
        echo json_encode(['ok' => false, 'error' => 'Ongeldige code.']); exit;
    }

    $db->prepare('UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE id = ?')
       ->execute([$userId]);
    $db->prepare('DELETE FROM totp_backup_codes WHERE user_id = ?')->execute([$userId]);
    auditLog('user.2fa_disable', 'user', $userId);
    echo json_encode(['ok' => true]); exit;
}

// ── 2FA: regenereer backup-codes (vereist huidige TOTP-code) ───────────────────
if ($action === 'regenerate_backup_codes') {
    rateLimitCheck('2fa_backup_regen:user:' . $userId, 5, 600);

    $code = preg_replace('/\D/', '', $data['code'] ?? '');
    $row  = $db->prepare('SELECT totp_secret FROM users WHERE id = ? AND totp_enabled = 1');
    $row->execute([$userId]);
    $row  = $row->fetch();
    if (!$row || !Totp::verify($row['totp_secret'], $code)) {
        rateLimitRecord('2fa_backup_regen:user:' . $userId);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige verificatiecode.']); exit;
    }
    $codes = generateTotpBackupCodes($userId);
    auditLog('user.2fa_backup_regenerate', 'user', $userId);
    echo json_encode(['ok' => true, 'backup_codes' => $codes]); exit;
}

// ── 2FA: aantal nog ongebruikte backup-codes (GET via POST {action}) ──────────
if ($action === 'backup_codes_count') {
    echo json_encode(['ok' => true, 'remaining' => countUnusedBackupCodes($userId)]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Onbekende actie.']);
