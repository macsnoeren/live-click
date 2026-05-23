<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$bandId  = (int)($_GET['band_id'] ?? 0);
$redirect = $_GET['redirect'] ?? '../dashboard.php';
if ($bandId) switchBand($bandId);
header('Location: ' . $redirect);
exit;
