<?php
/**
 * Dagelijkse tariefverwerking.
 *
 * Past het op dat moment geldende tarief (currentPricing) toe op lopende
 * abonnementen waarvan het bedrag afwijkt: het Mollie-abonnement wordt bijgewerkt
 * (nieuwe prijs geldt vanaf de eerstvolgende incasso) en de lokale snapshot mee.
 * Zo betalen bestaande abonnees bij hun volgende verlenging het nieuwe tarief.
 *
 * Aanroepen:
 *   - CLI:  php api/cron.php            (geen token nodig)
 *   - HTTP: GET api/cron.php?token=…    (token = CRON_TOKEN / LIVEGIG_CRON_TOKEN)
 *           of header  X-Cron-Token: …
 * Plan dit één keer per dag in (bv. een Kubernetes CronJob).
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/mollie.php';
require_once APP_ROOT . '/includes/pricing.php';
require_once APP_ROOT . '/includes/auth.php'; // auditLog()

$cli = (php_sapi_name() === 'cli');
if (!$cli) {
    header('Content-Type: application/json');
    $token    = $_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
    $expected = defined('CRON_TOKEN') ? CRON_TOKEN : '';
    if ($expected === '' || !hash_equals($expected, (string)$token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige of ontbrekende cron-token.']);
        exit;
    }
}

if (!mollieEnabled()) {
    echo json_encode(['ok' => true, 'skipped' => 'mollie disabled']);
    exit;
}

$db      = getDB();
$pricing = currentPricing();
$total   = pricingTotal($pricing);

// Lopende abonnementen met een geldig Mollie-abonnement.
$rows = $db->query(
    "SELECT id, user_id, amount, mollie_customer_id, mollie_subscription_id
       FROM subscriptions
      WHERE status IN ('active','trialing')
        AND mollie_subscription_id IS NOT NULL
        AND mollie_customer_id IS NOT NULL"
)->fetchAll();

$checked = 0; $updated = 0; $failed = 0;
foreach ($rows as $s) {
    $checked++;
    if (abs((float)$s['amount'] - $total) < 0.01) continue; // al op het juiste tarief
    try {
        mollieUpdateSubscriptionAmount($s['mollie_customer_id'], $s['mollie_subscription_id'], (string)$total);
        $db->prepare(
            "UPDATE subscriptions SET amount=?, base_amount=?, mollie_fee=?, vat_percent=? WHERE id=?"
        )->execute([$total, $pricing['base_amount'], $pricing['mollie_fee'], $pricing['vat_percent'], $s['id']]);
        auditLog('subscription.price_updated', 'user', (int)$s['user_id'], ['new_amount' => $total]);
        $updated++;
    } catch (RuntimeException $e) {
        error_log('cron price update sub ' . $s['id'] . ': ' . $e->getMessage());
        $failed++;
    }
}

echo json_encode([
    'ok'            => true,
    'current_total' => $total,
    'effective_from'=> $pricing['effective_from'] ?? null,
    'checked'       => $checked,
    'updated'       => $updated,
    'failed'        => $failed,
]);
