<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
sessionStart();
if (currentUser()) { header('Location: dashboard.php'); exit; }

$error     = '';
$show2fa   = !empty($_SESSION['2fa_pending_id']);
$csrf      = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();

    if (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
        // ── Step 2: verify TOTP code ────────────────────────────────────────
        $pendingId = (int)($_SESSION['2fa_pending_id'] ?? 0);
        // Twee buckets: per-account (strenger) en per-IP (defense-in-depth)
        rateLimitCheck('totp:user:' . $pendingId, 5, 600);
        rateLimitCheck('totp:ip:'   . clientIp(), 20, 600);

        $code = trim($_POST['code'] ?? '');
        if (verifyTotpLogin($code)) {
            if (!empty($_SESSION['2fa_pending_remember'])) {
                createRememberToken($_SESSION['user_id']);
            }
            _doRedirect();
        } else {
            rateLimitRecord('totp:user:' . $pendingId);
            rateLimitRecord('totp:ip:'   . clientIp());
            $error   = 'Ongeldige verificatiecode. Probeer opnieuw.';
            $show2fa = true;
        }

    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_backup') {
        // ── Step 2 alt: backup-code als TOTP-alternatief ──────────────────
        $pendingId = (int)($_SESSION['2fa_pending_id'] ?? 0);
        rateLimitCheck('backup:user:' . $pendingId, 5, 600);
        rateLimitCheck('backup:ip:'   . clientIp(), 20, 600);

        $code = trim($_POST['code'] ?? '');
        if (verifyBackupCodeLogin($code)) {
            if (!empty($_SESSION['2fa_pending_remember'])) {
                createRememberToken($_SESSION['user_id']);
            }
            _doRedirect();
        } else {
            rateLimitRecord('backup:user:' . $pendingId);
            rateLimitRecord('backup:ip:'   . clientIp());
            $error   = 'Ongeldige backup-code.';
            $show2fa = true;
        }

    } elseif (isset($_POST['action']) && $_POST['action'] === 'cancel_2fa') {
        // ── Cancel 2FA step ─────────────────────────────────────────────────
        unset($_SESSION['2fa_pending_id'], $_SESSION['2fa_pending_username'],
              $_SESSION['2fa_pending_role'], $_SESSION['2fa_pending_remember']);
        $show2fa = false;

    } else {
        // ── Step 1: credentials ─────────────────────────────────────────────
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        // Rate-limit op username (sleutel om brute-force op één account te stoppen)
        // én op IP (om verspreid brute-forcen te dempen).
        rateLimitCheck('login:user:' . strtolower($username), 5, 600);
        rateLimitCheck('login:ip:'   . clientIp(),            20, 600);

        $result = login($username, $password);

        if ($result === 'ok') {
            if ($remember) createRememberToken($_SESSION['user_id']);
            _doRedirect();

        } elseif ($result === '2fa') {
            if ($remember) $_SESSION['2fa_pending_remember'] = true;
            $show2fa = true;

        } else {
            rateLimitRecord('login:user:' . strtolower($username));
            rateLimitRecord('login:ip:'   . clientIp());
            $error = 'Gebruikersnaam of wachtwoord onjuist.';
        }
    }
}

