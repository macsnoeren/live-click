<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
logout();
header('Location: login.php');
exit;
