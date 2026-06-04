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

/* ---- Tijdelijke diagnose: welke betaalmethodes zijn beschikbaar? ----
   Toont de methodes voor een first-payment (mandaat) bij het ingestelde bedrag,
   plus alle ingeschakelde methodes. Verwijder na de diagnose. */
if ($method === 'GET' && ($_GET['debug'] ?? '') === 'methods') {
    header('Content-Type: application/json');
    $amt = (string)($_GET['amount'] ?? MOLLIE_MANDATE_AMOUNT);
    $fmt = function ($resp) {
        return array_map(
            fn($m) => ($m['id'] ?? '?') . ' — ' . ($m['description'] ?? ''),
            $resp['_embedded']['methods'] ?? []
        );
    };
    $out = ['mandate_amount' => (string)MOLLIE_MANDATE_AMOUNT, 'tested_amount' => $amt];
    try {
        $q = '/methods?sequenceType=first&amount[value]=' . rawurlencode($amt) . '&amount[currency]=EUR';
        $out['first_payment_methods'] = $fmt(mollieRequest('GET', $q));
    } catch (RuntimeException $e) { $out['first_payment_error'] = $e->getMessage(); }
    try {
        $out['all_enabled_methods'] = $fmt(mollieRequest('GET', '/methods'));
    } catch (RuntimeException $e) { $out['all_methods_error'] = $e->getMessage(); }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---- Tijdelijke diagnose: volledige abonnementsrij + laatste betalingen ---- */
if ($method === 'GET' && ($_GET['debug'] ?? '') === 'sub') {
    header('Content-Type: application/json');
    $p = $db->prepare("SELECT mollie_payment_id, amount, status, created_at, paid_at
                         FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $p->execute([$userId]);
    echo json_encode([
        'subscription'    => getUserSubscription($userId),
        'recent_payments' => $p->fetchAll(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            'ends_at'       => $sub['ends_at'],
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
        $sub = getUserSubscription($userId);

        // Écht actief (proef/betaald, niet opgezegd) → niets te doen.
        if ($sub && in_array($sub['status'], ['trialing', 'active'], true)) {
            echo json_encode(['ok' => true, 'already_active' => true]);
            exit;
        }

        // Heractiveren van een OPGEZEGD abonnement: maak een nieuw Mollie-abonnement
        // dat pas ingaat zodra de huidige (proef-/betaalde) periode afloopt — met het
        // bestaande mandaat, dus géén nieuwe activatiebetaling nu. Lukt dat niet
        // (mandaat verlopen), dan valt 'ie door naar de gewone activatieflow.
        if ($sub && $sub['status'] === 'canceled' && !empty($sub['mollie_customer_id'])) {
            $endsAt    = $sub['ends_at'] ?? null;
            $future    = $endsAt && strtotime($endsAt) > time();
            $startDate = $future ? date('Y-m-d', strtotime($endsAt)) : null;
            try {
                $created    = mollieCreateSubscription($sub['mollie_customer_id'], $userId, $startDate);
                // Loopt de proef nog? Dan blijft de status 'trialing' tot die afloopt.
                $stillTrial = $future && !empty($sub['trial_ends_at']) && $endsAt === $sub['trial_ends_at'];
                $newStatus  = $stillTrial ? 'trialing' : 'active';
                $nextPay    = $created['nextPaymentDate'] ?? ($future ? $startDate : date('Y-m-d'));
                $db->prepare(
                    "UPDATE subscriptions
                        SET mollie_subscription_id=?, status=?, canceled_at=NULL,
                            ends_at=NULL, next_payment_at=?
                      WHERE id=?"
                )->execute([$created['id'], $newStatus, $nextPay, $sub['id']]);
                auditLog('subscription.reactivated', 'user', $userId, ['status' => $newStatus]);
                echo json_encode(['ok' => true, 'reactivated' => true]);
                exit;
            } catch (RuntimeException $e) {
                error_log('subscribe.reactivate: ' . $e->getMessage());
                // val door naar de gewone activatieflow (nieuwe first payment)
            }
        }

        // E-mail/naam ophalen voor de Mollie-customer.
        $u = $db->prepare('SELECT username, email FROM users WHERE id = ?');
        $u->execute([$userId]);
        $info = $u->fetch();
        if (!$info) { echo json_encode(['ok' => false, 'error' => 'Gebruiker niet gevonden.']); exit; }

        // Bestaat er al een Mollie-abonnement (bv. door eerder starten), maar staat
        // de lokale status verkeerd op 'pending'? Dan NIET opnieuw beginnen — dat zou
        // een dubbel abonnement + nieuwe afschrijving geven. Herstel alleen de status.
        if ($sub && !empty($sub['mollie_subscription_id']) && $sub['status'] !== 'canceled') {
            $resync = (!empty($sub['trial_ends_at']) && strtotime($sub['trial_ends_at']) > time())
                ? 'trialing' : 'active';
            $db->prepare("UPDATE subscriptions SET status = ? WHERE id = ?")->execute([$resync, $sub['id']]);
            echo json_encode(['ok' => true, 'already_active' => true]);
            exit;
        }

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

        // Toegang blijft tot het einde van de huidige periode: einde proefmaand
        // (bij 'trialing') of einde betaalde maand (next_payment_at). Daarna vervalt
        // het. Het Mollie-abonnement is nu opgezegd, dus mollie_subscription_id leeg.
        $endsAt = ($sub['status'] === 'trialing' && !empty($sub['trial_ends_at']))
            ? $sub['trial_ends_at']
            : ($sub['next_payment_at'] ?? null);
        $db->prepare(
            "UPDATE subscriptions
                SET status='canceled', canceled_at=CURRENT_TIMESTAMP, ends_at=?, mollie_subscription_id=NULL
              WHERE id=?"
        )->execute([$endsAt, $sub['id']]);
        auditLog('subscription.cancel', 'user', $userId, ['ends_at' => $endsAt]);
        echo json_encode(['ok' => true, 'ends_at' => $endsAt]);
        exit;
    }

    /* ---- Tijdelijk testhulpmiddel: abonnement van de huidige gebruiker wissen ----
       Alleen voor admins. Verwijdert de subscription-rij + betalingen zodat je de
       hele flow (incl. gratis proefmaand) schoon opnieuw kunt doorlopen. Zet bij
       Mollie eventueel ook het abonnement stop. Verwijder dit blok vóór productie. */
    if ($action === 'reset') {
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Alleen een admin mag resetten.']); exit;
        }
        $sub = getUserSubscription($userId);
        if ($sub && !empty($sub['mollie_customer_id']) && !empty($sub['mollie_subscription_id'])) {
            try { mollieCancelSubscription($sub['mollie_customer_id'], $sub['mollie_subscription_id']); }
            catch (RuntimeException $e) { error_log('subscribe.reset: ' . $e->getMessage()); }
        }
        $db->prepare('DELETE FROM payments WHERE user_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM subscriptions WHERE user_id = ?')->execute([$userId]);
        auditLog('subscription.reset_for_test', 'user', $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Onbekende actie.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
