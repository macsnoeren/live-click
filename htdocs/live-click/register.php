<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/db.php';
sessionStart();
if (currentUser()) { header('Location: dashboard.php'); exit; }

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$username || !$email || !$password) {
        $error = 'Vul alle verplichte velden in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } elseif (strlen($password) < 6) {
        $error = 'Wachtwoord moet minimaal 6 tekens zijn.';
    } elseif ($password !== $confirm) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        $db = getDB();
        try {
            $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)')
               ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), 'user']);
            $success = true;
        } catch (PDOException $e) {
            $error = 'Gebruikersnaam of e-mailadres is al in gebruik.';
        }
    }
}
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registreren — LiveGig</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="d-flex align-items-center justify-content-center min-vh-100 px-3">
    <div class="login-card">

        <div class="text-center mb-4">
            <div class="login-logo">
                <div class="beat-dot lit"></div>
                <div class="beat-dot"></div>
                <div class="beat-dot lit"></div>
                <div class="beat-dot"></div>
            </div>
            <h1 class="mt-3 fw-bold text-white fs-3">LiveGig</h1>
            <p class="text-muted small">Account aanmaken</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Account aangemaakt!</strong><br>
            <span class="small">Een admin koppelt je aan een band. Je kunt nu inloggen.</span>
        </div>
        <a href="login.php" class="btn btn-danger w-100 mt-2">
            <i class="bi bi-box-arrow-in-right"></i> Inloggen
        </a>

        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small">
            <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="form-label small">Gebruikersnaam <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" maxlength="50">
            </div>
            <div class="mb-3">
                <label class="form-label small">E-mailadres <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label small">Wachtwoord <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" minlength="6">
                <div class="form-text text-muted" style="font-size:0.72rem">Minimaal 6 tekens</div>
            </div>
            <div class="mb-4">
                <label class="form-label small">Wachtwoord bevestigen <span class="text-danger">*</span></label>
                <input type="password" name="confirm" class="form-control">
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-bold">
                <i class="bi bi-person-plus-fill"></i> Account aanmaken
            </button>
        </form>

        <hr class="border-secondary my-3">
        <p class="text-center text-muted small mb-0">
            Al een account? <a href="login.php" class="text-danger">Inloggen</a>
        </p>

        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
