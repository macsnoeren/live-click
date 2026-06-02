<?php
/**
 * Mollie-integratie + facturatie-helpers voor LiveGig.
 *
 * Verdienmodel (zie gesprek/PRIVACY-context):
 *   - Registreren en lid/kijker zijn: gratis.
 *   - Een band aanmaken / leider worden: vereist een actief abonnement of een
 *     lopende proefperiode. De gratis proefmaand is EENMALIG per gebruiker.
 *   - Eén abonnement per gebruiker (niet per band); het dekt alle bands die de
 *     gebruiker leidt.
 *
 * Betaalflow:
 *   1. "first payment" (sequenceType=first) → klant geeft een incassomandaat af.
 *   2. Zodra die betaling 'paid' is (webhook), maken we het Mollie-abonnement aan
 *      met een startDate +MOLLIE_TRIAL_DAYS → de eerste echte incasso valt pas na
 *      de gratis maand. Heeft de gebruiker zijn proef al verbruikt, dan start het
 *      abonnement direct (startDate=vandaag).
 *   3. Mollie incasseert daarna automatisch; elke incasso meldt zich via de webhook.
 *
 * Geen Composer-dependency: directe REST-calls met cURL.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const MOLLIE_API_BASE = 'https://api.mollie.com/v2';

/** Staat de betaalfunctie aan? (API-key geconfigureerd) */
function mollieEnabled(): bool {
    return defined('MOLLIE_API_KEY') && MOLLIE_API_KEY !== '';
}

/**
 * Wordt de paywall daadwerkelijk afgedwongen? Vereist zowel een geconfigureerde
 * key ALS de expliciete schakelaar MOLLIE_BILLING_ENFORCED. Zo lock je niet per
 * ongeluk iedereen buiten zodra er een (test)key staat — testen kan los van
 * afdwingen.
 */
function billingEnforced(): bool {
    return mollieEnabled() && defined('MOLLIE_BILLING_ENFORCED') && MOLLIE_BILLING_ENFORCED === true;
}

/**
 * Doe een geauthenticeerde call naar de Mollie-API.
 *
 * @throws RuntimeException bij transport- of API-fouten.
 * @return array Gedecodeerde JSON-respons (leeg array bij 204).
 */
function mollieRequest(string $method, string $path, ?array $body = null): array {
    if (!mollieEnabled()) {
        throw new RuntimeException('Mollie is niet geconfigureerd (MOLLIE_API_KEY ontbreekt).');
    }
    $url = str_starts_with($path, 'http') ? $path : MOLLIE_API_BASE . $path;

    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . MOLLIE_API_KEY,
        'Content-Type: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Mollie-verbinding mislukt: ' . $err);
    }
    if ($code === 204 || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Onverwacht antwoord van Mollie (HTTP ' . $code . ').');
    }
    if ($code >= 400) {
        $detail = $data['detail'] ?? ('HTTP ' . $code);
        throw new RuntimeException('Mollie-fout: ' . $detail);
    }
    return $data;
}

// ── Mollie API-acties ────────────────────────────────────────────────────────

/** Maak (of hergebruik) een Mollie-customer voor een LiveGig-gebruiker. */
function mollieCreateCustomer(string $name, string $email): array {
    return mollieRequest('POST', '/customers', [
        'name'  => $name !== '' ? $name : $email,
        'email' => $email,
    ]);
}

/**
 * Eerste betaling om het incassomandaat te vestigen (sequenceType=first).
 * De klant rekent dit eenmalig af (iDEAL/kaart); daarna kan automatisch
 * geïncasseerd worden.
 */
function mollieCreateFirstPayment(string $customerId, int $userId): array {
    return mollieRequest('POST', '/payments', [
        'amount'       => ['currency' => 'EUR', 'value' => (string)MOLLIE_MANDATE_AMOUNT],
        'customerId'   => $customerId,
        'sequenceType' => 'first',
        'description'  => MOLLIE_SUBSCRIPTION_DESCRIPTION . ' — activatie',
        'redirectUrl'  => MOLLIE_REDIRECT_URL,
        'webhookUrl'   => MOLLIE_WEBHOOK_URL,
        'metadata'     => ['user_id' => $userId, 'kind' => 'mandate'],
    ]);
}

/**
 * Maak het terugkerende abonnement aan op een customer met een geldig mandaat.
 *
 * @param ?string $startDate YYYY-MM-DD; null = direct starten (geen proef meer).
 */
function mollieCreateSubscription(string $customerId, int $userId, ?string $startDate): array {
    $body = [
        'amount'      => ['currency' => 'EUR', 'value' => (string)MOLLIE_SUBSCRIPTION_AMOUNT],
        'interval'    => MOLLIE_SUBSCRIPTION_INTERVAL,
        // Beschrijving moet uniek zijn per customer-abonnement bij Mollie.
        'description' => MOLLIE_SUBSCRIPTION_DESCRIPTION . ' #' . $userId . '-' . time(),
        'webhookUrl'  => MOLLIE_WEBHOOK_URL,
        'metadata'    => ['user_id' => $userId],
    ];
    if ($startDate !== null) {
        $body['startDate'] = $startDate;
    }
    return mollieRequest('POST', '/customers/' . rawurlencode($customerId) . '/subscriptions', $body);
}

/** Haal een betaling op (gebruikt door de webhook). */
function mollieGetPayment(string $paymentId): array {
    return mollieRequest('GET', '/payments/' . rawurlencode($paymentId));
}

/** Zeg een Mollie-abonnement op. */
function mollieCancelSubscription(string $customerId, string $subscriptionId): array {
    return mollieRequest('DELETE',
        '/customers/' . rawurlencode($customerId) . '/subscriptions/' . rawurlencode($subscriptionId));
}

// ── Facturatie-helpers (lokaal, los van Mollie) ──────────────────────────────

/** Het (enige) abonnement van een gebruiker, of null. */
function getUserSubscription(int $userId): ?array {
    $s = getDB()->prepare('SELECT * FROM subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$userId]);
    $row = $s->fetch();
    return $row ?: null;
}

/**
 * Heeft deze gebruiker recht om band-leider te zijn? Waar bij een lopende
 * proefperiode of een actief (betaald) abonnement.
 */
function userHasActiveBilling(int $userId): bool {
    $sub = getUserSubscription($userId);
    if (!$sub) return false;
    return in_array($sub['status'], ['trialing', 'active'], true);
}

/** Heeft de gebruiker zijn eenmalige gratis proef al verbruikt? */
function userTrialUsed(int $userId): bool {
    $sub = getUserSubscription($userId);
    return $sub ? (int)$sub['trial_used'] === 1 : false;
}

/** user_id's van alle leiders van een band. */
function bandLeaderIds(int $bandId): array {
    $s = getDB()->prepare("SELECT user_id FROM band_members WHERE band_id=? AND role='leader'");
    $s->execute([$bandId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Is de band geblokkeerd? Waar als de band géén leider met actieve facturatie
 * heeft (ook leiderloos = geblokkeerd). Geldt alleen als de betaalfunctie
 * aanstaat — zonder Mollie-config is niets geblokkeerd, zodat bestaande
 * installaties ongewijzigd blijven werken.
 */
function bandIsBlocked(int $bandId): bool {
    if (!billingEnforced()) return false;
    $leaders = bandLeaderIds($bandId);
    if (!$leaders) return true;                   // leiderloos → geblokkeerd
    foreach ($leaders as $lid) {
        if (userHasActiveBilling($lid)) return false;
    }
    return true;
}
