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
