<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/mollie.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();
$userId = (int)$user['id'];

if (!mollieEnabled()) {
    echo json_encode(['ok' => false, 'error' => 'Betalingen zijn nog niet geconfigureerd.']);
    exit;
}

/* ---- GET: huidige abonnementsstatus ---- */
if ($method === 'GET') {
    $sub = getUserSubscription($userId);
    echo json_encode([
        'ok'           => true,
        'subscription' => $sub ? [
            'status'        => $sub['status'],
            'amount'        => $sub['amount'],
            'currency'      => $sub['currency'],
            'interval'      => $sub['interval'],
            'trial_ends_at' => $sub['trial_ends_at'],
            'next_payment_at' => $sub['next_payment_at'],
            'trial_used'    => (int)$sub['trial_used'],
        ] : null,
        'active'       => userHasActiveBilling($userId),
        'trial_used'   => userTrialUsed($userId),
    ]);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? 'start';

    /* ---- Abonnement starten (eerste betaling / mandaat) ---- */
    if ($action === 'start') {
        // Al actief? Dan niets te doen.
        if (userHasActiveBilling($userId)) {
            echo json_encode(['ok' => true, 'already_active' => true]);
            exit;
        }

        // E-mail/naam ophalen voor de Mollie-customer.
        $u = $db->prepare('SELECT username, email FROM users WHERE id = ?');
        $u->execute([$userId]);
        $info = $u->fetch();
        if (!$info) { echo json_encode(['ok' => false, 'error' => 'Gebruiker niet gevonden.']); exit; }

        $sub = getUserSubscription($userId);

        try {
            // Customer (her)gebruiken of aanmaken.
            $customerId = $sub['mollie_customer_id'] ?? null;
            if (!$customerId) {
                $customer   = mollieCreateCustomer($info['username'], $info['email']);
                $customerId = $customer['id'];
            }

            // First payment → levert de checkout-URL en (na betaling) het mandaat.
            $payment    = mollieCreateFirstPayment($customerId, $userId);
            $checkout   = $payment['_links']['checkout']['href'] ?? null;
            if (!$checkout) { echo json_encode(['ok' => false, 'error' => 'Geen betaal-URL ontvangen van Mollie.']); exit; }
        } catch (RuntimeException $e) {
            error_log('subscribe.start: ' . $e->getMessage());
            // Tijdens het opzetten tonen we de echte Mollie-fout, zodat je kunt
            // zien wat er misgaat (bv. ongeldige key of webhook-URL). Vervang dit
            // later door een generieke melding voor eindgebruikers.
            echo json_encode(['ok' => false, 'error' => 'Mollie: ' . $e->getMessage()]);
            exit;
        }

        // Lokale subscription-rij aanmaken/bijwerken naar 'pending'.
        if ($sub) {
            $db->prepare(
                "UPDATE subscriptions
                    SET mollie_customer_id=?, status='pending', amount=?, currency='EUR',
                        interval=?, canceled_at=NULL
                  WHERE id=?"
            )->execute([$customerId, MOLLIE_SUBSCRIPTION_AMOUNT, MOLLIE_SUBSCRIPTION_INTERVAL, $sub['id']]);
            $subId = (int)$sub['id'];
        } else {
            $db->prepare(
                "INSERT INTO subscriptions (user_id, mollie_customer_id, status, amount, currency, interval)
                 VALUES (?, ?, 'pending', ?, 'EUR', ?)"
            )->execute([$userId, $customerId, MOLLIE_SUBSCRIPTION_AMOUNT, MOLLIE_SUBSCRIPTION_INTERVAL]);
            $subId = (int)$db->lastInsertId();
        }

        // Mandaatbetaling alvast vastleggen voor het Betalingen-overzicht.
        $db->prepare(
            "INSERT OR IGNORE INTO payments (user_id, subscription_id, mollie_payment_id, amount, currency, status, description)
             VALUES (?, ?, ?, ?, 'EUR', ?, ?)"
        )->execute([
            $userId, $subId, $payment['id'],
            (float)MOLLIE_MANDATE_AMOUNT, $payment['status'] ?? 'open',
            MOLLIE_SUBSCRIPTION_DESCRIPTION . ' — activatie',
        ]);

        auditLog('subscription.start', 'user', $userId, ['mollie_customer_id' => $customerId]);
        echo json_encode(['ok' => true, 'checkout_url' => $checkout]);
        exit;
    }

    /* ---- Abonnement opzeggen ---- */
    if ($action === 'cancel') {
        $sub = getUserSubscription($userId);
        if (!$sub) { echo json_encode(['ok' => false, 'error' => 'Geen abonnement gevonden.']); exit; }

        try {
            if (!empty($sub['mollie_customer_id']) && !empty($sub['mollie_subscription_id'])) {
                mollieCancelSubscription($sub['mollie_customer_id'], $sub['mollie_subscription_id']);
            }
        } catch (RuntimeException $e) {
            error_log('subscribe.cancel: ' . $e->getMessage());
            // Toch lokaal opzeggen — Mollie kan al opgezegd zijn.
        }

        $db->prepare("UPDATE subscriptions SET status='canceled', canceled_at=CURRENT_TIMESTAMP WHERE id=?")
           ->execute([$sub['id']]);
        auditLog('subscription.cancel', 'user', $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Onbekende actie.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
