<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/db.php';
sessionStart();

$token = trim($_GET['token'] ?? '');
if (!$token) { header('Location: dashboard.php'); exit; }

$db   = getDB();
// Token in DB is een SHA-256-hash van de plaintext-token in de URL.
$stmt = $db->prepare(
    'SELECT bi.band_id, b.name AS band_name FROM band_invites bi JOIN bands b ON b.id = bi.band_id WHERE bi.token = ?'
);
$stmt->execute([hash('sha256', $token)]);
$invite = $stmt->fetch();

$user = currentUser();

// If not logged in, redirect to login and come back here
if (!$user) {
    header('Location: ' . appRelPath('login.php') . '?next=' . urlencode('join.php?token=' . rawurlencode($token)));
    exit;
}

$error   = '';
$success = false;

if ($invite) {
    // Check already a member
    $chk = $db->prepare('SELECT 1 FROM band_members WHERE band_id=? AND user_id=?');
    $chk->execute([$invite['band_id'], $user['id']]);
    $alreadyMember = (bool)$chk->fetch();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfRequire();
        if ($alreadyMember) {
            header('Location: bands.php'); exit;
        }
        $db->prepare('INSERT OR IGNORE INTO band_members (user_id, band_id) VALUES (?,?)')
           ->execute([$user['id'], $invite['band_id']]);
        $success = true;
    }
}
$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Band uitnodiging — LiveGig</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="d-flex align-items-center justify-content-center min-vh-100 px-3">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo">
                <div class="beat-dot lit"></div>
                <div class="beat-dot"></div>
                <div class="beat-dot"></div>
                <div class="beat-dot lit"></div>
            </div>
            <h1 class="mt-3 fw-bold text-white fs-3">LiveGig</h1>
        </div>

        <?php if (!$invite): ?>
            <div class="alert alert-danger">
                <i class="bi bi-x-circle me-2"></i>
                Deze uitnodigingslink is niet geldig of is ingetrokken.
            </div>
            <a href="dashboard.php" class="btn btn-secondary w-100">Terug naar dashboard</a>

        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                Je bent nu lid van <strong><?= htmlspecialchars($invite['band_name']) ?></strong>!
            </div>
            <a href="bands.php" class="btn btn-danger w-100">
                <i class="bi bi-people-fill me-2"></i> Naar mijn bands
            </a>

        <?php elseif ($alreadyMember): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Je bent al lid van <strong><?= htmlspecialchars($invite['band_name']) ?></strong>.
            </div>
            <a href="bands.php" class="btn btn-secondary w-100">Naar mijn bands</a>

        <?php else: ?>
            <p class="text-muted text-center mb-3">Je bent uitgenodigd om lid te worden van:</p>
            <div class="card mb-4">
                <div class="card-body text-center py-4">
                    <i class="bi bi-people-fill fs-2 text-danger mb-2 d-block"></i>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($invite['band_name']) ?></h5>
                </div>
            </div>
            <p class="text-muted small text-center mb-3">
                Ingelogd als <strong><?= htmlspecialchars($user['username']) ?></strong>
            </p>
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="btn btn-danger w-100 fw-bold">
                    <i class="bi bi-person-plus-fill me-2"></i> Deelnemen
                </button>
            </form>
            <a href="dashboard.php" class="btn btn-link w-100 text-muted mt-2">Annuleren</a>
        <?php endif; ?>
    </div>
</div>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
