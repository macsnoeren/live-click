<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/totp.php';

define('REMEMBER_COOKIE', 'lg_remember');
define('REMEMBER_DAYS',   30);

function sessionStart(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Cookies pas afdwingen vóór session_start(); achteraf heeft geen effect.
    // Secure-flag alleen aan onder HTTPS — anders krijgt lokaal dev (HTTP) geen cookie.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
           || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',  // 'Strict' breekt cross-site links zoals join.php?token=...
    ]);
    session_start();
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

/**
 * Geeft een URL-relatief pad terug vanuit het huidige script naar $file in de webroot.
 *
 * Gebruikt APP_DEPTH (gedefinieerd in htdocs/live-click/bootstrap.php) om te weten
 * hoeveel niveaus het huidige script onder de webroot zit.
 *
 * Voorbeelden (webroot = /live-click/):
 *   script = /live-click/join.php       (depth 0) → "login.php"
 *   script = /live-click/api/songs.php  (depth 1) → "../login.php"
 */
function appRelPath(string $file): string {
    $depth = defined('APP_DEPTH') ? (int) APP_DEPTH : 0;
    return str_repeat('../', $depth) . $file;
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

    // Voer áltijd password_verify uit (tegen een dummy hash als de user niet bestaat)
    // zodat de responstijd niet onthult of de username bekend is.
    static $dummyHash = null;
    if ($dummyHash === null) {
        $dummyHash = password_hash('this-account-does-not-exist', PASSWORD_DEFAULT);
    }
    $hash = $user['password_hash'] ?? $dummyHash;
    $ok   = password_verify($password, $hash);
    if (!$user || !$ok) return false;

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
    // Mitigatie session-fixation: nieuw session-id na succesvolle authenticatie.
    session_regenerate_id(true);
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
    auditLog('login.success', 'user', (int)$user['id']);
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

/**
 * Login completen via backup-code als TOTP-alternatief. Backup-code is
 * single-use en wordt verbruikt bij succes.
 */
function verifyBackupCodeLogin(string $code): bool {
    sessionStart();
    $pendingId = (int)($_SESSION['2fa_pending_id'] ?? 0);
    if (!$pendingId) return false;

    if (!consumeTotpBackupCode($pendingId, $code)) return false;

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, role, must_change_password FROM users WHERE id = ? AND totp_enabled = 1');
    $stmt->execute([$pendingId]);
    $user = $stmt->fetch();
    if (!$user) return false;

    _completeLogin($user);
    auditLog('login.backup_code_used', 'user', (int)$user['id']);
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
    // Mitigatie session-fixation: nieuw session-id na succesvolle authenticatie.
    session_regenerate_id(true);
    $_SESSION['user_id']              = (int)$row['user_id'];
    $_SESSION['user_username']        = $row['username'];
    $_SESSION['user_role']            = $row['role'];
    $_SESSION['must_change_password'] = !empty($row['must_change_password']);
    $_SESSION['user_band_id']         = null;
    $_SESSION['user_band_name']       = null;

    // Token rotation — vervang het gebruikte token. Een gestolen oud token wordt
    // hierdoor ongeldig zodra de echte gebruiker terugkomt (detecteerbaar).
    createRememberToken((int)$row['user_id']);
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

// ── 2FA backup codes ─────────────────────────────────────────────────────────

const TOTP_BACKUP_CODE_COUNT = 8;

/** Genereer een set nieuwe backup-codes, sla hashes op, retourneer plaintext. */
function generateTotpBackupCodes(int $userId): array {
    $db = getDB();
    $db->prepare('DELETE FROM totp_backup_codes WHERE user_id=?')->execute([$userId]);
    $ins   = $db->prepare('INSERT INTO totp_backup_codes (user_id, code_hash) VALUES (?, ?)');
    $codes = [];
    for ($i = 0; $i < TOTP_BACKUP_CODE_COUNT; $i++) {
        // 10-cijferige code, gegroepeerd in 5+5 voor leesbaarheid
        $raw  = random_int(0, 9999999999);
        $code = sprintf('%010d', $raw);
        $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
        $ins->execute([$userId, hash('sha256', $code)]);
    }
    return $codes;
}

/** Verbruik een backup-code (single-use). Retourneert true bij succes. */
function consumeTotpBackupCode(int $userId, string $code): bool {
    // Normaliseer: alleen cijfers
    $digits = preg_replace('/\D/', '', $code);
    if (strlen($digits) !== 10) return false;
    $hash = hash('sha256', $digits);
    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM totp_backup_codes WHERE user_id=? AND code_hash=? AND used_at IS NULL');
    $stmt->execute([$userId, $hash]);
    $id = $stmt->fetchColumn();
    if (!$id) return false;
    $db->prepare("UPDATE totp_backup_codes SET used_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    return true;
}

/** Aantal ongebruikte backup-codes voor een user. */
function countUnusedBackupCodes(int $userId): int {
    $s = getDB()->prepare('SELECT COUNT(*) FROM totp_backup_codes WHERE user_id=? AND used_at IS NULL');
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}

// ── Audit log ────────────────────────────────────────────────────────────────

/**
 * Schrijf een gebeurtenis in het audit-log. Failsafe: een logfout mag de
 * eigenlijke actie nooit blokkeren.
 *
 * @param string $action       Korte machine-vriendelijke naam, bv. "user.create"
 * @param ?string $targetType  bv. "user", "band", "song"
 * @param ?int    $targetId    id van het doel
 * @param ?array  $details     extra context; wordt als JSON opgeslagen
 */
function auditLog(string $action, ?string $targetType = null, ?int $targetId = null, ?array $details = null): void {
    try {
        $u  = currentUser();
        $db = getDB();
        $db->prepare(
            'INSERT INTO audit_log (actor_id, actor_username, ip, action, target_type, target_id, details)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $u['id']       ?? null,
            $u['username'] ?? null,
            clientIp(),
            $action,
            $targetType,
            $targetId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Exception $e) {
        error_log('auditLog: ' . $e->getMessage());
    }
}

// ── Rate-limiting ────────────────────────────────────────────────────────────

/** Best-effort client-IP (eenvoudig; aanpassen als achter trusted proxy gedraaid). */
function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Registreert een mislukte poging in een bucket (bv. "login:bob" of "totp:42").
 * Buckets ouder dan 1 uur worden opgeruimd.
 */
function rateLimitRecord(string $bucket): void {
    $db = getDB();
    try {
        $db->prepare('INSERT INTO auth_attempts (bucket) VALUES (?)')->execute([$bucket]);
        // Goedkope cleanup — verwijder records ouder dan 1 uur
        $db->exec("DELETE FROM auth_attempts WHERE ts < datetime('now','-1 hour')");
    } catch (Exception $e) {
        error_log('rateLimitRecord: ' . $e->getMessage());
    }
}

/**
 * Telt mislukte pogingen in $bucket binnen $windowSeconds. Als ≥ $maxAttempts
 * stuur HTTP 429 en exit.
 *
 * Roep deze AAN VOOR de credential-check (zodat een geblokkeerd account
 * geen verdere brute-force attempts kan registreren) maar registreer
 * pogingen ALLEEN bij mislukking.
 */
function rateLimitCheck(string $bucket, int $maxAttempts = 10, int $windowSeconds = 600): void {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM auth_attempts WHERE bucket = ? AND ts > datetime('now','-' || ? || ' seconds')"
        );
        $stmt->execute([$bucket, $windowSeconds]);
        $n = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // Failsafe: bij DB-fout niet blokkeren.
        error_log('rateLimitCheck: ' . $e->getMessage());
        return;
    }
    if ($n >= $maxAttempts) {
        http_response_code(429);
        header('Retry-After: ' . $windowSeconds);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Te veel mislukte pogingen. Probeer het later opnieuw.']);
        } else {
            echo 'Te veel mislukte pogingen. Probeer het over een paar minuten opnieuw.';
        }
        exit;
    }
}

// ── Wachtwoordbeleid ─────────────────────────────────────────────────────────

const PASSWORD_MIN_LENGTH = 12;

/**
 * Controleer wachtwoordsterkte. Retourneert null als OK, anders een
 * gebruikersvriendelijke foutmelding (in het Nederlands).
 */
function validatePasswordStrength(string $password): ?string {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Wachtwoord moet minimaal ' . PASSWORD_MIN_LENGTH . ' tekens zijn.';
    }
    return null;
}

