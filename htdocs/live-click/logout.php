<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';

// Logout vereist POST + CSRF. Voorkomt CSRF-uitlog en accidentele logout via prefetch.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Toon een mini-form zodat gebruikers die op /logout.php landen alsnog
    // bewust een POST kunnen doen (i.p.v. een 405 die de UI breekt).
    sessionStart();
    $csrf = csrfToken();
    ?>
    <!doctype html>
    <html lang="nl" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <title>Uitloggen — LiveGig</title>
        <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="d-flex align-items-center justify-content-center min-vh-100">
        <form method="POST" class="text-center">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <p class="mb-3">Weet je zeker dat je wilt uitloggen?</p>
            <button type="submit" class="btn btn-danger">Uitloggen</button>
            <a href="dashboard.php" class="btn btn-link">Annuleren</a>
        </form>
    </body>
    </html>
    <?php
    exit;
}

csrfRequire();
logout();
header('Location: login.php');
exit;
