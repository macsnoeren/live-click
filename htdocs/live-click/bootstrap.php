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