// ── Redirect safety ──────────────────────────────────────────────────────────

/**
 * Geeft het meegegeven pad terug ALS het veilig is voor een Location-header
 * binnen deze app, anders $fallback. Voorkomt open-redirect-misbruik.
 *
 * Veilig = relatief pad binnen dezelfde origin:
 *   - moet beginnen met "/" of letter (geen scheme)
 *   - mag geen "//", "\\", ":", of newline bevatten
 */
function safeLocalRedirect(string $candidate, string $fallback): string {
    $c = trim($candidate);
    if ($c === '') return $fallback;
    if (preg_match('#[\r\n\0]#', $c)) return $fallback;       // header injection
    if (str_contains($c, '\\')) return $fallback;             // backslash spoofing
    if (str_contains($c, '://')) return $fallback;            // any scheme
    if (str_starts_with($c, '//')) return $fallback;          // protocol-relative
    if (str_starts_with($c, '/\\')) return $fallback;
    // Alleen relatief pad of pad dat met / start; geen ":" (data:, javascript:, mailto:, etc.)
    if (str_contains(explode('?', $c, 2)[0], ':')) return $fallback;
    return $c;
}

// ── Band-access helpers ──────────────────────────────────────────────────────

/**
 * Is de huidige (of meegegeven) user lid van $bandId, of admin?
 * Geeft true voor admin onafhankelijk van lidmaatschap.
 */
