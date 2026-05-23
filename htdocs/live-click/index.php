<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
sessionStart();
header('Location: ' . (currentUser() ? 'dashboard.php' : 'login.php'));
exit;
