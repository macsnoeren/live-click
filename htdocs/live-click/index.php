<?php
/**
 * Landingspagina (root) — publiek, geen login vereist.
 *
 * Verkoopt LiveGig aan muzikanten en linkt door naar registratie en login.
 * Ingelogde bezoekers sturen we direct naar het dashboard.
 */
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
sessionStart();
if (currentUser()) { header('Location: dashboard.php'); exit; }
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LiveGig — Click track &amp; setlist beheer voor bands</title>
    <meta name="description" content="LiveGig helpt bands en drummers met nummers, setlijsten, repetities en de click track tijdens live optredens. Eerlijk geprijsd, privacy-first.">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        body { background: var(--bg); overflow-x: hidden; }

        /* ── Topbalk ─────────────────────────────────────────────── */
        .site-top {
            position: sticky; top: 0; z-index: 1030;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px;
            background: rgba(13,13,13,0.82);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--nav-border);
        }
        .site-top .brand-link { text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .site-top .brand-name { font-size: 1.25rem; }
        .site-top .mini-dots { display: flex; gap: 5px; }
        .site-top .mini-dots i { width: 8px; height: 8px; border-radius: 50%; background: #333; display: block; }
        .site-top .mini-dots i.lit { background: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }
        .site-top nav a { color: #999; text-decoration: none; font-size: 0.95rem; margin-left: 18px; transition: color .15s; }
        .site-top nav a:hover { color: #fff; }
        .site-top nav a.btn-login { color: #fff; }

        /* ── Hero ────────────────────────────────────────────────── */
        .hero {
            position: relative;
            text-align: center;
            padding: 100px 20px 90px;
            background: radial-gradient(ellipse at 50% -10%, #200000 0%, var(--bg) 65%);
            border-bottom: 1px solid var(--nav-border);
        }
        .hero .eyebrow { text-transform: uppercase; letter-spacing: 3px; font-size: 0.78rem; color: var(--accent); font-weight: 700; }
        .hero h1 { font-weight: 800; font-size: clamp(2.2rem, 6vw, 4rem); color: #fff; line-height: 1.1; margin: 18px auto 0; max-width: 800px; }
        .hero h1 .accent { color: var(--accent); }
        .hero p.lead { color: #b9b9b9; font-size: clamp(1.05rem, 2.4vw, 1.3rem); font-weight: 300; max-width: 600px; margin: 22px auto 0; line-height: 1.6; }
        .hero-cta { margin-top: 38px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .hero-cta .btn { min-width: 190px; padding: 12px 22px; font-size: 1.05rem; }

        /* Geanimeerde beat-dots als click track */
        .hero-dots { display: flex; gap: 16px; justify-content: center; margin: 46px 0 0; }
        .hero-dots .beat-dot {
            width: 54px; height: 54px; font-size: 1.1rem;
            animation: beatPulse 2s infinite steps(1);
        }
        .hero-dots .beat-dot:nth-child(1) { animation-delay: 0s; }
        .hero-dots .beat-dot:nth-child(2) { animation-delay: .5s; }
        .hero-dots .beat-dot:nth-child(3) { animation-delay: 1s; }
        .hero-dots .beat-dot:nth-child(4) { animation-delay: 1.5s; }
        @keyframes beatPulse {
            0%   { background: var(--accent); border-color: #ff2222; color: #fff; box-shadow: 0 0 16px var(--accent-glow); }
            25%, 100% { background: #1e1e1e; border-color: #383838; color: #444; box-shadow: none; }
        }
        @media (prefers-reduced-motion: reduce) { .hero-dots .beat-dot { animation: none; } }

        /* ── Secties ─────────────────────────────────────────────── */
        .section { padding: 80px 20px; }
        .section-inner { max-width: 1040px; margin: 0 auto; }
        .section-title { text-align: center; margin-bottom: 12px; color: #fff; font-weight: 800; font-size: clamp(1.6rem, 4vw, 2.4rem); }
        .section-sub { text-align: center; color: #999; max-width: 560px; margin: 0 auto 52px; font-size: 1.05rem; line-height: 1.6; }

        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .feature {
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: 12px; padding: 28px 24px; transition: border-color .2s, transform .2s;
        }
        .feature:hover { border-color: #3a3a3a; transform: translateY(-3px); }
        .feature .ico {
            width: 48px; height: 48px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(204,0,0,0.1); border: 1px solid rgba(204,0,0,0.3);
            color: var(--accent); font-size: 1.5rem; margin-bottom: 18px;
        }
        .feature h3 { color: #fff; font-size: 1.2rem; font-weight: 700; margin-bottom: 10px; }
        .feature p { color: #aaa; font-size: 0.97rem; line-height: 1.65; margin: 0; }

        /* Principes-strip */
        .principe-strip { background: radial-gradient(ellipse at 50% 50%, #160000 0%, var(--bg) 75%); border-top: 1px solid var(--nav-border); border-bottom: 1px solid var(--nav-border); }
        .principe-strip .quote { font-size: clamp(1.4rem, 3.6vw, 2.2rem); color: #fff; font-weight: 300; line-height: 1.5; max-width: 720px; margin: 0 auto 12px; text-align: center; }
        .principe-strip .quote strong { font-weight: 700; }
        .principe-strip .sub { text-align: center; color: #bbb; max-width: 560px; margin: 0 auto 30px; line-height: 1.7; }

        /* Prijs-blok */
        .price-card {
            max-width: 460px; margin: 0 auto;
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: 16px; padding: 40px 32px; text-align: center;
            box-shadow: 0 0 80px rgba(204,0,0,0.06);
        }
        .price-card .amount { font-size: 3.4rem; font-weight: 900; color: #fff; line-height: 1; }
        .price-card .amount span { font-size: 1.1rem; color: #888; font-weight: 400; }
        .price-card .note { color: #999; margin: 14px auto 24px; max-width: 320px; font-size: 0.95rem; line-height: 1.6; }
        .price-card ul { list-style: none; padding: 0; margin: 0 0 26px; text-align: left; }
        .price-card li { color: #cfcfcf; padding: 7px 0; display: flex; align-items: center; gap: 10px; }
        .price-card li i { color: var(--accent); }

        .site-footer { text-align: center; padding: 40px 20px; border-top: 1px solid var(--nav-border); color: #666; font-size: 0.85rem; }
        .site-footer a { color: #999; text-decoration: none; margin: 0 10px; }
        .site-footer a:hover { color: #fff; }
    </style>
</head>
<body>

<!-- ── Topbalk ─────────────────────────────────────────────────── -->
<header class="site-top">
    <a href="index.php" class="brand-link">
        <span class="mini-dots"><i class="lit"></i><i></i><i></i><i class="lit"></i></span>
        <span class="brand-name">LiveGig</span>
    </a>
    <nav>
        <a href="principes.php">Principes</a>
        <a href="voorwaarden.php">Voorwaarden</a>
        <a href="login.php" class="btn-login">Inloggen</a>
    </nav>
</header>

<!-- ── Hero ────────────────────────────────────────────────────── -->
<section class="hero">
    <div class="eyebrow">Click track &amp; setlist beheer</div>
    <h1>Strak op de tel, <span class="accent">live op het podium</span></h1>
    <p class="lead">
        LiveGig houdt je nummers, setlijsten en click track op één plek &mdash;
        van de repetitieruimte tot het podium. Gebouwd door en voor muzikanten.
    </p>
    <div class="hero-cta">
        <a href="register.php" class="btn btn-danger fw-bold">
            <i class="bi bi-play-fill"></i> Gratis account aanmaken
        </a>
        <a href="login.php" class="btn btn-outline-light">
            <i class="bi bi-box-arrow-in-right"></i> Inloggen
        </a>
    </div>

    <div class="hero-dots">
        <div class="beat-dot"><span>1</span></div>
        <div class="beat-dot"><span>2</span></div>
        <div class="beat-dot"><span>3</span></div>
        <div class="beat-dot"><span>4</span></div>
    </div>
</section>

<!-- ── Features ────────────────────────────────────────────────── -->
<section class="section">
    <div class="section-inner">
        <h2 class="section-title">Alles voor je optreden</h2>
        <p class="section-sub">Geen losse spreadsheets of papieren setlijsten meer. Eén overzicht dat met je band meereist.</p>

        <div class="feature-grid">
            <div class="feature">
                <div class="ico"><i class="bi bi-music-note-beamed"></i></div>
                <h3>Nummers &amp; BPM</h3>
                <p>Beheer je repertoire met BPM, toonsoort, duur, notities, akkoorden en drumstructuur &mdash; alles per nummer bij de hand.</p>
            </div>
            <div class="feature">
                <div class="ico"><i class="bi bi-list-ol"></i></div>
                <h3>Setlijsten</h3>
                <p>Stel setlijsten samen, sleep nummers in volgorde en deel ze via een publieke link met de rest van de band.</p>
            </div>
            <div class="feature">
                <div class="ico"><i class="bi bi-soundwave"></i></div>
                <h3>Click track</h3>
                <p>Een ingebouwde metronoom die automatisch de juiste BPM per nummer pakt. Strak meetellen tijdens de hele set.</p>
            </div>
            <div class="feature">
                <div class="ico"><i class="bi bi-people-fill"></i></div>
                <h3>Samen met je band</h3>
                <p>Nodig bandleden uit als leider, lid of kijker. Iedereen ziet dezelfde, altijd actuele nummers en setlijsten.</p>
            </div>
            <div class="feature">
                <div class="ico"><i class="bi bi-shield-lock"></i></div>
                <h3>End-to-end versleuteld</h3>
                <p>Versleutel je band en je inhoud wordt op je eigen apparaat versleuteld. Zelfs wij kunnen er niet bij. <a href="privacy.php" class="link-light">Meer&nbsp;&rarr;</a></p>
            </div>
            <div class="feature">
                <div class="ico"><i class="bi bi-phone"></i></div>
                <h3>Werkt op elk scherm</h3>
                <p>Telefoon op een standaard tijdens de gig of laptop in de oefenruimte &mdash; LiveGig past zich aan elk scherm aan.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Principes-strip ─────────────────────────────────────────── -->
<section class="section principe-strip">
    <div class="section-inner">
        <p class="quote">&ldquo;Niet meer vragen dan nodig.<br><strong>Niet minder dan eerlijk.</strong>&rdquo;</p>
        <p class="sub">
            LiveGig is geen winstgedreven platform en geen wegwerp-hobbyproject. Het is een
            eerlijk hulpmiddel dat betaalbaar en duurzaam beschikbaar moet blijven &mdash;
            transparant over wat het kost en waarvoor je betaalt.
        </p>
        <div class="text-center">
            <a href="principes.php" class="btn btn-outline-light">
                <i class="bi bi-arrow-right"></i> Lees onze principes
            </a>
        </div>
    </div>
</section>

<!-- ── Prijs ───────────────────────────────────────────────────── -->
<section class="section">
    <div class="section-inner">
        <h2 class="section-title">Eerlijk geprijsd</h2>
        <p class="section-sub">Eén transparant abonnement dat precies dekt wat de dienst kost &mdash; niet meer.</p>

        <div class="price-card">
            <div class="amount">&euro;2<span> / gebruiker / maand</span></div>
            <p class="note">Een richtprijs op basis van de werkelijke kosten. Geen advertenties, geen dataverkoop, geen verborgen extra&rsquo;s.</p>
            <ul>
                <li><i class="bi bi-check-lg"></i> Onbeperkt nummers en setlijsten</li>
                <li><i class="bi bi-check-lg"></i> Click track inbegrepen</li>
                <li><i class="bi bi-check-lg"></i> End-to-end versleuteling</li>
                <li><i class="bi bi-check-lg"></i> Je data blijft van jou</li>
                <li><i class="bi bi-check-lg"></i> Opzegbaar wanneer je wilt</li>
            </ul>
            <a href="register.php" class="btn btn-danger fw-bold w-100">
                <i class="bi bi-play-fill"></i> Begin nu
            </a>
            <p class="text-muted small mt-3 mb-0">
                Nieuwsgierig naar de opbouw? <a href="principes.php" class="link-light">Bekijk de kostenverdeling</a>.
            </p>
        </div>
    </div>
</section>

<footer class="site-footer">
    <div class="mb-2">
        <a href="principes.php">Principes</a>
        <a href="voorwaarden.php">Voorwaarden</a>
        <a href="privacy.php">Privacy</a>
        <a href="login.php">Inloggen</a>
        <a href="register.php">Registreren</a>
    </div>
    LiveGig &mdash; gebouwd voor muzikanten.
</footer>

</body>
</html>
