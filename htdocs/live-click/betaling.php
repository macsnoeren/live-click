<?php
/**
 * Terugkeerpagina na een Mollie-betaling (MOLLIE_REDIRECT_URL wijst hierheen).
 *
 * Bewust een KALE pagina: geen header.php/footer.php, dus geen kluis-ontgrendel-
 * prompt vlak na het betalen. We tonen alleen de status van het abonnement en
 * sturen daarna door. De echte verwerking gebeurt server-side via de webhook;
 * deze pagina pollt alleen de status (die kan een paar seconden achterlopen).
 */
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>Betaling verwerken — LiveGig</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="d-flex align-items-center justify-content-center min-vh-100 px-3">
    <div class="login-card text-center">
        <div class="login-logo mb-3">
            <div class="beat-dot lit"></div>
            <div class="beat-dot"></div>
            <div class="beat-dot"></div>
            <div class="beat-dot lit"></div>
        </div>

        <!-- Bezig met verwerken -->
        <div id="state-processing">
            <div class="spinner-border text-danger mb-3" role="status"></div>
            <h5 class="text-white">Betaling verwerken…</h5>
            <p class="text-muted small mb-0">Een ogenblik, we bevestigen je abonnement.</p>
        </div>

        <!-- Gelukt -->
        <div id="state-success" style="display:none">
            <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
            <h5 class="text-white mt-3" id="success-title">Je abonnement is actief</h5>
            <p class="text-muted small" id="success-text"></p>
            <a href="dashboard.php" class="btn btn-danger w-100 mt-2">
                <i class="bi bi-house-fill me-1"></i> Naar dashboard
            </a>
            <a href="bands.php" class="btn btn-link w-100 text-muted">Naar mijn bands</a>
        </div>

        <!-- Mislukt / afgebroken -->
        <div id="state-failed" style="display:none">
            <i class="bi bi-x-circle-fill text-danger" style="font-size:3rem"></i>
            <h5 class="text-white mt-3">Betaling niet gelukt</h5>
            <p class="text-muted small">
                Je betaling is niet voltooid of geannuleerd. Er is niets in rekening gebracht.
                Je kunt het opnieuw proberen.
            </p>
            <a href="subscribe.php" class="btn btn-danger w-100 mt-2">
                <i class="bi bi-arrow-repeat me-1"></i> Opnieuw proberen
            </a>
            <a href="dashboard.php" class="btn btn-link w-100 text-muted">Naar dashboard</a>
        </div>

        <!-- Nog niet verwerkt / niet voltooid -->
        <div id="state-pending" style="display:none">
            <i class="bi bi-hourglass-split text-warning" style="font-size:3rem"></i>
            <h5 class="text-white mt-3">Nog even geduld</h5>
            <p class="text-muted small" id="pending-text">
                We hebben je betaling nog niet bevestigd zien worden. Dit kan een
                moment duren. Je kunt deze pagina verversen of later naar je
                abonnement kijken.
            </p>
            <button onclick="location.reload()" class="btn btn-outline-secondary w-100 mt-2">
                <i class="bi bi-arrow-clockwise me-1"></i> Opnieuw controleren
            </button>
            <a href="subscribe.php" class="btn btn-link w-100 text-muted">Naar abonnement</a>
        </div>
    </div>
</div>

<script>
(function() {
    var tries = 0, maxTries = 12; // ~24s (elke 2s)

    function show(id) {
        ["state-processing", "state-success", "state-failed", "state-pending"].forEach(function(s) {
            document.getElementById(s).style.display = (s === id) ? "" : "none";
        });
    }

    function succeed(sub) {
        var title = document.getElementById("success-title");
        var text  = document.getElementById("success-text");
        if (sub && sub.status === "trialing") {
            title.textContent = "Je proefperiode is gestart";
            text.textContent  = sub.trial_ends_at
                ? "Gratis tot " + String(sub.trial_ends_at).substring(0, 10) + ". Daarna wordt automatisch geïncasseerd. Je kunt altijd opzeggen."
                : "Je gratis proefperiode loopt. Je kunt nu een band aanmaken.";
        } else {
            title.textContent = "Je abonnement is actief";
            text.textContent  = "Bedankt! Je kunt nu een band aanmaken en beheren.";
        }
        show("state-success");
    }

    function poll() {
        fetch("api/subscribe.php", { credentials: "same-origin", headers: { "Accept": "application/json" } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var sub = d && d.subscription;
                var status = sub ? sub.status : null;
                if (status === "trialing" || status === "active") { succeed(sub); return; }
                // Betaling mislukt/afgebroken/verlopen → meteen duidelijke melding,
                // niet blijven "verwerken".
                if (["failed", "expired", "canceled"].indexOf(d.last_payment) !== -1) {
                    show("state-failed"); return;
                }
                if (++tries >= maxTries) {
                    // Na de wachttijd: 'pending' = wordt nog echt verwerkt (bv.
                    // overschrijving) → geduld. 'open'/geen betaling = niet afgerond
                    // → toon de duidelijke "niet gelukt"-melding i.p.v. te blijven hangen.
                    show(d.last_payment === "pending" ? "state-pending" : "state-failed");
                    return;
                }
                setTimeout(poll, 2000);
            })
            .catch(function() {
                if (++tries >= maxTries) { show("state-pending"); return; }
                setTimeout(poll, 2000);
            });
    }

    poll();
})();
</script>
</body>
</html>
