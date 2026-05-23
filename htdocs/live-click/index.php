<?php
require_once __DIR__ . '/includes/auth.php';
sessionStart();
header('Location: ' . (currentUser() ? 'dashboard.php' : 'login.php'));
exit;
