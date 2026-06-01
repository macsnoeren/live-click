<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Versleuteling & privacy — LiveGig';
require APP_ROOT . '/includes/header.php';
?>

<div class="container py-4" style="max-width:760px">

    <h3 class="mb-1"><i class="bi bi-shield-lock"></i> Versleuteling &amp; privacy</h3>
    <p class="text-muted">Wat het betekent om je band end-to-end te versleutelen.</p>

    <div class="card mb-3">
        <div class="card-body">
            <h5><i class="bi bi-lock-fill text-success me-1"></i> Wat is end-to-end-versleuteling?</h5>
            <p class="mb-0">
                Als je je band versleutelt, wordt alle inhoud — nummers, notities, songteksten,
                akkoorden, drumstructuren en setlijsten (inclusief titels en artiesten) — op je
                eigen apparaat <strong>versleuteld</strong> voordat het naar de server gaat. Alleen
                bandleden kunnen het weer leesbaar maken. De server slaat enkel onleesbare,
                versleutelde gegevens op.
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h5><i class="bi bi-people-fill text-info me-1"></i> Wie kan de inhoud lezen?</h5>
            <ul class="mb-0">
                <li><strong>Bandleden</strong> (leider, lid en kijker) kunnen de inhoud zien.</li>
                <li><strong>De serverbeheerder en hoster niet</strong> — zij zien alleen versleutelde data.</li>
                <li>Zelfs een <strong>platformbeheerder (admin)</strong> kan de inhoud van je band niet inzien.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h5><i class="bi bi-key-fill text-warning me-1"></i> Je wachtwoord is de sleutel</h5>
            <p>
                De versleuteling is gekoppeld aan je wachtwoord. Daarom krijg je bij het
                versleutelen eenmalig een <strong>herstelcode</strong> te zien. Bewaar die goed
                (bijvoorbeeld in een wachtwoordmanager).
            </p>
            <div class="alert alert-warning py-2 mb-0 small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Ben je je wachtwoord <em>én</em> je herstelcode kwijt, dan kan
                <strong>niemand</strong> — ook wij niet — de versleutelde inhoud nog herstellen.
                Dat is precies wat de versleuteling zo veilig maakt.
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h5><i class="bi bi-share-fill text-info me-1"></i> Delen en samenwerken blijft werken</h5>
            <ul class="mb-0">
                <li>Nieuwe leden krijgen automatisch toegang zodra ze zijn ingelogd.</li>
                <li>Je kunt nummers importeren uit andere bands waar je lid of leider van bent.</li>
                <li>Een publieke setlijst-deellink blijft mogelijk; de sleutel zit dan veilig
                    in de link zelf en bereikt de server nooit.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h5><i class="bi bi-info-circle text-muted me-1"></i> Wat is leesbaar voor de server?</h5>
            <p class="mb-0">
                De <strong>inhoud</strong> is versleuteld. Een beperkte set technische gegevens
                blijft zichtbaar voor de werking: dat er bands, nummers en setlijsten bestaan,
                hoeveel het er zijn, en wanneer ze zijn aangemaakt. De bandnaam zelf blijft
                leesbaar zodat je hem in menu's kunt kiezen.
            </p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5><i class="bi bi-toggle-on text-success me-1"></i> Versleuteling aan of uit?</h5>
            <p class="mb-0">
                We raden versleuteling <strong>aan</strong> voor maximale privacy. Kies je ervoor
                om je band <em>niet</em> te versleutelen, dan werkt alles ook gewoon, maar dan kan
                de serverbeheerder de inhoud in principe inzien. Je kunt een band later alsnog
                versleutelen via de knop <em>"Band versleutelen"</em> op de Bands-pagina.
            </p>
        </div>
    </div>

    <a href="bands.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Terug naar Bands
    </a>

</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
