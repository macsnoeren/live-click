<?php
require_once __DIR__ . '/includes/auth.php';
sessionStart();
if (currentUser()) { header('Location: dashboard.php'); exit; }

$error     = '';
$show2fa   = !empty($_SESSION['2fa_pending_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
        // ── Step 2: verify TOTP code ────────────────────────────────────────
        $code = trim($_POST['code'] ?? '');
        if (verifyTotpLogin($code)) {
            if (!empty($_SESSION['2fa_pending_remember'])) {
                createRememberToken($_SESSION['user_id']);
            }
            _doRedirect();
        } else {
            $error   = 'Ongeldige verificatiecode. Probeer opnieuw.';
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

        $result = login($username, $password);

        if ($result === 'ok') {
            if ($remember) createRememberToken($_SESSION['user_id']);
            _doRedirect();

        } elseif ($result === '2fa') {
            if ($remember) $_SESSION['2fa_pending_remember'] = true;
            $show2fa = true;

        } else {
            $error = 'Gebruikersnaam of wachtwoord onjuist.';
        }
    }
}

function _doRedirect(): void {
    $next = $_GET['next'] ?? '';
    if ($next && preg_match('/^[a-zA-Z0-9_\-\.\/\?=&%]+$/', $next) && !str_starts_with($next, '//')) {
        header('Location: ' . $next);
    } else {
        header('Location: dashboard.php');
    }
    exit;
}
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LiveGig — Inloggen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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
        <form method="POST">
            <input type="hidden" name="action" value="verify_2fa">
            <div class="mb-3">
                <label class="form-label">Verificatiecode</label>
                <input type="text" name="code" class="form-control text-center fw-bold fs-4 letter-spacing-3"
                       maxlength="6" autocomplete="one-time-code" inputmode="numeric"
                       pattern="[0-9]{6}" autofocus placeholder="000000"
                       style="letter-spacing:0.4em">
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-bold">
                <i class="bi bi-shield-check"></i> Verifiëren
            </button>
        </form>
        <form method="POST" class="mt-2">
            <input type="hidden" name="action" value="cancel_2fa">
            <button type="submit" class="btn btn-link btn-sm w-100 text-muted">Annuleren</button>
        </form>

        <?php else: ?>
        <!-- ── Credentials step ───────────────────────────────────────── -->
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Gebruikersnaam</label>
                <input type="text" name="username" class="form-control" autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Wachtwoord</label>
                <input type="password" name="password" class="form-control">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
