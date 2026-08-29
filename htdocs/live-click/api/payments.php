<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireAdmin();
csrfRequire();
header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Lopende abonnementen, met gebruiker en eventueel toegepaste kortingscode.
$subscriptions = $db->query(
    "SELECT s.id, s.status, s.amount, s.currency, s.interval,
            s.trial_ends_at, s.next_payment_at, s.started_at, s.canceled_at, s.ends_at,
            s.created_at, s.trial_used,
            s.mollie_customer_id, s.mollie_subscription_id, s.mollie_mandate_id,
            u.username, u.email,
            d.code AS discount_code
       FROM subscriptions s
       LEFT JOIN users u          ON u.id = s.user_id
       LEFT JOIN discount_codes d ON d.id = s.discount_code_id
      ORDER BY s.created_at DESC"
)->fetchAll();

// Laatste afschrijvingen (gelimiteerd voor een overzichtelijk paneel).
$payments = $db->query(
    "SELECT p.id, p.amount, p.currency, p.status, p.description,
            p.paid_at, p.created_at, p.mollie_payment_id,
            u.username, u.email
       FROM payments p
       LEFT JOIN users u ON u.id = p.user_id
      ORDER BY p.created_at DESC
      LIMIT 200"
)->fetchAll();

// Samenvatting voor de kop van het tabblad.
$summary = [
    'active_subscriptions' => (int)$db->query(
        "SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trialing')"
    )->fetchColumn(),
    // Bruto-omzet over betaalde incasso's (in EUR aangenomen).
    'total_paid' => (float)$db->query(
        "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'"
    )->fetchColumn(),
];

echo json_encode([
    'ok'            => true,
    'summary'       => $summary,
    'subscriptions' => $subscriptions,
    'payments'      => $payments,
]);
