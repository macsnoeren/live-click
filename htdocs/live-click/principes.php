<?php
/**
 * Onze principes — publieke pagina, geen login vereist.
 *
 * Toont de overtuiging achter LiveGig (waarom de dienst bestaat, het
 * financiële model en de slotverklaring) als verhaal, niet als juridische
 * tekst. De volledige gebruiksvoorwaarden staan los in voorwaarden.php.
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
    <title>Onze principes — LiveGig</title>
    <meta name="description" content="Waarom LiveGig bestaat: een eerlijk, transparant en duurzaam hulpmiddel voor muzikanten. Niet meer vragen dan nodig, niet minder dan eerlijk.">
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
        .principe-hero {
            background: radial-gradient(ellipse at 50% 0%, #1a0000 0%, var(--bg) 70%);
            text-align: center;
            padding: 90px 20px 70px;
            border-bottom: 1px solid var(--nav-border);
        }
        .principe-hero .eyebrow {
            text-transform: uppercase; letter-spacing: 3px;
            font-size: 0.78rem; color: var(--accent); font-weight: 700;
        }
        .principe-hero h1 { font-weight: 800; font-size: clamp(2rem, 5vw, 3.1rem); color: #fff; margin: 14px 0 8px; }
        .principe-motto {
            font-size: clamp(1.15rem, 2.6vw, 1.6rem);
            color: #cfcfcf; font-weight: 300; line-height: 1.6;
            max-width: 640px; margin: 26px auto 0;
        }
        .principe-motto strong { color: #fff; font-weight: 600; }

        /* ── Inhoud ──────────────────────────────────────────────── */
        .principe-wrap { max-width: 760px; margin: 0 auto; padding: 64px 20px; }
        .principe-block { margin-bottom: 64px; }
        .principe-block:last-child { margin-bottom: 0; }
        .principe-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(204,0,0,0.12); border: 1px solid rgba(204,0,0,0.4);
            color: var(--accent); font-weight: 700; font-size: 0.9rem;
            margin-bottom: 16px;
        }
        .principe-block h2 { color: #fff; font-weight: 700; font-size: 1.6rem; margin-bottom: 18px; }
        .principe-block p { color: #c4c4c4; font-size: 1.05rem; line-height: 1.75; }
        .principe-block p strong { color: #ededed; }
        .pull {
            border-left: 3px solid var(--accent);
            padding: 6px 0 6px 22px; margin: 26px 0;
            font-size: 1.25rem; color: #fff; font-weight: 300; line-height: 1.55;
        }

        /* Kostentabel */
        .cost-table { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-top: 24px; }
        .cost-table table { margin: 0; }
        .cost-table th, .cost-table td { border-color: var(--card-border); padding: 11px 18px; }
        .cost-table thead th { background: #181818; color: #888; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.5px; }
        .cost-table td.amount, .cost-table th.amount { text-align: right; font-variant-numeric: tabular-nums; }
        .cost-table tr.total td { font-weight: 700; color: #fff; border-top: 2px solid #2e2e2e; background: #161616; }
        .cost-note {
            margin-top: 18px; padding: 14px 18px;
            background: rgba(204,0,0,0.06); border: 1px solid rgba(204,0,0,0.2);
            border-radius: 8px; color: #d9b3b3; font-size: 0.95rem;
        }
        .cost-note strong { color: #fff; }

        /* CTA-strip onderaan */
        .principe-cta { text-align: center; padding: 56px 20px 80px; border-top: 1px solid var(--nav-border); }
        .principe-cta h3 { color: #fff; font-weight: 700; }
        .principe-cta .btn { min-width: 180px; }

        .site-footer { text-align: center; padding: 30px 20px; border-top: 1px solid var(--nav-border); color: #666; font-size: 0.85rem; }
        .site-footer a { color: #999; text-decoration: none; margin: 0 10px; }
        .site-footer a:hover { color: #fff; }
    </style>
</head>
<body>

<!-- ── Topbalk ─────────────────────────────────────────────────── -->
<header class="site-top">
    <a href="<?= $ingelogd ? 'dashboard.php' : 'index.php' ?>" class="brand-link">
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
<section class="principe-hero">
    <div class="eyebrow">Onze principes</div>
    <h1>Gebouwd voor muzikanten</h1>
    <p class="principe-motto">
        Niet alles hoeft gericht te zijn op maximale groei of maximale winst.
        Een goed hulpmiddel hoeft geen groot bedrijf te worden &mdash; het hoeft vooral
        <strong>betrouwbaar, betaalbaar en duurzaam beschikbaar</strong> te blijven.
    </p>
</section>

<!-- ── Inhoud ──────────────────────────────────────────────────── -->
<div class="principe-wrap">

    <div class="principe-block">
        <span class="principe-num">1</span>
        <h2>Waarom deze dienst bestaat</h2>
        <p>
            LiveGig is gebouwd voor muzikanten. Het doel is simpel: bands, drummers en
            artiesten helpen bij het organiseren van nummers, setlijsten, repetities en
            live optredens. De software is ontstaan vanuit een persoonlijke behoefte en
            wordt onderhouden vanuit echte betrokkenheid bij de muziekwereld &mdash; niet
            vanuit een groeidoelstelling.
        </p>
        <div class="pull">
            &ldquo;Niet meer vragen dan nodig.<br>Niet minder dan eerlijk.&rdquo;
        </div>
        <p>
            Dat ene uitgangspunt stuurt elke keuze die we maken: welke functies we bouwen,
            hoe we met je gegevens omgaan, en wat een eerlijke prijs is. Geen verborgen
            agenda, geen advertenties, geen doorverkoop van data.
        </p>
    </div>

    <div class="principe-block">
        <span class="principe-num">2</span>
        <h2>Ons financiële model</h2>
        <p>
            LiveGig werkt op basis van een eenvoudig, transparant abonnement. Die bijdrage
            is er niet om zoveel mogelijk te verdienen, maar om de dienst <strong>overeind te
            houden</strong>. Het abonnement betaalt de infrastructuur, hosting en opslag,
            back-ups en beveiliging, ondersteuning en onderhoud, en een redelijke vergoeding
            voor het beheer.
        </p>
        <p>
            Prijswijzigingen voeren we alleen door wanneer dat redelijkerwijs nodig is voor de
            continuïteit. Niet om de marge te verhogen, maar om de lichten aan te houden.
        </p>
        <p>
            We vinden dat je mag weten waarvoor je betaalt. Daarom een voorbeeld van hoe de
            kosten ongeveer zijn opgebouwd:
        </p>

        <div class="cost-table">
            <table class="table table-dark mb-0">
                <thead>
                    <tr><th>Kostenpost</th><th class="amount">Per maand</th></tr>
                </thead>
                <tbody>
                    <tr><td>Hosting</td><td class="amount">&euro;40</td></tr>
                    <tr><td>Back-ups</td><td class="amount">&euro;10</td></tr>
                    <tr><td>Domeinen en certificaten</td><td class="amount">&euro;5</td></tr>
                    <tr><td>Monitoring en e-mail</td><td class="amount">&euro;15</td></tr>
                    <tr><td>Overige operationele kosten</td><td class="amount">&euro;10</td></tr>
                    <tr class="total"><td>Totaal operationeel</td><td class="amount">&euro;80</td></tr>
                    <tr><td>Onderhoud en ondersteuning (beheerder)</td><td class="amount">&euro;70</td></tr>
                    <tr class="total"><td>Totaal benodigd</td><td class="amount">&euro;150</td></tr>
                </tbody>
            </table>
        </div>

        <div class="cost-note">
            Bij ongeveer <strong>75 betalende gebruikers</strong> komt dit neer op zo&rsquo;n
            <strong>&euro;2 per gebruiker per maand</strong>. Dit overzicht is een voorbeeld;
            de werkelijke kosten kunnen afwijken. Het laat vooral de gedachte zien: een
            eerlijke verdeling van wat de dienst écht kost.
        </div>
    </div>

    <div class="principe-block">
        <span class="principe-num">3</span>
        <h2>Jouw muziek blijft van jou</h2>
        <p>
            Je nummers, setlijsten, teksten, notities en configuraties blijven van jou. Door
            inhoud op het platform te plaatsen geef je ons uitsluitend toestemming om die
            gegevens technisch te verwerken zodat de dienst kan werken &mdash; niets meer.
        </p>
        <p>
            Bij LiveGig is dat geen belofte op papier maar iets dat <strong>technisch wordt
            afgedwongen</strong>: kies je voor versleuteling, dan wordt je inhoud op je eigen
            apparaat versleuteld vóórdat die de server bereikt. Lees er meer over op de pagina
            <a href="privacy.php" class="link-light">Versleuteling &amp; privacy</a>.
        </p>
    </div>

    <div class="principe-block">
        <span class="principe-num">4</span>
        <h2>Onze belofte</h2>
        <p>
            We streven ernaar de dienst betrouwbaar te houden, zorgvuldig met gegevens om te
            gaan, je tijdig te informeren over belangrijke wijzigingen, functies te verbeteren
            waar het kan, en steeds het belang van muzikanten voorop te stellen. We streven
            naar langdurige beschikbaarheid &mdash; al kunnen we, zoals iedereen, geen garanties
            geven. Neem belangrijke informatie tijdens een optreden daarom altijd ook lokaal mee.
        </p>
    </div>

    <div class="principe-block">
        <span class="principe-num">5</span>
        <h2>Slotverklaring</h2>
        <p>
            Deze dienst bestaat dankzij de mensen die haar gebruiken. Het abonnement is geen
            manier om zoveel mogelijk geld te verdienen, maar een manier om een nuttig
            hulpmiddel voor muzikanten <strong>duurzaam beschikbaar</strong> te houden.
        </p>
        <div class="pull">
            We geloven dat software mag bestaan tussen &ldquo;gratis hobbyproject&rdquo;
            en &ldquo;winstgedreven platform&rdquo;.
        </div>
        <p>
            Een eerlijke bijdrage van gebruikers maakt het mogelijk om LiveGig te onderhouden,
            te verbeteren en onafhankelijk voort te zetten. Dat is het doel.
        </p>
    </div>

</div>

<!-- ── CTA ─────────────────────────────────────────────────────── -->
<section class="principe-cta">
    <h3 class="mb-3">Klinkt dit als jouw soort dienst?</h3>
    <p class="text-muted mb-4">Lees de volledige voorwaarden of begin meteen.</p>
    <a href="voorwaarden.php" class="btn btn-outline-light me-2 mb-2">
        <i class="bi bi-file-text"></i> Lees de voorwaarden
    </a>
    <?php if ($ingelogd): ?>
        <a href="dashboard.php" class="btn btn-danger fw-bold mb-2">
            <i class="bi bi-play-fill"></i> Naar dashboard
        </a>
    <?php else: ?>
        <a href="register.php" class="btn btn-danger fw-bold mb-2">
            <i class="bi bi-play-fill"></i> Account aanmaken
        </a>
    <?php endif; ?>
</section>

<footer class="site-footer">
    <div class="mb-2">
        <a href="index.php">Home</a>
        <a href="handleiding.php">Handleiding</a>
        <a href="principes.php">Principes</a>
        <a href="voorwaarden.php">Voorwaarden</a>
        <a href="privacybeleid.php">Privacybeleid</a>
    </div>
    LiveGig &mdash; gebouwd voor muzikanten.
</footer>

</body>
</html>
