<?php
/**
 * Bootstrap: definieer APP_ROOT zodat includes/ buiten de webroot blijft.
 *
 * Mappenstructuur op de server:
 *   /projects/app.blastcoverband.nl/          ← APP_ROOT  (= dirname(__DIR__, 2))
 *   /projects/app.blastcoverband.nl/includes/ ← PHP-includes (buiten webroot)
 *   /projects/app.blastcoverband.nl/htdocs/live-click/  ← webroot (__DIR__)
 */
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

/**
 * APP_DEPTH: hoe diep bevindt het huidige script zich onder de webroot?
 *   0 = direct in htdocs/live-click/  (login.php, join.php, dashboard.php …)
 *   1 = in htdocs/live-click/api/     (api/songs.php, api/setlists.php …)
 *
 * Wordt gebruikt door appRelPath() in includes/auth.php om correcte
 * URL-relatieve paden te genereren voor redirects.
 */
if (!defined('APP_DEPTH')) {
    $_wd  = realpath(__DIR__);                              // webroot op filesystem
    $_sd  = realpath(dirname($_SERVER['SCRIPT_FILENAME'])); // map van huidig script
    $_dep = 0;
    $_cur = $_sd;
    while ($_cur && $_cur !== $_wd && strlen($_cur) > strlen($_wd)) {
        $_dep++;
        $_cur = realpath(dirname($_cur));
        if ($_cur === false) break;
    }
    define('APP_DEPTH', $_dep);
    unset($_wd, $_sd, $_dep, $_cur);
}
