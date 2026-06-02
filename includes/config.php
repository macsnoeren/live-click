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

// Token cache: Spotify access token op disk (verlopen na 1 uur)
if (!defined('SPOTIFY_TOKEN_CACHE_FILE')) {
    define('SPOTIFY_TOKEN_CACHE_FILE', __DIR__ . '/../data/.spotify_token');
}
