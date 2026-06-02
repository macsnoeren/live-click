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

/* ---- Tijdelijke diagnose: welke Mollie-config gebruikt de server? ----
   Toont GEEN volledige key, alleen of die gezet is + het prefix. Verwijder dit
   blok zodra de configuratie werkt. */
if ($method === 'GET' && ($_GET['debug'] ?? '') === 'config') {
    $key = defined('MOLLIE_API_KEY') ? MOLLIE_API_KEY : '';
    echo json_encode([
        'ok'                => true,
        'mollie_enabled'    => mollieEnabled(),
        'billing_enforced'  => billingEnforced(),
        'api_key_set'       => $key !== '',
        'api_key_prefix'    => $key !== '' ? substr($key, 0, 5) . '…' : '(leeg)',
        'redirect_url'      => defined('MOLLIE_REDIRECT_URL') ? MOLLIE_REDIRECT_URL : '(niet gedefinieerd)',
        'webhook_url'       => defined('MOLLIE_WEBHOOK_URL') ? MOLLIE_WEBHOOK_URL : '(niet gedefinieerd)',
        // Verborgen tekens (spatie/CR/BOM) worden hier zichtbaar als %XX, en de
        // lengte verraadt onverwachte extra bytes.
        'redirect_url_raw'  => defined('MOLLIE_REDIRECT_URL') ? rawurlencode(MOLLIE_REDIRECT_URL) : '',
        'redirect_url_len'  => defined('MOLLIE_REDIRECT_URL') ? strlen(MOLLIE_REDIRECT_URL) : 0,
        'webhook_url_raw'   => defined('MOLLIE_WEBHOOK_URL') ? rawurlencode(MOLLIE_WEBHOOK_URL) : '',
        'webhook_url_len'   => defined('MOLLIE_WEBHOOK_URL') ? strlen(MOLLIE_WEBHOOK_URL) : 0,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---- Tijdelijke diagnose: rauwe testbetaling rechtstreeks naar Mollie ----
   Doet een minimale 'oneoff' betaling met alleen amount+description+redirectUrl+
   webhookUrl, en geeft de EXACTE verzonden body + Mollie's rauwe antwoord terug.
   Zo zien we precies wat Mollie afkeurt. Verwijder dit blok na de diagnose. */
if ($method === 'GET' && ($_GET['debug'] ?? '') === 'testpay') {
    // Optioneel: ?url=… om een andere redirectUrl te testen (bv. een bekend-goede
    // externe https-URL). Zonder webhookUrl als ?nowebhook=1 wordt meegegeven,
    // om te isoleren of het probleem bij de redirect of de webhook zit.
    $override = trim((string)($_GET['url'] ?? ''));
    $body = [
        'amount'      => ['currency' => 'EUR', 'value' => '0.01'],
        'description' => 'LiveGig testpay',
        'redirectUrl' => $override !== '' ? $override : (defined('MOLLIE_REDIRECT_URL') ? MOLLIE_REDIRECT_URL : ''),
    ];
    if (($_GET['nowebhook'] ?? '') !== '1') {
        $body['webhookUrl'] = defined('MOLLIE_WEBHOOK_URL') ? MOLLIE_WEBHOOK_URL : '';
    }
    $json = json_encode($body, JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://api.mollie.com/v2/payments');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MOLLIE_API_KEY, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    header('Content-Type: application/json');
    echo json_encode([
        'sent_body'     => $json,
        'http_code'     => $code,
        'curl_error'    => $err,
        'mollie_raw'    => $raw ? json_decode($raw, true) : null,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
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
