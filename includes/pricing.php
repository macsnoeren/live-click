<?php
/**
 * Tarieven (abonnementsprijzen) met ingangsdatum.
 *
 * Een tariefversie bestaat uit componenten:
 *   base_amount  — netto basisbedrag voor de exploitant (bv. €5)
 *   mollie_fee   — vaste Mollie-transactiekosten die je doorberekent
 *   vat_percent  — btw-percentage
 *   interval     — Mollie-interval, bv. '12 months' (jaarabonnement)
 *   effective_from — vanaf welke datum dit tarief geldt
 *
 * Totaal dat de klant betaalt:
 *   round((base_amount + mollie_fee) * (1 + vat_percent/100), 2)
 *
 * Het 'huidige' tarief is de versie met de hoogste effective_from <= vandaag.
 * Versies met een toekomstige datum staan gepland (en worden door api/cron.php
 * op de ingangsdatum toegepast op lopende abonnementen).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

/** Totaalbedrag (incl. btw) van een tariefrij. */
function pricingTotal(array $p): float {
    $base = (float)($p['base_amount'] ?? 0);
    $fee  = (float)($p['mollie_fee']  ?? 0);
    $vat  = (float)($p['vat_percent'] ?? 0);
    return round(($base + $fee) * (1 + $vat / 100), 2);
}

/** Netto (excl. btw) = basis + Mollie-kosten. */
function pricingNet(array $p): float {
    return round((float)($p['base_amount'] ?? 0) + (float)($p['mollie_fee'] ?? 0), 2);
}

/** Btw-bedrag van een tariefrij. */
function pricingVatAmount(array $p): float {
    return round(pricingTotal($p) - pricingNet($p), 2);
}

/**
 * Het tarief dat op een bepaalde datum geldt (default: vandaag), of een fallback
 * uit de config als er nog geen tarief is geconfigureerd.
 */
function pricingAtDate(?string $date = null): array {
    $date = $date ?: date('Y-m-d');
    $s = getDB()->prepare(
        "SELECT * FROM pricing WHERE effective_from <= ? ORDER BY effective_from DESC, id DESC LIMIT 1"
    );
    $s->execute([$date]);
    $r = $s->fetch();
    if ($r) return $r;

    // Fallback: geen tarief in de database → config-constanten.
    return [
        'id'             => 0,
        'interval'       => defined('MOLLIE_SUBSCRIPTION_INTERVAL') ? MOLLIE_SUBSCRIPTION_INTERVAL : '12 months',
        'base_amount'    => defined('MOLLIE_SUBSCRIPTION_AMOUNT') ? (float)MOLLIE_SUBSCRIPTION_AMOUNT : 5.00,
        'mollie_fee'     => 0.0,
        'vat_percent'    => defined('INVOICE_VAT_PERCENT') ? (float)INVOICE_VAT_PERCENT : 21.0,
        'effective_from' => $date,
    ];
}

/** Het op dit moment geldende tarief. */
function currentPricing(): array {
    return pricingAtDate(date('Y-m-d'));
}

/** Alle tariefversies (nieuwste eerst) — voor het admin-overzicht. */
function allPricing(): array {
    return getDB()->query("SELECT * FROM pricing ORDER BY effective_from DESC, id DESC")->fetchAll();
}
