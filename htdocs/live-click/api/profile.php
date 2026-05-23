<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
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
    if (strlen($new1) < 8) {
        echo json_encode(['ok' => false, 'error' => 'Wachtwoord moet minimaal 8 tekens zijn.']); exit;
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
    echo json_encode(['ok' => true]); exit;
}

// ── 2FA: start setup (generate + return QR URL) ───────────────────────────────
if ($action === 'enable_2fa_start') {
    // Generate a fresh secret and store it in the session (not DB yet — unconfirmed)
    $secret = Totp::generateSecret();
    $_SESSION['totp_setup_secret'] = $secret;

    echo json_encode([
        'ok'     => true,
        'secret' => $secret,
        'qr_url' => Totp::qrUrl($secret, $user['username']),
    ]);
    exit;
}

// ── 2FA: confirm setup ────────────────────────────────────────────────────────
if ($action === 'enable_2fa_confirm') {
    $secret = $_SESSION['totp_setup_secret'] ?? '';
    $code   = preg_replace('/\D/', '', $data['code'] ?? '');

    if (!$secret) {
        echo json_encode(['ok' => false, 'error' => 'Sessie verlopen. Ververs de pagina.']); exit;
    }
    if (!Totp::verify($secret, $code)) {
        echo json_encode(['ok' => false, 'error' => 'Ongeldige code. Probeer opnieuw.']); exit;
    }

    $db->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?')
       ->execute([$secret, $userId]);
    unset($_SESSION['totp_setup_secret']);
    echo json_encode(['ok' => true]); exit;
}

// ── 2FA: disable ──────────────────────────────────────────────────────────────
if ($action === 'disable_2fa') {
    $code = preg_replace('/\D/', '', $data['code'] ?? '');
    $row  = $db->prepare('SELECT totp_secret FROM users WHERE id = ? AND totp_enabled = 1');
    $row->execute([$userId]);
    $row  = $row->fetch();

    if (!$row || !Totp::verify($row['totp_secret'], $code)) {
        echo json_encode(['ok' => false, 'error' => 'Ongeldige code.']); exit;
    }

    $db->prepare('UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE id = ?')
       ->execute([$userId]);
    echo json_encode(['ok' => true]); exit;
}

echo json_encode(['ok' => false, 'error' => 'Onbekende actie.']);
