<?php
/**
 * Voorbeeld config.local.php — kopieer naar includes/config.local.php
 * (gitignored) en vul je echte credentials in.
 *
 * Of: gebruik environment variables (LIVEGIG_SPOTIFY_CLIENT_ID etc.)
 * in plaats van dit bestand.
 */

define('SPOTIFY_CLIENT_ID',     '');  // Vul in
define('SPOTIFY_CLIENT_SECRET', '');  // Vul in
define('GETSONGBPM_API_KEY',    '');  // Optioneel

// Mollie (betalingen/abonnementen) — https://my.mollie.com → Developers → API-keys
// Test-keys beginnen met 'test_', live-keys met 'live_'.
define('MOLLIE_API_KEY',      '');  // Vul in (bv. test_xxxxxxxx)
// Publiek bereikbare URL's — de webhook mag GEEN localhost zijn.
define('MOLLIE_REDIRECT_URL', '');  // bv. https://jouwdomein.nl/live-click/dashboard.php
define('MOLLIE_WEBHOOK_URL',  '');  // bv. https://jouwdomein.nl/live-click/api/mollie_webhook.php
// Paywall afdwingen (bands zonder betalende leider blokkeren)? Laat false tijdens
// testen; true pas bij live-gang nadat bestaande leiders zijn vrijgesteld.
define('MOLLIE_BILLING_ENFORCED', false);

// Fair-use opslaglimiet per gebruiker (som van de bands die hij/zij leidt).
// Standaard 500 MB. Overschrijf met EEN van onderstaande (niet allebei):
//   define('STORAGE_QUOTA_MB', 500);              // in megabytes (handig)
//   define('STORAGE_QUOTA_BYTES', 500*1024*1024); // exacte bytes
// Tip: zet tijdelijk op 1 (MB) om de blokkering te testen.
// define('STORAGE_QUOTA_MB', 500);

// Tarieven (jaarabonnement) beheer je in de admin → Tarieven, niet hier.

// Factuurgegevens (komen op de downloadbare factuur).
// define('INVOICE_COMPANY_NAME',    'Jouw Bedrijf');
// define('INVOICE_COMPANY_ADDRESS', "Straat 1\n1234 AB Plaats");
// define('INVOICE_COMPANY_KVK',     '12345678');
// define('INVOICE_COMPANY_VATID',   'NL001234567B01');
// define('INVOICE_COMPANY_EMAIL',   'facturen@jouwbedrijf.nl');
// define('INVOICE_VAT_PERCENT',     21);   // 0 = geen btw (bv. KOR)

// Token waarmee api/cron.php (dagelijkse tariefverwerking) via HTTP mag draaien.
// define('CRON_TOKEN', 'een-lange-willekeurige-string');
