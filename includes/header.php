<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$activeBands = $user ? userBands($user['id']) : [];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'LiveGig') ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <!-- Vendor-bestanden lokaal opgeslagen — app werkt ook zonder internet -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>

<nav id="clicktrack-nav" class="fixed-top">

    <!-- ROW 1: Click track controls -->
    <div class="ct-row-1">
        <span class="brand-name">LiveGig</span>

        <div class="beat-dots">
            <div id="beat_1" class="beat-dot"><span>1</span></div>
            <div id="beat_2" class="beat-dot"><span>2</span></div>
            <div id="beat_3" class="beat-dot"><span>3</span></div>
            <div id="beat_4" class="beat-dot"><span>4</span></div>
        </div>

        <div id="ct-bpm" class="ct-bpm-display">-- BPM</div>

        <div id="ct-song" class="ct-song-display">--</div>

        <button class="btn btn-danger btn-sm px-3 fw-bold" onclick="ctStart()">
            <i class="bi bi-play-fill"></i> START
        </button>
        <button class="btn btn-outline-secondary btn-sm px-3" onclick="ctStop()">
            <i class="bi bi-stop-fill"></i> STOP
        </button>

        <div class="ct-toggles">
            <label class="ct-toggle-label">
                <input type="checkbox" id="ct-automode" checked onchange="ctToggleAuto()">
                <span>Auto</span>
            </label>
            <label class="ct-toggle-label">
                <input type="checkbox" id="ct-soundmode" onchange="ctToggleSound()">
                <span>Sound</span>
            </label>
        </div>

        <?php if ($user && $user['band_id']): ?>
        <div class="dropdown ms-auto">
            <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-music-note-list"></i> <span id="ct-setlist-label">Setlist</span>
            </button>
            <ul id="setlist-dropdown" class="dropdown-menu dropdown-menu-dark">
                <li><a class="dropdown-item text-muted" href="#">Laden...</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- ROW 2: Navigation -->
    <?php if ($user): ?>
    <div class="ct-row-2">
        <div class="ct-nav-links">
            <a href="dashboard.php" class="ct-nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-house-fill"></i> Dashboard
            </a>
            <a href="songs.php" class="ct-nav-link <?= $currentPage === 'songs.php' ? 'active' : '' ?>">
                <i class="bi bi-music-note-beamed"></i> Nummers
            </a>
            <a href="setlists.php" class="ct-nav-link <?= $currentPage === 'setlists.php' ? 'active' : '' ?>">
                <i class="bi bi-list-ol"></i> Setlists
            </a>
            <a href="bands.php" class="ct-nav-link <?= $currentPage === 'bands.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Bands
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="admin.php" class="ct-nav-link ct-nav-admin <?= $currentPage === 'admin.php' ? 'active' : '' ?>">
                <i class="bi bi-shield-fill"></i> Admin
            </a>
            <?php endif; ?>
        </div>

        <div class="ct-nav-right">
            <!-- localStorage-gebruik -->
            <span id="ls-usage" class="ls-usage" title="Lokale cache (localStorage)"></span>

            <!-- Volledig scherm -->
            <button type="button" id="ct-fullscreen-btn" class="btn btn-sm btn-outline-secondary"
                    onclick="toggleFullscreen()" title="Volledig scherm (F)">
                <i class="bi bi-fullscreen" id="ct-fullscreen-icon"></i>
            </button>

            <!-- Band -->
            <?php if (count($activeBands) > 1): ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-people-fill"></i> <?= htmlspecialchars($user['band_name'] ?? 'Band') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    <?php foreach ($activeBands as $b): ?>
                    <li>
                        <a class="dropdown-item <?= $b['id'] == $user['band_id'] ? 'active' : '' ?>"
                           href="api/switch-band.php?band_id=<?= $b['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                            <?= htmlspecialchars($b['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php elseif ($user['band_name']): ?>
            <span class="ct-band-name"><i class="bi bi-people-fill"></i> <?= htmlspecialchars($user['band_name']) ?></span>
            <?php else: ?>
            <span class="ct-band-name text-warning"><i class="bi bi-exclamation-triangle"></i> Geen band</span>
            <?php endif; ?>

            <!-- User -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['username']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    <li><h6 class="dropdown-header"><?= htmlspecialchars($user['username']) ?></h6></li>
                    <li><span class="dropdown-item-text text-muted small">
                        Rol: <?= $user['role'] ?>
                        <?php if ($user['band_name']): ?> · <?= htmlspecialchars($user['band_name']) ?><?php endif; ?>
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear me-2"></i>Profiel &amp; beveiliging</a></li>
                    <li><a class="dropdown-item" href="subscribe.php"><i class="bi bi-stars me-2"></i>Abonnement</a></li>
                    <li><a class="dropdown-item" href="privacy.php"><i class="bi bi-shield-lock me-2"></i>Versleuteling &amp; privacy</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="handleiding.php"><i class="bi bi-book me-2"></i>Handleiding</a></li>
                    <li><a class="dropdown-item" href="principes.php"><i class="bi bi-heart me-2"></i>Onze principes</a></li>
                    <li><a class="dropdown-item" href="voorwaarden.php"><i class="bi bi-file-text me-2"></i>Voorwaarden</a></li>
                    <li><a class="dropdown-item" href="privacybeleid.php"><i class="bi bi-shield-check me-2"></i>Privacybeleid</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="logout.php" class="m-0"
                              onsubmit="if(window.LGKeys)try{LGKeys.lock()}catch(e){}">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <button type="submit" class="dropdown-item">
                                <i class="bi bi-box-arrow-right me-2"></i>Uitloggen
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<!-- Dynamic padding spacer — JS sets height to match nav -->
<div id="nav-spacer"></div>
<script>
(function() {
    function adjustPadding() {
        var nav = document.getElementById('clicktrack-nav');
        var spacer = document.getElementById('nav-spacer');
        if (nav && spacer) {
            var h = nav.offsetHeight;
            spacer.style.height = h + 'px';
            document.documentElement.style.setProperty('--nav-h', h + 'px');
        }
    }
    document.addEventListener('DOMContentLoaded', adjustPadding);
    window.addEventListener('resize', adjustPadding);
    adjustPadding();
})();
</script>

<!-- Volledig scherm: toggle + icoon-sync + sneltoets F -->
<script>
(function() {
    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }
    window.toggleFullscreen = function() {
        var el = document.documentElement;
        if (isFullscreen()) {
            (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        } else {
            (el.requestFullscreen || el.webkitRequestFullscreen).call(el);
        }
    };
    function syncIcon() {
        var icon = document.getElementById('ct-fullscreen-icon');
        var btn  = document.getElementById('ct-fullscreen-btn');
        if (!icon || !btn) return;
        var on = isFullscreen();
        icon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
        btn.title = on ? 'Volledig scherm afsluiten (F)' : 'Volledig scherm (F)';
        btn.classList.toggle('btn-outline-secondary', !on);
        btn.classList.toggle('btn-secondary', on);
    }
    document.addEventListener('fullscreenchange', syncIcon);
    document.addEventListener('webkitfullscreenchange', syncIcon);
    // Sneltoets "F" — behalve tijdens typen in een veld
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'f' && e.key !== 'F') return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
        e.preventDefault();
        window.toggleFullscreen();
    });
})();
</script>

<div id="page-content">
