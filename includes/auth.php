<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/totp.php';

define('REMEMBER_COOKIE', 'lg_remember');
define('REMEMBER_DAYS',   30);

function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireLogin(): void {
    sessionStart();
    if (empty($_SESSION['user_id'])) {
        // Try remember-me cookie before redirecting
        if (!loginWithRememberToken()) {
            header('Location: ' . appRelPath('login.php'));
            exit;
        }
    }
    // Redirect to profile if admin has required a password change,
    // unless we're already on profile.php (or its API endpoint) to avoid loops.
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_FILENAME']) !== 'profile.php') {
        header('Location: ' . appRelPath('profile.php') . '?force_pw=1');
        exit;
    }
    // Always refresh band from DB so changes by admin are visible immediately.
    refreshSessionBand((int)$_SESSION['user_id']);
}

function refreshSessionBand(int $userId): void {
    // Only refresh if no band is active in session, or if band_id key is missing.
    // To force a full refresh after admin changes, we always re-query.
    $db = getDB();
    $current = $_SESSION['user_band_id'] ?? null;
    $stmt = $db->prepare(
        'SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id
         WHERE bm.user_id = ? ORDER BY b.name LIMIT 1'
    );
    $stmt->execute([$userId]);
    $band = $stmt->fetch();

    // If the currently active band was removed, or none was set, pick the first available.
    if ($current) {
        $check = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id WHERE bm.user_id = ? AND b.id = ?');
        $check->execute([$userId, $current]);
        $stillValid = $check->fetch();
        if ($stillValid) return; // Current band still valid — no change needed.
    }

    $_SESSION['user_band_id']   = $band['id'] ?? null;
    $_SESSION['user_band_name'] = $band['name'] ?? null;
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . appRelPath('dashboard.php'));
        exit;
    }
}

// Returns a path relative to the calling script pointing to a file in the app root.
function appRelPath(string $file): string {
    $appRoot   = realpath(__DIR__ . '/..');
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
    if (!$appRoot || !$scriptDir || $scriptDir === $appRoot) return $file;
    $prefix = '';
    $dir = $scriptDir;
    while ($dir !== $appRoot && strlen($dir) > strlen($appRoot)) {
        $prefix .= '../';
        $dir = realpath($dir . '/..');
        if (!$dir) break;
    }
    return $prefix . $file;
}

function currentUser(): ?array {
    sessionStart();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['user_username'],
        'role'     => $_SESSION['user_role'],
        'band_id'  => $_SESSION['user_band_id'] ?? null,
        'band_name'=> $_SESSION['user_band_name'] ?? null,
    ];
}

/**
 * Verify credentials and start a login session.
 * Returns:
 *   'ok'  — logged in (no 2FA)
 *   '2fa' — credentials OK, 2FA code required (pending data stored in session)
 *   false — bad credentials
 */
function login(string $username, string $password): string|false {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, role, totp_enabled, totp_secret, must_change_password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) return false;

    sessionStart();

    if ($user['totp_enabled']) {
        // Store pending state; full session set only after code is verified
        $_SESSION['2fa_pending_id']       = (int)$user['id'];
        $_SESSION['2fa_pending_username'] = $user['username'];
        $_SESSION['2fa_pending_role']     = $user['role'];
        return '2fa';
    }

    _completeLogin($user);
    return 'ok';
}

/** Complete login after credentials (and optionally 2FA) are verified. */
function _completeLogin(array $user): void {
    sessionStart();
    $_SESSION['user_id']              = (int)$user['id'];
    $_SESSION['user_username']        = $user['username'];
    $_SESSION['user_role']            = $user['role'];
    $_SESSION['must_change_password'] = !empty($user['must_change_password']);
    // Clear any 2FA pending data
    unset($_SESSION['2fa_pending_id'], $_SESSION['2fa_pending_username'],
          $_SESSION['2fa_pending_role'], $_SESSION['2fa_pending_remember']);

    $db = getDB();
    $bm = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id WHERE bm.user_id = ? LIMIT 1');
    $bm->execute([$user['id']]);
    $band = $bm->fetch();
    $_SESSION['user_band_id']   = $band['id'] ?? null;
    $_SESSION['user_band_name'] = $band['name'] ?? null;
}

/**
 * Complete login after TOTP code verification.
 * Returns true on success, false if code is wrong.
 */
function verifyTotpLogin(string $code): bool {
    sessionStart();
    $pendingId = $_SESSION['2fa_pending_id'] ?? null;
    if (!$pendingId) return false;

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, role, totp_secret, must_change_password FROM users WHERE id = ? AND totp_enabled = 1');
    $stmt->execute([$pendingId]);
    $user = $stmt->fetch();
    if (!$user || !Totp::verify($user['totp_secret'], $code)) return false;

    _completeLogin($user);
    return true;
}

function logout(): void {
    sessionStart();
    clearRememberToken();
    session_destroy();
}

// ── Remember-me ──────────────────────────────────────────────────────────────

/** Set a persistent remember-me cookie and store its hash in the DB. */
function createRememberToken(int $userId): void {
    $token = bin2hex(random_bytes(32)); // 64 hex chars
    $hash  = hash('sha256', $token);
    $db    = getDB();
    // One active token per user — replace any existing one
    $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
    $db->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at)
                  VALUES (?, ?, datetime("now", "+' . REMEMBER_DAYS . ' days"))')
       ->execute([$userId, $hash]);
    setcookie(REMEMBER_COOKIE, $token, [
        'expires'  => time() + REMEMBER_DAYS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/** Try to log in using the remember-me cookie. Returns true on success. */
function loginWithRememberToken(): bool {
    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!$token) return false;

    $hash = hash('sha256', $token);
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT rt.user_id, u.username, u.role, u.must_change_password
           FROM remember_tokens rt
           JOIN users u ON u.id = rt.user_id
          WHERE rt.token_hash = ? AND rt.expires_at > datetime("now")'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        _clearRememberCookie();
        return false;
    }

    sessionStart();
    $_SESSION['user_id']              = (int)$row['user_id'];
    $_SESSION['user_username']        = $row['username'];
    $_SESSION['user_role']            = $row['role'];
    $_SESSION['must_change_password'] = !empty($row['must_change_password']);
    $_SESSION['user_band_id']         = null;
    $_SESSION['user_band_name']       = null;
    return true;
}

/** Delete the remember token from DB and clear the cookie. */
function clearRememberToken(): void {
    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($token) {
        $hash = hash('sha256', $token);
        try {
            getDB()->prepare('DELETE FROM remember_tokens WHERE token_hash = ?')->execute([$hash]);
        } catch (Exception $e) {}
    }
    _clearRememberCookie();
}

function _clearRememberCookie(): void {
    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function userBands(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id WHERE bm.user_id = ? ORDER BY b.name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function switchBand(int $bandId): bool {
    sessionStart();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) return false;
    $db = getDB();
    $stmt = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id WHERE bm.user_id = ? AND b.id = ?');
    $stmt->execute([$userId, $bandId]);
    $band = $stmt->fetch();
    if (!$band) return false;
    $_SESSION['user_band_id']   = $band['id'];
    $_SESSION['user_band_name'] = $band['name'];
    return true;
}