function _doRedirect(): void {
    $next = safeLocalRedirect($_GET['next'] ?? '', 'dashboard.php');
    header('Location: ' . $next);
    exit;
}
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LiveGig — Inloggen</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo">
                <div class="beat-dot lit"></div>
                <div class="beat-dot"></div>
                <div class="beat-dot"></div>
                <div class="beat-dot lit"></div>
            </div>
            <h1 class="mt-3 fw-bold text-white fs-3">LiveGig</h1>
            <p class="text-muted small">Click track &amp; setlist beheer</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($show2fa): ?>
        <!-- ── 2FA code step ───────────────────────────────────────────── -->
        <div class="text-center mb-3">
            <div class="mb-2" style="font-size:2rem">🔐</div>
            <p class="text-muted small mb-0">
                Ingelogd als <strong class="text-white"><?= htmlspecialchars($_SESSION['2fa_pending_username'] ?? '') ?></strong>.
                Vul de 6-cijferige code in van je authenticator-app.
            </p>
        </div>
        <?php $useBackup = !empty($_GET['backup']); ?>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="<?= $useBackup ? 'verify_backup' : 'verify_2fa' ?>">
            <div class="mb-3">
                <label class="form-label">
                    <?= $useBackup ? 'Backup-code (10 cijfers)' : 'Verificatiecode' ?>
                </label>
                <?php if ($useBackup): ?>
                <input type="text" name="code" class="form-control text-center fw-bold"
                       maxlength="11" autocomplete="one-time-code" inputmode="numeric"
                       autofocus placeholder="00000-00000"
                       style="letter-spacing:0.15em">
                <?php else: ?>
                <input type="text" name="code" class="form-control text-center fw-bold fs-4 letter-spacing-3"
                       maxlength="6" autocomplete="one-time-code" inputmode="numeric"
                       pattern="[0-9]{6}" autofocus placeholder="000000"
                       style="letter-spacing:0.4em">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-bold">
                <i class="bi bi-shield-check"></i> Verifiëren
            </button>
        </form>
        <div class="text-center mt-2">
            <?php if ($useBackup): ?>
                <a href="login.php" class="text-muted small">← Terug naar verificatiecode</a>
            <?php else: ?>
                <a href="login.php?backup=1" class="text-muted small">Authenticator-app niet beschikbaar? Gebruik een backup-code</a>
            <?php endif; ?>
        </div>
        <form method="POST" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="cancel_2fa">
            <button type="submit" class="btn btn-link btn-sm w-100 text-muted">Annuleren</button>
        </form>

        <?php else: ?>
        <!-- ── Credentials step ───────────────────────────────────────── -->
        <form method="POST" id="login-cred-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div class="mb-3">
                <label class="form-label">Gebruikersnaam</label>
                <input type="text" name="username" class="form-control" autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Wachtwoord</label>
                <input type="password" name="password" class="form-control" id="login-password">
            </div>
            <div class="mb-4 form-check">
                <input type="checkbox" name="remember" value="1" id="remember-me" class="form-check-input"
                       <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
                <label class="form-check-label text-muted small" for="remember-me">
                    Onthoud mij op dit apparaat (<?= REMEMBER_DAYS ?> dagen)
                </label>
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-bold">
                <i class="bi bi-play-fill"></i> Inloggen
            </button>
        </form>

        <hr class="border-secondary my-3">
        <p class="text-center text-muted small mb-0">
            Nog geen account? <a href="register.php" class="text-danger">Registreren</a>
        </p>
        <?php endif; ?>
    </div>
</div>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
/* E2EE-sleutels (zie PRIVACY.md): het wachtwoord is nodig om in de browser de
 * privésleutel te ontgrendelen. We bewaren het kortstondig in sessionStorage
 * (zelfde origin, gaat nooit naar de server) zodat de eerste ingelogde pagina
 * de kluis kan ontgrendelen; daarna wist de bootstrap het meteen. */
(function () {
    var in2fa = <?= $show2fa ? 'true' : 'false' ?>;
    try {
        // Verse login / uitgelogd: oude sleutel + wachtwoord opruimen.
        // Tijdens de 2FA-stap NIET wissen — het wachtwoord uit stap 1 is nog nodig.
        if (!in2fa) {
            sessionStorage.removeItem('lg_priv');
            sessionStorage.removeItem('lg_pw_tmp');
        }
        var form = document.getElementById('login-cred-form');
        if (form) {
            form.addEventListener('submit', function () {
                var pw = document.getElementById('login-password');
                if (pw && pw.value) {
                    try { sessionStorage.setItem('lg_pw_tmp', pw.value); } catch (e) {}
                }
            });
        }
    } catch (e) {}
})();
</script>
</body>
</html>
