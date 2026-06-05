<?php
/**
 * LiveGig configuration
 *
 * SECRETS LADEN — in volgorde van prioriteit:
 *   1. Omgevingsvariabelen (LIVEGIG_SPOTIFY_CLIENT_ID, …)
 *   2. includes/config.local.php   ← gitignored, voor on-server overrides
 *   3. lege defaults (search.php valt dan terug op Tunebat/MusicBrainz)
 *
 * Spotify Developer credentials:
 * 1. Ga naar https://developer.spotify.com/dashboard
 * 2. Maak een app aan ("LiveGig")
 * 3. Zet de Client ID/Secret in includes/config.local.php OF in env vars
 *
 * Let op: het audio-features endpoint (BPM) is gedepreceerd voor apps
 * aangemaakt na 27 november 2024.
 */

// Stap 1 — laad lokale overrides als die bestaan
$_local = __DIR__ . '/config.local.php';
if (is_file($_local)) {
    require $_local;
}
unset($_local);

// Stap 2 — env vars hebben voorrang; anders constanten uit config.local.php; anders leeg
if (!defined('SPOTIFY_CLIENT_ID')) {
    define('SPOTIFY_CLIENT_ID', getenv('LIVEGIG_SPOTIFY_CLIENT_ID') ?: '');
}
if (!defined('SPOTIFY_CLIENT_SECRET')) {
    define('SPOTIFY_CLIENT_SECRET', getenv('LIVEGIG_SPOTIFY_CLIENT_SECRET') ?: '');
}
if (!defined('GETSONGBPM_API_KEY')) {
    define('GETSONGBPM_API_KEY', getenv('LIVEGIG_GETSONGBPM_API_KEY') ?: '');
}

// Mollie (betalingen/abonnementen). Leeg = betaalfunctie staat uit.
if (!defined('MOLLIE_API_KEY')) {
    define('MOLLIE_API_KEY', getenv('LIVEGIG_MOLLIE_API_KEY') ?: '');
}
if (!defined('MOLLIE_REDIRECT_URL')) {
    define('MOLLIE_REDIRECT_URL', getenv('LIVEGIG_MOLLIE_REDIRECT_URL') ?: '');
}
if (!defined('MOLLIE_WEBHOOK_URL')) {
    define('MOLLIE_WEBHOOK_URL', getenv('LIVEGIG_MOLLIE_WEBHOOK_URL') ?: '');
}
// Abonnementsvoorwaarden. Bedragen als string met punt-decimaal (Mollie-formaat).
if (!defined('MOLLIE_SUBSCRIPTION_AMOUNT')) {
    define('MOLLIE_SUBSCRIPTION_AMOUNT', getenv('LIVEGIG_MOLLIE_SUBSCRIPTION_AMOUNT') ?: '2.50');
}
if (!defined('MOLLIE_SUBSCRIPTION_INTERVAL')) {
    define('MOLLIE_SUBSCRIPTION_INTERVAL', getenv('LIVEGIG_MOLLIE_SUBSCRIPTION_INTERVAL') ?: '1 month');
}
if (!defined('MOLLIE_SUBSCRIPTION_DESCRIPTION')) {
    define('MOLLIE_SUBSCRIPTION_DESCRIPTION', getenv('LIVEGIG_MOLLIE_SUBSCRIPTION_DESCRIPTION') ?: 'LiveGig abonnement');
}
// Gratis proefperiode (dagen) voordat de eerste echte incasso valt.
if (!defined('MOLLIE_TRIAL_DAYS')) {
    define('MOLLIE_TRIAL_DAYS', (int)(getenv('LIVEGIG_MOLLIE_TRIAL_DAYS') ?: 30));
}
// ── Facturatie-gegevens (voor de downloadbare factuur) ──────────────────────
// Vul je eigen bedrijfsgegevens in via env-vars of config.local.php.
if (!defined('INVOICE_COMPANY_NAME')) {
    define('INVOICE_COMPANY_NAME', getenv('LIVEGIG_INVOICE_COMPANY_NAME') ?: 'LiveGig');
}
if (!defined('INVOICE_COMPANY_ADDRESS')) {
    // Meerdere regels mogen met "\n" gescheiden worden.
    define('INVOICE_COMPANY_ADDRESS', getenv('LIVEGIG_INVOICE_COMPANY_ADDRESS') ?: '');
}
if (!defined('INVOICE_COMPANY_KVK')) {
    define('INVOICE_COMPANY_KVK', getenv('LIVEGIG_INVOICE_COMPANY_KVK') ?: '');
}
if (!defined('INVOICE_COMPANY_VATID')) {
    define('INVOICE_COMPANY_VATID', getenv('LIVEGIG_INVOICE_COMPANY_VATID') ?: '');
}
if (!defined('INVOICE_COMPANY_EMAIL')) {
    define('INVOICE_COMPANY_EMAIL', getenv('LIVEGIG_INVOICE_COMPANY_EMAIL') ?: '');
}
// BTW-percentage waarmee het betaalde (inclusief) bedrag wordt uitgesplitst.
// Zet op 0 als je geen btw in rekening brengt (bv. KOR-regeling).
if (!defined('INVOICE_VAT_PERCENT')) {
    define('INVOICE_VAT_PERCENT', (float)(getenv('LIVEGIG_INVOICE_VAT_PERCENT') ?: 21));
}

// Bedrag van de eenmalige "first payment" die alleen het incassomandaat vestigt.
// iDEAL staat geen €0,00 toe; een minimaal bedrag volstaat om de machtiging te krijgen.
if (!defined('MOLLIE_MANDATE_AMOUNT')) {
    define('MOLLIE_MANDATE_AMOUNT', getenv('LIVEGIG_MOLLIE_MANDATE_AMOUNT') ?: '0.01');
}
// Wordt de paywall AFGEDWONGEN? Losgekoppeld van de API-key, zodat je een
// (test)key kunt instellen en de betaalflow kunt testen ZONDER dat bestaande
// bands meteen geblokkeerd raken. Zet dit pas op '1' als je echt live gaat
// (en je bestaande leiders hebt vrijgesteld — zie admin Betalingen).
if (!defined('MOLLIE_BILLING_ENFORCED')) {
    define('MOLLIE_BILLING_ENFORCED', (getenv('LIVEGIG_MOLLIE_BILLING_ENFORCED') ?: '0') === '1');
}

// Token cache: Spotify access token op disk (verlopen na 1 uur)
if (!defined('SPOTIFY_TOKEN_CACHE_FILE')) {
    define('SPOTIFY_TOKEN_CACHE_FILE', __DIR__ . '/../data/.spotify_token');
}
