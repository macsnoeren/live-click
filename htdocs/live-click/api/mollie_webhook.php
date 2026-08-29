<?php
/**
 * Mollie-webhook: ontvangt statusmeldingen van betalingen.
 *
 * Beveiliging: Mollie stuurt alleen een payment-id (geen CSRF, geen sessie).
 * We vertrouwen die melding NIET op zichzelf — we halen de betaling opnieuw op
 * bij de Mollie-API en handelen op basis van de echte status. Antwoord altijd
 * met HTTP 200 zodra we de melding verwerkt hebben, anders blijft Mollie het
 * opnieuw proberen.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/mollie.php';
require_once APP_ROOT . '/includes/auth.php';   // voor auditLog()

header('Content-Type: text/plain');

$paymentId = $_POST['id'] ?? '';
if ($paymentId === '' || !mollieEnabled()) {
    http_response_code(200); // niets te doen, maar geen retry uitlokken
    echo 'ignored';
    exit;
}

$db = getDB();

try {
    $payment = mollieGetPayment($paymentId);
} catch (RuntimeException $e) {
    error_log('mollie_webhook: ' . $e->getMessage());
    http_response_code(200);
    echo 'fetch-failed';
    exit;
}

$status     = $payment['status'] ?? 'open';
$customerId = $payment['customerId'] ?? null;
$seq        = $payment['sequenceType'] ?? 'oneoff';
$amount     = (float)($payment['amount']['value'] ?? 0);
$currency   = $payment['amount']['currency'] ?? 'EUR';
$mandateId  = $payment['mandateId'] ?? null;
$paidAt     = $payment['paidAt'] ?? null;

// Bijbehorende lokale subscription (één per customer/gebruiker).
$sub = null;
if ($customerId) {
    $s = $db->prepare('SELECT * FROM subscriptions WHERE mollie_customer_id = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$customerId]);
    $sub = $s->fetch() ?: null;
}

// Betaling vastleggen/bijwerken (idempotent op mollie_payment_id).
$exists = $db->prepare('SELECT id FROM payments WHERE mollie_payment_id = ?');
$exists->execute([$paymentId]);
if ($exists->fetchColumn()) {
    $db->prepare('UPDATE payments SET status=?, amount=?, currency=?, paid_at=? WHERE mollie_payment_id=?')
       ->execute([$status, $amount, $currency, $paidAt, $paymentId]);
} else {
    $db->prepare(
        "INSERT INTO payments (user_id, subscription_id, mollie_payment_id, amount, currency, status, description, paid_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $sub['user_id'] ?? null,
        $sub['id'] ?? null,
        $paymentId, $amount, $currency, $status,
        $payment['description'] ?? '',
        $paidAt,
    ]);
}

if (!$sub) {
    http_response_code(200);
    echo 'no-subscription';
    exit;
}

$userId = (int)$sub['user_id'];

if ($seq === 'first') {
    if ($status === 'paid') {
        // Mandaat verkregen. Maak het terugkerende abonnement aan (idempotent).
        if (empty($sub['mollie_subscription_id'])) {
            $withTrial = ((int)$sub['trial_used'] === 0);
            $startDate = $withTrial
                ? date('Y-m-d', time() + (int)MOLLIE_TRIAL_DAYS * 86400)
                : null;
            try {
                $created = mollieCreateSubscription($sub['mollie_customer_id'], $userId, $startDate,
                              (string)$sub['amount'], (string)$sub['interval']);
            } catch (RuntimeException $e) {
                error_log('mollie_webhook create-subscription: ' . $e->getMessage());
                http_response_code(200);
                echo 'subscription-create-failed';
                exit;
            }
            $newStatus    = $withTrial ? 'trialing' : 'active';
            $trialEndsAt  = $withTrial ? date('Y-m-d H:i:s', time() + (int)MOLLIE_TRIAL_DAYS * 86400) : null;
            $nextPayment  = $created['nextPaymentDate'] ?? ($withTrial ? $startDate : date('Y-m-d'));

            $db->prepare(
                "UPDATE subscriptions
                    SET mollie_subscription_id=?, mollie_mandate_id=?, status=?,
                        trial_used=1, trial_ends_at=?, started_at=CURRENT_TIMESTAMP, next_payment_at=?
                  WHERE id=?"
            )->execute([
                $created['id'], $mandateId, $newStatus,
                $trialEndsAt, $nextPayment, $sub['id'],
            ]);
            auditLog('subscription.activated', 'user', $userId, ['status' => $newStatus]);
        } else {
            // Het abonnement bestond al (bv. door een herstart), maar de status
            // was op 'pending'/'suspended' blijven hangen. Zet die weer goed i.p.v.
            // niets te doen — anders blijft de terugkeerpagina "verwerken" tonen.
            if (in_array($sub['status'], ['pending', 'suspended'], true)) {
                $active = (!empty($sub['trial_ends_at']) && strtotime($sub['trial_ends_at']) > time())
                    ? 'trialing' : 'active';
                $db->prepare("UPDATE subscriptions SET status=? WHERE id=?")->execute([$active, $sub['id']]);
                auditLog('subscription.reactivated', 'user', $userId, ['status' => $active]);
            }
        }
    } elseif (in_array($status, ['failed', 'canceled', 'expired'], true)) {
        // Mandaat niet verkregen — blijft 'pending', gebruiker kan opnieuw proberen.
        auditLog('subscription.mandate_failed', 'user', $userId, ['status' => $status]);
    }
    http_response_code(200);
    echo 'ok';
    exit;
}

// Terugkerende incasso (sequenceType=recurring).
if ($status === 'paid') {
    $db->prepare("UPDATE subscriptions SET status='active', started_at=COALESCE(started_at, CURRENT_TIMESTAMP) WHERE id=?")
       ->execute([$sub['id']]);
} elseif (in_array($status, ['failed', 'expired'], true)) {
    // Incasso mislukt → opschorten. De bijbehorende bands worden hierdoor
    // geblokkeerd (bandIsBlocked kijkt naar actieve facturatie van de leider).
    $db->prepare("UPDATE subscriptions SET status='suspended' WHERE id=?")->execute([$sub['id']]);
    auditLog('subscription.payment_failed', 'user', $userId);
}

http_response_code(200);
echo 'ok';
