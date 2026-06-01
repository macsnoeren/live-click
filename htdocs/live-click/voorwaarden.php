<?php
/**
 * Gebruiksvoorwaarden & Fair Service Charter — publiek, geen login vereist.
 *
 * De volledige juridische tekst. De "zachte" secties (waarom, financieel model,
 * slotverklaring) staan ook als verhaal op principes.php.
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
    <title>Gebruiksvoorwaarden — LiveGig</title>
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
        .terms-table th, .terms-table td { border-color: var(--card-border); padding: 10px 16px; }
        .terms-table thead th { background: #181818; color: #888; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.5px; }
        .terms-table .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .terms-table tr.total td { font-weight: 700; color: #fff; background: #161616; border-top: 2px solid #2e2e2e; }
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
    <div class="eyebrow">Fair Service Charter</div>
    <h1>Gebruiksvoorwaarden</h1>
    <div class="updated">Laatst bijgewerkt: <?= date('j F Y') ?></div>
</div>

<div class="terms-wrap">

    <p class="lead-intro">
        Deze voorwaarden leggen vast hoe LiveGig werkt en waar we voor staan. De gedachte
        erachter lees je losser verteld op de pagina <a href="principes.php" class="link-light">Onze principes</a>.
    </p>

    <h2><span class="n">1.</span> Waarom deze dienst bestaat</h2>
    <p>Deze dienst is gebouwd voor muzikanten.</p>
    <p>
        Het doel is om muzikanten, bands en drummers te helpen bij het organiseren van
        nummers, setlijsten, repetities en live optredens. De software is ontstaan vanuit een
        persoonlijke behoefte en wordt onderhouden vanuit betrokkenheid bij de muziekwereld.
    </p>
    <p>
        Wij geloven dat niet alles gericht hoeft te zijn op maximale groei of maximale winst.
        Een goed hulpmiddel hoeft geen groot bedrijf te worden. Het hoeft vooral betrouwbaar,
        betaalbaar en duurzaam beschikbaar te blijven.
    </p>
    <p>Daarom hanteren wij een eenvoudig uitgangspunt:</p>
    <blockquote>Niet meer vragen dan nodig.<br>Niet minder dan eerlijk.</blockquote>

    <h2><span class="n">2.</span> Ons financiële model</h2>
    <p>Deze dienst werkt op basis van een transparant abonnementsmodel. Het abonnement is bedoeld om:</p>
    <ul>
        <li>infrastructuurkosten te betalen;</li>
        <li>hosting en opslag te bekostigen;</li>
        <li>back-ups en beveiliging te verzorgen;</li>
        <li>ondersteuning en onderhoud mogelijk te maken;</li>
        <li>een redelijke vergoeding te bieden voor het beheer van de dienst.</li>
    </ul>
    <p>Het doel van het abonnement is niet winstmaximalisatie.</p>
    <p>Prijswijzigingen worden uitsluitend doorgevoerd wanneer dit redelijkerwijs nodig is voor de continuïteit van de dienst.</p>

    <h2><span class="n">3.</span> Transparantie</h2>
    <p>Wij vinden dat gebruikers mogen weten waarvoor zij betalen. Daarom streven wij naar openheid over de kosten van het platform. Een voorbeeld:</p>
    <div class="terms-table">
        <table class="table table-dark mb-0">
            <thead><tr><th>Kostenpost</th><th class="amount">Per maand</th></tr></thead>
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
    <p>Bij 75 betalende gebruikers betekent dit ongeveer &euro;2 per gebruiker per maand. Dit overzicht is slechts een voorbeeld. Werkelijke kosten kunnen afwijken.</p>

    <h2><span class="n">4.</span> Onze belofte</h2>
    <p>Wij streven ernaar om:</p>
    <ul>
        <li>de dienst betrouwbaar te houden;</li>
        <li>zorgvuldig met gegevens om te gaan;</li>
        <li>gebruikers tijdig te informeren over belangrijke wijzigingen;</li>
        <li>functies te verbeteren waar mogelijk;</li>
        <li>de belangen van muzikanten voorop te stellen.</li>
    </ul>
    <p>Wij streven naar langdurige beschikbaarheid van de dienst, maar kunnen deze niet garanderen.</p>

    <h2><span class="n">5.</span> Geen eigendom van jouw muziek</h2>
    <p>Gebruikers blijven eigenaar van hun eigen inhoud. Dit omvat onder andere:</p>
    <ul>
        <li>nummers;</li>
        <li>setlijsten;</li>
        <li>teksten;</li>
        <li>notities;</li>
        <li>configuraties;</li>
        <li>andere gegevens die door gebruikers zijn ingevoerd.</li>
    </ul>
    <p>Door inhoud op het platform te plaatsen geef je uitsluitend toestemming om deze gegevens technisch te verwerken voor het functioneren van de dienst.</p>

    <h2><span class="n">6.</span> Beschikbaarheid van de dienst</h2>
    <p>Wij doen ons best om de dienst beschikbaar te houden. Toch kunnen storingen voorkomen, bijvoorbeeld door:</p>
    <ul>
        <li>technische problemen;</li>
        <li>onderhoudswerkzaamheden;</li>
        <li>fouten in software;</li>
        <li>problemen bij externe leveranciers;</li>
        <li>overmacht.</li>
    </ul>
    <p>Wij geven geen garantie dat de dienst altijd beschikbaar, foutloos of ononderbroken zal functioneren.</p>

    <h2><span class="n">7.</span> Gebruik op eigen verantwoordelijkheid</h2>
    <p>De dienst is een hulpmiddel. Gebruikers blijven zelf verantwoordelijk voor:</p>
    <ul>
        <li>optredens;</li>
        <li>repetities;</li>
        <li>uitvoeringen;</li>
        <li>het bewaren van belangrijke gegevens;</li>
        <li>het hebben van een alternatief wanneer de dienst niet beschikbaar is.</li>
    </ul>
    <p>Wij raden aan om belangrijke informatie altijd ook lokaal of op een andere manier beschikbaar te hebben tijdens optredens.</p>

    <h2><span class="n">8.</span> Aansprakelijkheid</h2>
    <p>Voor zover wettelijk toegestaan wordt de dienst geleverd &ldquo;zoals deze is&rdquo; en &ldquo;zoals beschikbaar&rdquo;. De eigenaar van deze dienst is niet aansprakelijk voor:</p>
    <ul>
        <li>directe of indirecte schade;</li>
        <li>verlies van inkomsten;</li>
        <li>verlies van optredens;</li>
        <li>verlies van gegevens;</li>
        <li>verlies van zakelijke kansen;</li>
        <li>reputatieschade;</li>
        <li>gevolgschade van welke aard dan ook.</li>
    </ul>
    <p>De totale aansprakelijkheid is in alle gevallen beperkt tot het bedrag dat de gebruiker in de twaalf maanden voorafgaand aan de gebeurtenis aan abonnementskosten heeft betaald.</p>
    <div class="terms-callout">
        Indien geen abonnementskosten zijn betaald, is iedere aansprakelijkheid uitgesloten voor zover wettelijk toegestaan.
    </div>

    <h2><span class="n">9.</span> Wijzigingen</h2>
    <p>De dienst kan op ieder moment worden gewijzigd, uitgebreid of vereenvoudigd. Functies kunnen worden toegevoegd, aangepast of verwijderd wanneer dit nodig is voor de ontwikkeling of continuïteit van het platform.</p>
    <p>Wij zullen proberen belangrijke wijzigingen tijdig aan te kondigen.</p>

    <h2><span class="n">10.</span> Beëindiging</h2>
    <p>Gebruikers kunnen hun abonnement op ieder moment beëindigen. Wij behouden ons het recht voor accounts te beëindigen wanneer:</p>
    <ul>
        <li>deze voorwaarden worden overtreden;</li>
        <li>de dienst wordt misbruikt;</li>
        <li>de veiligheid of stabiliteit van het platform in gevaar komt.</li>
    </ul>

    <h2><span class="n">11.</span> Slotverklaring</h2>
    <p>Deze dienst bestaat dankzij de mensen die haar gebruiken.</p>
    <p>Het abonnement is geen middel om zoveel mogelijk geld te verdienen, maar een manier om een nuttig hulpmiddel voor muzikanten duurzaam beschikbaar te houden.</p>
    <blockquote>Wij geloven dat software mag bestaan tussen &ldquo;gratis hobbyproject&rdquo; en &ldquo;winstgedreven platform&rdquo;.</blockquote>
    <p>Een eerlijke bijdrage van gebruikers maakt het mogelijk om de dienst te onderhouden, te verbeteren en onafhankelijk voort te zetten. Dat is het doel.</p>

</div>

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