function userCanAccessBand(int $bandId, ?int $userId = null): bool {
    $user = currentUser();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    $uid = $userId ?? (int)$user['id'];
    $db  = getDB();
    $s   = $db->prepare('SELECT 1 FROM band_members WHERE band_id=? AND user_id=?');
    $s->execute([$bandId, $uid]);
    return (bool)$s->fetch();
}

/**
 * Verwerp request met 403 als de user geen toegang heeft tot $bandId.
 * Gebruik in JSON-API endpoints na requireLogin().
 */
function requireBandAccess(int $bandId): void {
    if (!$bandId || !userCanAccessBand($bandId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang tot deze band.']);
        exit;
    }
}

// ── Band-rollen ──────────────────────────────────────────────────────────────
//
// Per band heeft een lid een rol in band_members.role:
//   'leader'  — beheert leden + rollen én bewerkt inhoud (nummers/setlists)
//   'member'  — bewerkt inhoud, maar beheert geen leden
//   'viewer'  — mag alleen bekijken (dashboard/tekst/PDF), niets wijzigen
//
// De globale admin (users.role = 'admin') mag alles, ongeacht lidmaatschap.

/** Bandrol van de (huidige of opgegeven) user: 'leader'|'member'|'viewer' of null. */
function userBandRole(int $bandId, ?int $userId = null): ?string {
    $user = currentUser();
    if (!$user) return null;
    $uid = $userId ?? (int)$user['id'];
    $s   = getDB()->prepare('SELECT role FROM band_members WHERE band_id=? AND user_id=?');
    $s->execute([$bandId, $uid]);
    $role = $s->fetchColumn();
    return $role === false ? null : (string)$role;
}

/** Mag de huidige user inhoud (nummers/setlists/PDF/tekst) van $bandId bewerken? */
function userCanEditBandContent(int $bandId, ?int $userId = null): bool {
    $user = currentUser();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    return in_array(userBandRole($bandId, $userId), ['leader', 'member'], true);
}

/** Is de huidige user leider van $bandId (of globale admin)? */
function userIsBandLeader(int $bandId, ?int $userId = null): bool {
    $user = currentUser();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    return userBandRole($bandId, $userId) === 'leader';
}

/** 403 als de user de inhoud van $bandId niet mag bewerken (viewer/niet-lid). */
function requireBandContentAccess(int $bandId): void {
    if (!$bandId || !userCanEditBandContent($bandId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Je hebt alleen leesrechten voor deze band.']);
        exit;
    }
}

/** 403 als de user geen leider (of admin) van $bandId is. */
function requireBandLeader(int $bandId): void {
    if (!$bandId || !userIsBandLeader($bandId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Alleen de bandleider mag dit doen.']);
        exit;
    }
}

/** Staat de E2EE-kluis aan voor deze band? (zie PRIVACY.md) */
function bandIsEncrypted(int $bandId): bool {
    if (!$bandId) return false;
    $s = getDB()->prepare('SELECT is_encrypted FROM bands WHERE id = ?');
    $s->execute([$bandId]);
    return (int)$s->fetchColumn() === 1;
}

/** band_id van een song; null als song niet bestaat. */
function getSongBandId(int $songId): ?int {
    $s = getDB()->prepare('SELECT band_id FROM songs WHERE id=?');
    $s->execute([$songId]);
    $v = $s->fetchColumn();
    return $v === false ? null : ($v !== null ? (int)$v : null);
}

/** band_id van een setlist; null als setlist niet bestaat. */
function getSetlistBandId(int $setlistId): ?int {
    $s = getDB()->prepare('SELECT band_id FROM setlists WHERE id=?');
    $s->execute([$setlistId]);
    $v = $s->fetchColumn();
    return $v === false ? null : ($v !== null ? (int)$v : null);
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

/**
 * Genereert/retourneert een per-sessie CSRF-token.
 * Eén token voor de duur van de sessie volstaat; bij login wordt
 * session_regenerate_id() aangeroepen, wat impliciet ook het token rouleert.
 */
function csrfToken(): string {
    sessionStart();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Verifieer een CSRF-token uit een request.
 * Accepteert (in volgorde):
 *   - HTTP-header X-CSRF-Token        (JSON-API calls)
 *   - $_POST['_csrf']                 (HTML-form submits)
 *   - JSON-body { "_csrf": "…" }      (fallback voor JSON POST)
 *
 * Bij mismatch: 403 + exit.
 */
function csrfRequire(): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        return;
    }
    sessionStart();
    $expected = $_SESSION['csrf'] ?? '';

    $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!$supplied) {
        // JSON-body kan een _csrf veld bevatten
        $raw = file_get_contents('php://input');
        if ($raw) {
            $body = json_decode($raw, true);
            $supplied = is_array($body) ? ($body['_csrf'] ?? '') : '';
        }
    }

    if (!$expected || !$supplied || !hash_equals($expected, (string)$supplied)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'CSRF-token ontbreekt of klopt niet. Vernieuw de pagina.']);
        } else {
            echo 'CSRF-token ontbreekt of klopt niet. Vernieuw de pagina en probeer opnieuw.';
        }
        exit;
    }
}
