<?php
/**
 * Centrale security-headers — geinclude vanuit bootstrap.php.
 * Idempotent: kan meermaals geinclude worden.
 */
if (!defined('LG_SECURITY_HEADERS_SENT')) {
    define('LG_SECURITY_HEADERS_SENT', true);

    // Sniff-bescherming + framing
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');

    // HSTS alleen onder HTTPS — anders heeft het geen zin en veroorzaakt
    // het verwarring bij lokale dev. 1 jaar; voeg "preload" toe na verificatie.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // CSP. 'unsafe-inline' is bewust toegestaan voor scripts én styles omdat
    // de pagina's inline <script>/onclick=… en inline style="…" gebruiken.
    // Verfijnen (nonce-based) als alle inline naar externe files is gemigreerd.
    $csp = "default-src 'self'; "
         . "img-src 'self' data:; "
         . "script-src 'self' 'unsafe-inline'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "font-src 'self' data:; "
         . "connect-src 'self'; "
         . "frame-ancestors 'none'; "
         . "base-uri 'self'; "
         . "form-action 'self'";
    header('Content-Security-Policy: ' . $csp);
}
