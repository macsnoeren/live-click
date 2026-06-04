<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/mollie.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Abonnement — LiveGig';

$enabled = mollieEnabled();
$amount  = $enabled ? MOLLIE_SUBSCRIPTION_AMOUNT : '';
$trialDays = $enabled ? (int)MOLLIE_TRIAL_DAYS : 0;

require APP_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3" style="max-width:680px">
    <h4 class="mb-3"><i class="bi bi-stars"></i> Abonnement</h4>

    <?php if (!$enabled): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Betalingen zijn nog niet geconfigureerd. Neem contact op met de beheerder.
        </div>
    <?php else: ?>

    <p class="text-muted">
        Registreren en meedoen als lid of kijker is gratis. Wil je zelf
        <strong>een band aanmaken en leiden</strong>, dan heb je een abonnement nodig.
        Je eerste <strong><?= $trialDays ?> dagen zijn gratis</strong> — daarna
        <strong><?= htmlspecialchars($amount) ?> per maand</strong>, maandelijks opzegbaar.
    </p>
    <p class="text-muted small">
        <i class="bi bi-heart me-1"></i>
        Geen winstoogmerk — de bijdrage dekt enkel de kosten (hosting, opslag, back-ups en onderhoud)
        en houdt LiveGig betaalbaar en onafhankelijk. <a href="principes.php" target="_blank" rel="noopener">Onze principes</a>.
    </p>

    <?php if ($user['role'] === 'admin'): ?>
    <div class="alert alert-info py-2 small">
        <i class="bi bi-shield-fill me-1"></i>
        Als beheerder heb je altijd volledige toegang — een abonnement is voor jou optioneel.
    </div>
    <?php endif; ?>

    <div class="card" id="sub-card">
        <div class="card-body">
            <div id="sub-status" class="text-muted">Status laden...</div>
            <div id="sub-actions" class="mt-3"></div>
        </div>
    </div>

    <p class="text-muted small mt-3">
        <i class="bi bi-shield-lock me-1"></i>
        Betalen verloopt veilig via Mollie (iDEAL, creditcard). Bij het starten
        leg je eenmalig een incassomachtiging vast; daarna verloopt het
        automatisch. Je kunt op elk moment opzeggen.
    </p>

    <?php endif; ?>
</div>

<?php
$extraScripts = '<script>
$(function() { loadSubStatus(); });

var SUB_LABEL = {
    pending:  ["secondary", "Nog niet actief"],
    trialing: ["info text-dark", "Proefperiode loopt"],
    active:   ["success", "Actief"],
    suspended:["warning text-dark", "Betaling mislukt — opgeschort"],
    canceled: ["secondary", "Opgezegd"]
};

function loadSubStatus() {
    $.get("api/subscribe.php", function(d) {
        if (!d.ok) { $("#sub-status").text(d.error || "Kon status niet laden."); return; }
        var sub = d.subscription;
        var statusEl = $("#sub-status");
        var actEl = $("#sub-actions");
        actEl.empty();

        // Opgezegd, maar nog binnen de betaalde/proefperiode → toegang tot ends_at,
        // met de mogelijkheid om te heractiveren (gaat in vanaf het einde, geen kosten nu).
        if (sub && sub.status === "canceled" && d.active) {
            statusEl.html("<span class=\"badge bg-warning text-dark\">Opgezegd</span>"
                + "<div class=\"small text-muted mt-2\">Je houdt toegang tot <strong>" + escHtml(sub.ends_at || "") + "</strong>. "
                + "Daarna stopt het abonnement en worden je bands geblokkeerd.<br>"
                + "Heractiveer je nu, dan loopt het gewoon door vanaf die datum — je betaalt nu niets extra.</div>");
            actEl.html("<button class=\"btn btn-danger\" id=\"start-btn\" onclick=\"startSub()\"><i class=\"bi bi-arrow-repeat\"></i> Heractiveren</button>");
            return;
        }

        if (!sub || sub.status === "pending" || sub.status === "canceled") {
            var trialNote = d.trial_used
                ? "Je gratis proefmaand is al gebruikt; het abonnement start direct."
                : "Start je gratis proefmaand. Je betaalt nu niets; na de proefperiode wordt automatisch geïncasseerd.";
            statusEl.html("<span class=\"badge bg-secondary\">Geen actief abonnement</span><div class=\"small text-muted mt-2\">" + trialNote + "</div>");
            actEl.html("<button class=\"btn btn-danger\" id=\"start-btn\" onclick=\"startSub()\"><i class=\"bi bi-play-fill\"></i> " + (d.trial_used ? "Abonnement starten" : "Gratis proefmaand starten") + "</button>");
            return;
        }

        var lbl = SUB_LABEL[sub.status] || ["secondary", sub.status];
        var extra = "";
        if (sub.status === "trialing" && sub.trial_ends_at)  extra = "<div class=\"small text-muted mt-2\">Proef loopt tot " + escHtml(sub.trial_ends_at) + ". Daarna " + escHtml(sub.amount) + " " + escHtml(sub.currency) + " per " + escHtml(sub.interval) + ".</div>";
        if (sub.status === "active" && sub.next_payment_at) extra = "<div class=\"small text-muted mt-2\">Volgende incasso: " + escHtml(sub.next_payment_at) + ".</div>";
        if (sub.status === "suspended") extra = "<div class=\"small text-warning mt-2\">De laatste incasso mislukte. Je bands zijn geblokkeerd tot de betaling lukt.</div>";
        statusEl.html("<span class=\"badge bg-" + lbl[0] + "\">" + escHtml(lbl[1]) + "</span>" + extra);

        if (sub.status === "trialing" || sub.status === "active") {
            actEl.html("<button class=\"btn btn-outline-danger btn-sm\" onclick=\"cancelSub()\"><i class=\"bi bi-x-circle\"></i> Opzeggen</button>");
        } else if (sub.status === "suspended") {
            actEl.html("<button class=\"btn btn-danger\" onclick=\"startSub()\"><i class=\"bi bi-arrow-repeat\"></i> Betaling herstellen</button>");
        }
    });
}

function startSub() {
    $("#start-btn").prop("disabled", true);
    $.post("api/subscribe.php", JSON.stringify({action: "start"}), function(r) {
        if (r.checkout_url) { window.location.href = r.checkout_url; return; }
        if (r.already_active || r.reactivated) { loadSubStatus(); return; }
        alert(r.error || "Kon de betaling niet starten.");
        $("#start-btn").prop("disabled", false);
    }, "json").fail(function() { alert("Er ging iets mis."); $("#start-btn").prop("disabled", false); });
}

function cancelSub() {
    if (!confirm("Abonnement opzeggen? Je houdt toegang tot het einde van de huidige periode; daarna worden je bands geblokkeerd. Je kunt vóór die tijd nog kosteloos heractiveren.")) return;
    $.post("api/subscribe.php", JSON.stringify({action: "cancel"}), function(r) {
        if (r.ok) loadSubStatus();
        else alert(r.error || "Opzeggen mislukt.");
    }, "json");
}
</script>';
require APP_ROOT . '/includes/footer.php';
?>
