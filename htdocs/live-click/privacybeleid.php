<?php
/**
 * Privacybeleid — publiek, geen login vereist.
 *
 * Het formele AVG/GDPR-privacybeleid: welke persoonsgegevens worden verwerkt,
 * met welk doel en grondslag, bewaartermijnen en rechten van betrokkenen.
 * De technische uitleg over end-to-end-versleuteling staat op privacy.php;
 * de gebruiksvoorwaarden op voorwaarden.php.
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
    <title>Privacybeleid — LiveGig</title>
    <meta name="robots" content="all">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        body { background: var(--bg); }
        .site-top {
            position: sticky; top: 0; z-index: 1030;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px;
            background: rgba(13,13,13,0.82); backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--nav-border);
        }
        .site-top .brand-link { text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .site-top .brand-name { font-size: 1.25rem; }
        .site-top .mini-dots { display: flex; gap: 5px; }
        .site-top .mini-dots i { width: 8px; height: 8px; border-radius: 50%; background: #333; display: block; }
        .site-top .mini-dots i.lit { background: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }
        .site-top nav a { color: #999; text-decoration: none; font-size: 0.95rem; margin-left: 18px; transition: color .15s; }
        .site-top nav a:hover { color: #fff; }

        .terms-head { text-align: center; padding: 64px 20px 36px; border-bottom: 1px solid var(--nav-border); }
        .terms-head .eyebrow { text-transform: uppercase; letter-spacing: 3px; font-size: 0.78rem; color: var(--accent); font-weight: 700; }
        .terms-head h1 { color: #fff; font-weight: 800; font-size: clamp(1.8rem, 4.5vw, 2.6rem); margin: 12px 0 6px; }
        .terms-head .updated { color: #777; font-size: 0.9rem; }

        .terms-wrap { max-width: 740px; margin: 0 auto; padding: 48px 20px 72px; }
        .terms-wrap h2 { color: #fff; font-weight: 700; font-size: 1.4rem; margin: 44px 0 14px; padding-top: 8px; }
        .terms-wrap h2:first-child { margin-top: 0; }
        .terms-wrap h2 .n { color: var(--accent); margin-right: 8px; }
        .terms-wrap p, .terms-wrap li { color: #c2c2c2; line-height: 1.75; font-size: 1.02rem; }
        .terms-wrap ul { padding-left: 1.3rem; }
        .terms-wrap li { margin-bottom: 4px; }
        .terms-wrap blockquote {
            border-left: 3px solid var(--accent); margin: 22px 0; padding: 4px 0 4px 20px;
            color: #fff; font-size: 1.15rem; font-weight: 300; line-height: 1.55;
        }
        .terms-wrap .lead-intro { color: #d4d4d4; font-size: 1.08rem; }
        .terms-wrap hr { border-color: var(--card-border); margin: 8px 0; }
        .terms-table { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin: 20px 0; }
        .terms-table table { margin: 0; }
        .terms-table th, .terms-table td { border-color: var(--card-border); padding: 10px 16px; vertical-align: top; }
        .terms-table thead th { background: #181818; color: #888; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.5px; }
        .terms-callout { background: rgba(204,0,0,0.06); border: 1px solid rgba(204,0,0,0.2); border-radius: 8px; padding: 14px 18px; color: #d9b3b3; }
        .terms-callout strong { color: #fff; }

        .site-footer { text-align: center; padding: 34px 20px; border-top: 1px solid var(--nav-border); color: #666; font-size: 0.85rem; }
        .site-footer a { color: #999; text-decoration: none; margin: 0 10px; }
        .site-footer a:hover { color: #fff; }
    </style>
</head>
<body>

<header class="site-top">
    <a href="<?= $ingelogd ? 'dashboard.php' : 'index.php' ?>" class="brand-link">
        <span class="mini-dots"><i class="lit"></i><i></i><i></i><i class="lit"></i></span>
        <span class="brand-name">LiveGig</span>
    </a>
    <nav>
        <a href="handleiding.php">Handleiding</a>
        <a href="principes.php">Principes</a>
        <a href="voorwaarden.php">Voorwaarden</a>
        <?php if ($ingelogd): ?><a href="dashboard.php">Dashboard</a><?php else: ?><a href="login.php">Inloggen</a><?php endif; ?>
    </nav>
</header>

<div class="terms-head">
    <div class="eyebrow">AVG / GDPR</div>
    <h1>Privacybeleid</h1>
    <div class="updated">Laatst bijgewerkt: 2 juni 2026</div>
</div>

<div class="terms-wrap">

    <p class="lead-intro">
        Dit privacybeleid beschrijft welke persoonsgegevens LiveGig verwerkt, met welk doel,
        op welke grondslag, hoe lang ze worden bewaard en welke rechten je hebt. Het is opgesteld
        conform de Algemene verordening gegevensbescherming (AVG/GDPR).
    </p>
    <p>
        Dit gaat over de <strong>juridische</strong> verwerking van persoonsgegevens. Hoe de
        end-to-end-versleuteling technisch werkt, lees je op de pagina
        <a href="privacy.php" class="link-light">Versleuteling &amp; privacy</a>. De
        gebruiksvoorwaarden staan op <a href="voorwaarden.php" class="link-light">Voorwaarden</a>.
    </p>

    <h2><span class="n">1.</span> Verwerkingsverantwoordelijke</h2>
    <p>
        LiveGig is een zelf-gehoste applicatie. De verwerkingsverantwoordelijke is de beheerder
        van de installatie waarop jij een account hebt. Voor de installatie op
        <strong>app.blastcoverband.nl</strong> is dat te bereiken via
        <a href="mailto:macsnoeren@gmail.com" class="link-light">macsnoeren@gmail.com</a>.
    </p>
    <p>
        Heb je een account op een andere installatie, dan is de beheerder daarvan verantwoordelijk.
        Dit beleid beschrijft de standaardverwerking van de software.
    </p>

    <h2><span class="n">2.</span> Welke persoonsgegevens we verwerken</h2>
    <p><strong>Accountgegevens</strong></p>
    <div class="terms-table">
        <table class="table table-dark mb-0">
            <thead><tr><th>Gegeven</th><th>Toelichting</th></tr></thead>
            <tbody>
                <tr><td>Gebruikersnaam</td><td>Door jou gekozen; om in te loggen en je te tonen aan bandleden.</td></tr>
                <tr><td>E-mailadres</td><td>Voor identificatie en accountbeheer.</td></tr>
                <tr><td>Wachtwoord</td><td>Nooit als platte tekst opgeslagen &mdash; alleen als bcrypt-hash.</td></tr>
                <tr><td>Rol</td><td><code>user</code> of <code>admin</code>.</td></tr>
                <tr><td>Bandlidmaatschap</td><td>In welke bands je zit en met welke rol (lid / leider).</td></tr>
            </tbody>
        </table>
    </div>

    <p><strong>Beveiligingsgegevens</strong></p>
    <div class="terms-table">
        <table class="table table-dark mb-0">
            <thead><tr><th>Gegeven</th><th>Toelichting</th></tr></thead>
            <tbody>
                <tr><td>2FA-secret (TOTP)</td><td>Alleen als je tweefactorauthenticatie inschakelt. Verlaat de server niet.</td></tr>
                <tr><td>2FA-back-upcodes</td><td>Als SHA-256-hash opgeslagen, eenmalig getoond bij activering.</td></tr>
                <tr><td>&ldquo;Onthoud mij&rdquo;-tokens</td><td>Alleen de hash staat in de database; de platte token staat in je cookie.</td></tr>
                <tr><td>IP-adres</td><td>Vastgelegd in het audit-logboek bij gevoelige acties en gebruikt voor rate-limiting tegen brute-force-aanvallen.</td></tr>
                <tr><td>Inlogpogingen</td><td>Tijdelijke tellers per account en per IP om misbruik te beperken.</td></tr>
            </tbody>
        </table>
    </div>

    <p><strong>Inhoudsgegevens (door jou ingevoerd)</strong></p>
    <p>
        Repertoire (nummers, artiest, BPM, toonsoort, notities, drumstructuur), setlijsten en
        banden. Dit zijn doorgaans geen persoonsgegevens, maar notities kunnen die bevatten.
        Wanneer de <strong>kluis (end-to-end-versleuteling)</strong> voor een band is ingeschakeld,
        wordt deze inhoud versleuteld opgeslagen en is die voor de server en de beheerder
        onleesbaar.
    </p>

    <p><strong>Wat we niet doen</strong></p>
    <ul>
        <li>Geen trackingcookies, geen advertenties, geen analytics van derden.</li>
        <li>Geen profilering of geautomatiseerde besluitvorming.</li>
        <li>Geen verkoop of verhuur van gegevens aan derden.</li>
    </ul>

    <h2><span class="n">3.</span> Doeleinden en grondslagen</h2>
    <div class="terms-table">
        <table class="table table-dark mb-0">
            <thead><tr><th>Doel</th><th>Grondslag (AVG art. 6)</th></tr></thead>
            <tbody>
                <tr><td>Account aanmaken en de dienst leveren</td><td>Uitvoering van de overeenkomst (art. 6.1.b)</td></tr>
                <tr><td>Beveiliging: 2FA, rate-limiting, audit-logboek, sessiebeheer</td><td>Gerechtvaardigd belang (art. 6.1.f)</td></tr>
                <tr><td>Tweefactorauthenticatie inschakelen</td><td>Toestemming (art. 6.1.a) &mdash; optioneel</td></tr>
                <tr><td>Wettelijke verplichtingen (indien van toepassing)</td><td>Wettelijke plicht (art. 6.1.c)</td></tr>
            </tbody>
        </table>
    </div>

    <h2><span class="n">4.</span> Cookies en lokale opslag</h2>
    <p>LiveGig gebruikt alleen <strong>functionele</strong> cookies en lokale opslag:</p>
    <div class="terms-table">
        <table class="table table-dark mb-0">
            <thead><tr><th>Item</th><th>Doel</th></tr></thead>
            <tbody>
                <tr><td>Sessiecookie (<code>HttpOnly</code>, <code>SameSite=Lax</code>, <code>Secure</code> onder HTTPS)</td><td>Je ingelogde sessie onthouden.</td></tr>
                <tr><td>&ldquo;Onthoud mij&rdquo;-cookie</td><td>Ingelogd blijven tussen bezoeken (optioneel).</td></tr>
                <tr><td><code>localStorage</code></td><td>Nummers, setlijsten en drumstructuren cachen zodat het dashboard offline werkt &mdash; bijvoorbeeld op het podium zonder internet.</td></tr>
            </tbody>
        </table>
    </div>
    <p>
        Er worden geen cookies geplaatst voor tracking of marketing. Hiervoor is geen
        cookietoestemmingsbanner vereist.
    </p>

    <h2><span class="n">5.</span> Ontvangers en externe diensten</h2>
    <p>
        De gegevens staan in een database op de server van de beheerder. Een eventuele
        hostingpartij verwerkt gegevens uitsluitend in opdracht van de
        verwerkingsverantwoordelijke (verwerker).
    </p>
    <p>
        Bij het <strong>opzoeken van een nummer</strong> stuurt de applicatie alleen de zoekterm
        (titel/artiest) naar externe muziekdiensten &mdash; <strong>nooit</strong> je
        persoonsgegevens: Tunebat, GetSongBPM, Spotify en MusicBrainz. Spotify is gevestigd in de
        VS; er worden alleen muziek-zoekopdrachten gedeeld, geen accountgegevens. Gebruik je de
        zoekfunctie niet, dan wordt er niets gedeeld.
    </p>

    <h2><span class="n">6.</span> Bewaartermijnen</h2>
    <div class="terms-table">
        <table class="table table-dark mb-0">
            <thead><tr><th>Gegeven</th><th>Bewaartermijn</th></tr></thead>
            <tbody>
                <tr><td>Account- en inhoudsgegevens</td><td>Tot je het account (laat) verwijderen.</td></tr>
                <tr><td>Inlogpogingen (rate-limiting)</td><td>Automatisch opgeruimd na uiterlijk 1 uur.</td></tr>
                <tr><td>&ldquo;Onthoud mij&rdquo;-tokens</td><td>Tot de vervaldatum of bij uitloggen; bij elk gebruik geroteerd.</td></tr>
                <tr><td>Audit-logboek</td><td>Bewaard voor beveiliging en verantwoording; de beheerder bepaalt de termijn.</td></tr>
            </tbody>
        </table>
    </div>

    <h2><span class="n">7.</span> Beveiliging</h2>
    <p>
        LiveGig past technische en organisatorische maatregelen toe, waaronder wachtwoord-hashing
        (bcrypt), optionele tweefactorauthenticatie (TOTP), CSRF-bescherming, rate-limiting,
        beveiligde sessiecookies, security headers en optionele end-to-end-versleuteling per band.
    </p>

    <h2><span class="n">8.</span> Jouw rechten</h2>
    <p>Op grond van de AVG heb je recht op:</p>
    <ul>
        <li><strong>Inzage</strong> in je persoonsgegevens.</li>
        <li><strong>Rectificatie</strong> (correctie) van onjuiste gegevens.</li>
        <li><strong>Verwijdering</strong> (&ldquo;recht op vergetelheid&rdquo;).</li>
        <li><strong>Beperking</strong> van de verwerking.</li>
        <li><strong>Dataportabiliteit</strong> (gegevens overdragen).</li>
        <li><strong>Bezwaar</strong> tegen verwerking op grond van gerechtvaardigd belang.</li>
        <li><strong>Intrekken van toestemming</strong> (bijvoorbeeld 2FA uitschakelen), zonder terugwerkende kracht.</li>
    </ul>
    <p>
        Een verzoek richt je aan de beheerder (zie &sect;1). Veel rechten kun je zelf uitvoeren via
        <em>Profiel</em> (wachtwoord, 2FA) of door je account te laten verwijderen.
    </p>

    <h2><span class="n">9.</span> Klachten</h2>
    <p>
        Ben je het oneens met hoe je gegevens worden verwerkt, neem dan eerst contact op met de
        beheerder. Je hebt ook het recht een klacht in te dienen bij de toezichthouder, de
        <strong>Autoriteit Persoonsgegevens</strong> (autoriteitpersoonsgegevens.nl).
    </p>

    <h2><span class="n">10.</span> Datalekken</h2>
    <p>
        Bij een datalek met risico voor jouw rechten en vrijheden meldt de
        verwerkingsverantwoordelijke dit binnen 72 uur bij de Autoriteit Persoonsgegevens en,
        indien vereist, aan de betrokkenen.
    </p>
    <div class="terms-callout">
        Vermoed je een lek of kwetsbaarheid? Mail naar
        <a href="mailto:macsnoeren@gmail.com" class="link-light">macsnoeren@gmail.com</a>.
    </div>

    <h2><span class="n">11.</span> Wijzigingen</h2>
    <p>
        Dit privacybeleid kan worden aangepast wanneer de applicatie of regelgeving verandert. De
        datum bovenaan geeft de laatste wijziging aan.
    </p>

</div>

<footer class="site-footer">
    <div class="mb-2">
        <a href="<?= $ingelogd ? 'dashboard.php' : 'index.php' ?>">Home</a>
        <a href="handleiding.php">Handleiding</a>
        <a href="principes.php">Principes</a>
        <a href="voorwaarden.php">Voorwaarden</a>
        <a href="privacybeleid.php">Privacybeleid</a>
    </div>
    LiveGig &mdash; gebouwd voor muzikanten.
</footer>

</body>
</html>
