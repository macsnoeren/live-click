<?php
/**
 * Handleiding — gebruikershandleiding voor LiveGig.
 *
 * Praktische uitleg voor muzikanten over alle onderdelen van de app:
 * dashboard en kliktrack, repertoire, setlists, bands en deellinks,
 * offline werking, profiel/beveiliging en de drumnotatie. Vereist geen
 * login, maar past de topbalk-links aan op de sessiestatus.
 */
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
sessionStart();
$ingelogd = (bool) currentUser();
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handleiding — LiveGig</title>
    <meta name="description" content="Gebruikershandleiding voor LiveGig: dashboard en kliktrack, repertoire, setlists, bands, deellinks, offline werking, beveiliging en drumnotatie.">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        body { background: var(--bg); }

        /* ── Topbalk ─────────────────────────────────────────────── */
        .site-top {
            position: sticky; top: 0; z-index: 1030;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px;
            background: rgba(13,13,13,0.82);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--nav-border);
        }
        .site-top .brand-name { font-size: 1.25rem; cursor: pointer; }
        .site-top .brand-link { text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .site-top .mini-dots { display: flex; gap: 5px; }
        .site-top .mini-dots i { width: 8px; height: 8px; border-radius: 50%; background: #333; display: block; }
        .site-top .mini-dots i.lit { background: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }
        .site-top nav a { color: #999; text-decoration: none; font-size: 0.95rem; margin-left: 18px; transition: color .15s; }
        .site-top nav a:hover { color: #fff; }

        /* ── Hero ────────────────────────────────────────────────── */
        .hl-hero {
            background: radial-gradient(ellipse at 50% 0%, #1a0000 0%, var(--bg) 70%);
            text-align: center;
            padding: 80px 20px 56px;
            border-bottom: 1px solid var(--nav-border);
        }
        .hl-hero .eyebrow {
            text-transform: uppercase; letter-spacing: 3px;
            font-size: 0.78rem; color: var(--accent); font-weight: 700;
        }
        .hl-hero h1 { font-weight: 800; font-size: clamp(2rem, 5vw, 3rem); color: #fff; margin: 14px 0 8px; }
        .hl-hero p { color: #c4c4c4; font-size: 1.1rem; max-width: 620px; margin: 18px auto 0; line-height: 1.6; }

        /* ── Layout ──────────────────────────────────────────────── */
        .hl-layout { max-width: 1040px; margin: 0 auto; padding: 48px 20px 24px; display: flex; gap: 48px; }
        .hl-toc {
            flex: 0 0 220px; align-self: flex-start;
            position: sticky; top: 84px;
        }
        .hl-toc h6 { color: #888; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 1px; margin-bottom: 12px; }
        .hl-toc a { display: block; color: #aaa; text-decoration: none; padding: 5px 0; font-size: 0.94rem; border-left: 2px solid transparent; padding-left: 12px; transition: color .15s, border-color .15s; }
        .hl-toc a:hover { color: #fff; border-left-color: var(--accent); }
        .hl-content { flex: 1 1 auto; min-width: 0; }

        @media (max-width: 800px) {
            .hl-layout { flex-direction: column; gap: 24px; }
            .hl-toc { position: static; flex-basis: auto; }
        }

        /* ── Secties ─────────────────────────────────────────────── */
        .hl-section { margin-bottom: 52px; scroll-margin-top: 80px; }
        .hl-section:last-child { margin-bottom: 0; }
        .hl-section > h2 {
            color: #fff; font-weight: 700; font-size: 1.55rem;
            margin-bottom: 18px; display: flex; align-items: center; gap: 12px;
        }
        .hl-section > h2 i { color: var(--accent); font-size: 1.4rem; }
        .hl-section h3 { color: #ededed; font-weight: 600; font-size: 1.15rem; margin: 28px 0 12px; }
        .hl-section p { color: #c4c4c4; font-size: 1.02rem; line-height: 1.7; }
        .hl-section ul, .hl-section ol { color: #c4c4c4; font-size: 1.02rem; line-height: 1.7; padding-left: 22px; }
        .hl-section li { margin-bottom: 8px; }
        .hl-section strong { color: #ededed; }
        .hl-section code {
            background: #1c1c1c; border: 1px solid var(--card-border);
            border-radius: 5px; padding: 1px 7px; color: #ff9b9b;
            font-size: 0.92em;
        }
        .hl-section a { color: #7fb3ff; text-decoration: none; }
        .hl-section a:hover { text-decoration: underline; }

        /* Stap-voor-stap */
        .hl-steps { counter-reset: step; list-style: none; padding-left: 0; }
        .hl-steps li {
            position: relative; padding-left: 44px; margin-bottom: 16px;
        }
        .hl-steps li::before {
            counter-increment: step; content: counter(step);
            position: absolute; left: 0; top: 0;
            width: 28px; height: 28px; border-radius: 50%;
            background: rgba(204,0,0,0.12); border: 1px solid rgba(204,0,0,0.4);
            color: var(--accent); font-weight: 700; font-size: 0.85rem;
            display: flex; align-items: center; justify-content: center;
        }

        /* Tip / let op */
        .hl-tip, .hl-warn {
            border-radius: 8px; padding: 14px 18px; margin: 22px 0;
            font-size: 0.97rem; line-height: 1.6;
        }
        .hl-tip { background: rgba(60,160,90,0.08); border: 1px solid rgba(60,160,90,0.3); color: #b6dcc0; }
        .hl-tip strong { color: #d7f0de; }
        .hl-warn { background: rgba(204,0,0,0.06); border: 1px solid rgba(204,0,0,0.25); color: #d9b3b3; }
        .hl-warn strong { color: #fff; }

        /* Tabel */
        .hl-table { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin: 22px 0; }
        .hl-table table { margin: 0; }
        .hl-table th, .hl-table td { border-color: var(--card-border); padding: 10px 16px; vertical-align: middle; }
        .hl-table thead th { background: #181818; color: #888; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.5px; }
        .hl-table td code { white-space: nowrap; }

        /* Drumnotatie-staal */
        .drum-key { display: inline-block; width: 16px; height: 16px; border-radius: 3px; vertical-align: -3px; margin-right: 6px; border: 1px solid rgba(255,255,255,0.15); }
        .dk-bar { background: #fff; }
        .dk-rest { background: #cc3030; }
        .dk-crash { background: #d4af37; }
        .dk-break { background: #e08a30; }
        pre.drum-sample {
            background: #141414; border: 1px solid var(--card-border);
            border-radius: 8px; padding: 16px 18px; color: #ddd;
            font-size: 0.92rem; overflow-x: auto; margin: 18px 0;
        }

        /* ── Footer ──────────────────────────────────────────────── */
        .site-footer { text-align: center; padding: 30px 20px; border-top: 1px solid var(--nav-border); color: #666; font-size: 0.85rem; margin-top: 40px; }
        .site-footer a { color: #999; text-decoration: none; margin: 0 10px; }
        .site-footer a:hover { color: #fff; }
    </style>
</head>
<body>

<!-- ── Topbalk ─────────────────────────────────────────────────── -->
<header class="site-top">
    <a href="<?= $ingelogd ? 'dashboard.php' : 'landing.php' ?>" class="brand-link">
        <span class="mini-dots"><i class="lit"></i><i></i><i></i><i class="lit"></i></span>
        <span class="brand-name">LiveGig</span>
    </a>
    <nav>
        <a href="handleiding.php">Handleiding</a>
        <a href="principes.php">Principes</a>
        <a href="voorwaarden.php">Voorwaarden</a>
        <?php if ($ingelogd): ?>
            <a href="dashboard.php">Dashboard</a>
        <?php else: ?>
            <a href="login.php">Inloggen</a>
        <?php endif; ?>
    </nav>
</header>

<!-- ── Hero ────────────────────────────────────────────────────── -->
<section class="hl-hero">
    <div class="eyebrow">Handleiding</div>
    <h1>Zo werkt LiveGig</h1>
    <p>
        Alles wat je nodig hebt om je repertoire te beheren, setlists te bouwen
        en op het podium een kliktrack op het juiste tempo te draaien &mdash; ook
        volledig offline.
    </p>
</section>

<!-- ── Layout ──────────────────────────────────────────────────── -->
<div class="hl-layout">

    <!-- Inhoudsopgave -->
    <aside class="hl-toc">
        <h6>Inhoud</h6>
        <a href="#aan-de-slag">Aan de slag</a>
        <a href="#dashboard">Dashboard &amp; kliktrack</a>
        <a href="#nummers">Nummers (repertoire)</a>
        <a href="#zoeken">Nummers zoeken</a>
        <a href="#drumnotatie">Drumstructuur</a>
        <a href="#setlists">Setlists</a>
        <a href="#bands">Bands &amp; leden</a>
        <a href="#delen">Setlist delen</a>
        <a href="#offline">Offline op het podium</a>
        <a href="#profiel">Profiel &amp; beveiliging</a>
        <a href="#sneltoetsen">Sneltoetsen</a>
    </aside>

    <!-- Inhoud -->
    <main class="hl-content">

        <!-- Aan de slag -->
        <section class="hl-section" id="aan-de-slag">
            <h2><i class="bi bi-rocket-takeoff"></i> Aan de slag</h2>
            <p>
                LiveGig is een webapplicatie voor coverband-muzikanten. Je beheert het
                repertoire van je band, bouwt setlists en draait een gesynthetiseerde
                kliktrack direct op het scherm op het juiste BPM.
            </p>
            <ol class="hl-steps">
                <li><strong>Maak een account aan</strong> of log in. Bij je eerste login vraagt de app je een eigen wachtwoord in te stellen.</li>
                <li><strong>Sluit je aan bij een band</strong> via een uitnodigingslink, of maak een nieuwe band aan onder <em>Bands</em>.</li>
                <li><strong>Vul je repertoire aan</strong> onder <em>Nummers</em> &mdash; handmatig of via de zoekfunctie.</li>
                <li><strong>Bouw een setlist</strong> onder <em>Setlists</em> en speel hem af vanaf het dashboard.</li>
            </ol>
            <div class="hl-tip">
                <strong>Tip:</strong> bovenin het scherm vind je altijd de navigatiebalk met
                de kliktrack-knoppen (boven) en de menu-items (onder). Je gebruikersmenu zit
                rechtsboven achter je naam.
            </div>
        </section>

        <!-- Dashboard -->
        <section class="hl-section" id="dashboard">
            <h2><i class="bi bi-house-fill"></i> Dashboard &amp; kliktrack</h2>
            <p>
                Het dashboard is je werkscherm tijdens repetities en optredens. Links staat
                de geselecteerde setlist, rechts je volledige repertoire.
            </p>
            <h3>Een nummer afspelen</h3>
            <p>
                Klik op een nummer en de kliktrack start direct op het BPM van dat nummer.
                De vier stippen bovenin lichten op de maat op: slag&nbsp;1 is het accent
                (hogere toon), de slagen 2&ndash;4 klinken iets lager.
            </p>
            <ul>
                <li><strong>START / STOP</strong> &mdash; start of stop de kliktrack handmatig.</li>
                <li><strong>Auto</strong> &mdash; staat dit aan, dan stopt de klik automatisch na het ingestelde aantal seconden.</li>
                <li><strong>Sound</strong> &mdash; schakelt het klikgeluid in of uit (handig om alleen visueel mee te tellen).</li>
                <li><strong>BPM-weergave</strong> &mdash; toont het tempo van het actieve nummer.</li>
            </ul>
            <p>
                Onder elk nummer kun je de <strong>drumstructuur</strong> in- of uitklappen:
                een visueel diagram van de secties (intro, couplet, refrein&hellip;).
            </p>
            <div class="hl-tip">
                <strong>Tip:</strong> het klikgeluid wordt realtime opgewekt door je browser
                (Web Audio API). Er wordt geen audiobestand gedownload, dus de klik werkt ook
                zonder internet.
            </div>
        </section>

        <!-- Nummers -->
        <section class="hl-section" id="nummers">
            <h2><i class="bi bi-music-note-beamed"></i> Nummers (repertoire)</h2>
            <p>
                Onder <em>Nummers</em> beheer je het repertoire van de actieve band. Per
                nummer leg je vast:
            </p>
            <div class="hl-table">
                <table class="table table-dark mb-0">
                    <thead><tr><th>Veld</th><th>Uitleg</th></tr></thead>
                    <tbody>
                        <tr><td>Titel &amp; artiest</td><td>De naam van het nummer en de oorspronkelijke uitvoerder.</td></tr>
                        <tr><td>BPM</td><td>Het tempo. Hierop draait de kliktrack.</td></tr>
                        <tr><td>Toonsoort</td><td>In Camelot-notatie (bijv. <code>8A</code>), handig voor mixen en transponeren.</td></tr>
                        <tr><td>Duur</td><td>Speelduur, gebruikt voor de geschatte totaalduur van een setlist.</td></tr>
                        <tr><td>Begint</td><td>Wie het nummer inzet (bijv. drums, zang, gitaar).</td></tr>
                        <tr><td>Notities</td><td>Vrije tekst: afspraken, aandachtspunten, eindes.</td></tr>
                        <tr><td>Drumstructuur</td><td>De sectie-opbouw in tekstnotatie (zie hieronder).</td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                Een nummer toevoegen doe je met de knop bovenaan; bewerken of verwijderen via
                de knoppen bij elk nummer.
            </p>
        </section>

        <!-- Zoeken -->
        <section class="hl-section" id="zoeken">
            <h2><i class="bi bi-search"></i> Nummers zoeken</h2>
            <p>
                Weet je het BPM of de toonsoort niet? Gebruik de zoekfunctie bij het toevoegen
                van een nummer. LiveGig zoekt automatisch via meerdere bronnen na elkaar
                (Tunebat, GetSongBPM, Spotify en MusicBrainz) en vult de gevonden gegevens
                voor je in: BPM, toonsoort, energie, dansbaarheid en &mdash; waar beschikbaar
                &mdash; een korte Spotify-preview.
            </p>
            <div class="hl-tip">
                <strong>Tip:</strong> de gevonden waarden zijn een startpunt. Controleer het BPM
                altijd even &mdash; bronnen verschillen soms een halve of dubbele tel.
            </div>
        </section>

        <!-- Drumnotatie -->
        <section class="hl-section" id="drumnotatie">
            <h2><i class="bi bi-grid-3x3-gap"></i> Drumstructuur</h2>
            <p>
                De drumstructuur beschrijf je in een eenvoudige tekstnotatie. Elke regel is
                één sectie. De app zet dit automatisch om naar een gekleurd diagram dat je op
                het dashboard kunt uit- en inklappen.
            </p>
            <h3>Opbouw</h3>
            <p>Schrijf per regel een label, een dubbele punt en daarna de maatsymbolen:</p>
            <pre class="drum-sample">Intro:   ^ | | |   | | | |
Couplet: | | | |   | | | |   | | | |   | | | |
Refrein: ^ | | |   ^ | | |   ^ | | *   | - - -  rustig uitlopen
Solo:    ^ | | |   | | | |
Outro:   | | | |   | | | -   ^ stop</pre>
            <div class="hl-table">
                <table class="table table-dark mb-0">
                    <thead><tr><th>Symbool</th><th>Betekenis</th><th>Kleur</th></tr></thead>
                    <tbody>
                        <tr><td><code>|</code></td><td>Normale maat</td><td><span class="drum-key dk-bar"></span>Wit</td></tr>
                        <tr><td><code>-</code></td><td>Rust / stil</td><td><span class="drum-key dk-rest"></span>Rood</td></tr>
                        <tr><td><code>^</code></td><td>Crash / cymbal</td><td><span class="drum-key dk-crash"></span>Goud</td></tr>
                        <tr><td><code>*</code></td><td>Break / brake</td><td><span class="drum-key dk-break"></span>Oranje</td></tr>
                    </tbody>
                </table>
            </div>
            <ul>
                <li><strong>Twee of meer spaties</strong> tussen groepen maken een zichtbare frasegrens in het diagram.</li>
                <li><strong>Tekst na het laatste symbool</strong> op een regel wordt als commentaar getoond (grijs, cursief) &mdash; bijvoorbeeld <em>&ldquo;rustig uitlopen&rdquo;</em>.</li>
            </ul>
            <div class="hl-tip">
                <strong>Tip:</strong> bij het bewerken zie je direct een live-preview van het
                diagram, zodat je je notatie kunt bijschaven voordat je opslaat.
            </div>
        </section>

        <!-- Setlists -->
        <section class="hl-section" id="setlists">
            <h2><i class="bi bi-list-ol"></i> Setlists</h2>
            <p>
                Onder <em>Setlists</em> stel je de speelvolgorde voor een optreden samen.
            </p>
            <ol class="hl-steps">
                <li>Maak een nieuwe setlist aan en geef hem een naam (bijv. <em>&ldquo;Bruiloft set 1&rdquo;</em>).</li>
                <li>Sleep nummers uit je repertoire in de gewenste volgorde (drag-and-drop).</li>
                <li>De <strong>geschatte totaalduur</strong> wordt automatisch berekend uit de duur van de nummers.</li>
            </ol>
            <p>
                Op het dashboard kies je rechtsboven welke setlist je voor de gig wilt zien.
                Die staat dan links klaar om af te spelen.
            </p>
        </section>

        <!-- Bands -->
        <section class="hl-section" id="bands">
            <h2><i class="bi bi-people-fill"></i> Bands &amp; leden</h2>
            <p>
                Je kunt lid zijn van meerdere bands. Elke band heeft een eigen repertoire en
                eigen setlists. Heb je meer dan één band, dan wissel je rechtsboven van
                actieve band.
            </p>
            <h3>Leden uitnodigen</h3>
            <p>
                Bandleiders maken onder <em>Bands</em> een <strong>uitnodigingslink</strong>
                aan. Deel die link met een nieuw lid; via de link sluit diegene zich bij de
                band aan. Bandleiders beheren het lidmaatschap en kunnen rollen toekennen.
            </p>
        </section>

        <!-- Delen -->
        <section class="hl-section" id="delen">
            <h2><i class="bi bi-share-fill"></i> Setlist delen</h2>
            <p>
                Wil je een setlist aan gasten laten zien zonder dat ze hoeven in te loggen?
                Maak onder <em>Bands</em> een <strong>publieke deellink</strong> aan. Iedereen
                met die link kan de setlist alleen-lezen bekijken en eventueel printen of als
                PDF opslaan via de printfunctie van de browser.
            </p>
            <div class="hl-warn">
                <strong>Let op:</strong> iedereen met de link kan meekijken. Wil je de toegang
                weer intrekken, verwijder dan de deellink onder <em>Bands &rarr; Setlijst deellink</em>.
                Er wordt dan een nieuwe link aangemaakt zodra je opnieuw deelt.
            </div>
        </section>

        <!-- Offline -->
        <section class="hl-section" id="offline">
            <h2><i class="bi bi-wifi-off"></i> Offline op het podium</h2>
            <p>
                Geen internet op het podium? Geen probleem. Na de eerste keer laden werkt het
                dashboard volledig offline:
            </p>
            <ul>
                <li><strong>Nummers, setlists en drumdiagrammen</strong> worden lokaal in je browser bewaard en bij elk bezoek ververst.</li>
                <li><strong>Het klikgeluid</strong> wordt door je browser zelf opgewekt &mdash; geen download nodig.</li>
                <li><strong>De app zelf</strong> (knoppen, opmaak) staat lokaal opgeslagen.</li>
            </ul>
            <div class="hl-warn">
                <strong>Let op:</strong> als de server niet bereikbaar is, verschijnt een rode
                balk boven de setlist met de datum van de laatste synchronisatie. Laad daarom
                het dashboard altijd één keer mét internet vóór een optreden, zodat je de meest
                recente gegevens lokaal hebt staan.
            </div>
        </section>

        <!-- Profiel -->
        <section class="hl-section" id="profiel">
            <h2><i class="bi bi-person-gear"></i> Profiel &amp; beveiliging</h2>
            <p>
                Via je naam rechtsboven open je <em>Profiel &amp; beveiliging</em>. Daar kun je:
            </p>
            <ul>
                <li><strong>Je wachtwoord wijzigen.</strong></li>
                <li><strong>Tweefactorauthenticatie (2FA) inschakelen</strong> met een authenticator-app (Google Authenticator, Authy e.d.). Je scant een QR-code en voert daarna bij elke login een 6-cijferige code in.</li>
            </ul>
            <p>
                In hetzelfde menu vind je <a href="privacy.php">Versleuteling &amp; privacy</a>
                (hoe je gegevens worden beschermd), <a href="principes.php">Onze principes</a>
                en de <a href="voorwaarden.php">Voorwaarden</a>.
            </p>
            <div class="hl-tip">
                <strong>Tip:</strong> zet 2FA aan voor extra bescherming, zeker als je band
                gevoelige afspraken of klantgegevens in notities bewaart.
            </div>
        </section>

        <!-- Sneltoetsen -->
        <section class="hl-section" id="sneltoetsen">
            <h2><i class="bi bi-keyboard"></i> Sneltoetsen</h2>
            <div class="hl-table">
                <table class="table table-dark mb-0">
                    <thead><tr><th>Toets</th><th>Actie</th></tr></thead>
                    <tbody>
                        <tr><td><code>F</code></td><td>Volledig scherm aan/uit (handig op een tablet op het podium).</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted" style="font-size:0.92rem;">
                De sneltoets werkt niet terwijl je in een tekstveld typt.
            </p>
        </section>

    </main>
</div>

<footer class="site-footer">
    <div class="mb-2">
        <a href="<?= $ingelogd ? 'dashboard.php' : 'landing.php' ?>">Home</a>
        <a href="handleiding.php">Handleiding</a>
        <a href="principes.php">Principes</a>
        <a href="voorwaarden.php">Voorwaarden</a>
        <a href="privacy.php">Privacy</a>
    </div>
    LiveGig &mdash; gebouwd voor muzikanten.
</footer>

</body>
</html>
