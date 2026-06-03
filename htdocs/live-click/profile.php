<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
$user      = currentUser();
$db        = getDB();
$row       = $db->prepare('SELECT totp_enabled FROM users WHERE id = ?');
$row->execute([$user['id']]);
$totpEnabled = (bool)($row->fetchColumn() ?: 0);
$backupCodesRemaining = $totpEnabled ? countUnusedBackupCodes((int)$user['id']) : 0;

$pageTitle = 'Profiel — LiveGig';
require APP_ROOT . '/includes/header.php';
?>

<div class="container" style="max-width:560px;padding-top:1.5rem;padding-bottom:2rem">

    <?php if (!empty($_SESSION['must_change_password']) || isset($_GET['force_pw'])): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>Wachtwoord wijzigen verplicht.</strong>
            Je kunt de app pas gebruiken nadat je een nieuw wachtwoord hebt ingesteld.
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Change password ───────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-key-fill me-2"></i>Wachtwoord wijzigen</div>
        <div class="card-body">
            <div id="pw-alert" class="alert py-2 mb-3" style="display:none"></div>
            <div class="mb-3">
                <label class="form-label">Huidig wachtwoord</label>
                <input type="password" id="pw-current" class="form-control" autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label">Nieuw wachtwoord</label>
                <input type="password" id="pw-new1" class="form-control" autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label">Nieuw wachtwoord (herhalen)</label>
                <input type="password" id="pw-new2" class="form-control" autocomplete="new-password">
            </div>
            <button class="btn btn-danger" onclick="changePassword()">
                <i class="bi bi-check-lg"></i> Opslaan
            </button>
        </div>
    </div>

    <!-- ── Opslag (fair-use) ─────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-hdd-fill me-2"></i>Opslag (fair-use)</div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                Het fair-use beleid geeft je <strong><span id="st-quota">500 MB</span></strong> opslag
                voor de bands die je leidt (nummers, setlijsten en PDF-bladmuziek samen).
                Lid of kijker zijn van andermans band kost jou geen ruimte.
            </p>
            <div class="progress mb-2" style="height:1.25rem" role="progressbar">
                <div id="st-bar" class="progress-bar bg-danger" style="width:0%">0%</div>
            </div>
            <div class="d-flex justify-content-between small">
                <span class="text-muted"><span id="st-used">—</span> gebruikt</span>
                <span class="text-muted"><span id="st-remaining">—</span> over</span>
            </div>
            <div id="st-detail" class="text-muted small mt-2"></div>
            <div id="st-over" class="alert alert-warning py-2 small mt-3 mb-0" style="display:none">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Je hebt de opslaglimiet bereikt. Verwijder bladmuziek of nummers om weer ruimte vrij te maken;
                tot die tijd kun je geen nieuwe PDF's uploaden.
            </div>
        </div>
    </div>

    <!-- ── Herstelcode (kluis) ───────────────────────────────────────────── -->
    <div class="card mb-4" id="recovery-card" style="display:none">
        <div class="card-header"><i class="bi bi-shield-lock-fill me-2"></i>Herstelcode versleuteling</div>
        <div class="card-body">
            <div id="rec-alert" class="alert py-2 mb-3" style="display:none"></div>
            <p class="text-muted small mb-2">
                De nummers, setlijsten en notities van een versleutelde band worden zo opgeslagen
                dat <strong>alleen de bandleden ze kunnen lezen</strong> — niet de beheerder en niet
                wie de server beheert. Je opent die inhoud met je wachtwoord.
            </p>
            <p class="text-muted small mb-2">
                <strong>Waarom een herstelcode?</strong> Vergeet je je wachtwoord, dan kan
                <strong>niemand</strong> dat voor je terugzetten — anders zou diegene ook bij de
                inhoud kunnen, en dat is precies wat we voorkomen. Een beheerder kan je een nieuw
                inlogwachtwoord geven, maar daarmee gaat de versleutelde inhoud nog niet open.
                De herstelcode is je eigen reservesleutel om er tóch weer bij te komen.
            </p>
            <p class="text-muted small mb-2">
                Het gaat hier om <strong>twee dingen tegelijk</strong>: jouw eigen toegang, én de
                gedeelde data van de band. Raken alle bandleden hun wachtwoord én herstelcode kwijt,
                dan is de inhoud van de band definitief weg — niet meer terug te halen. Daarom heeft
                iedereen het liefst een eigen herstelcode.
            </p>
            <p class="text-muted small mb-3">
                Eén herstelcode geldt voor al je bands. Bewaar 'm op een veilige plek, bijvoorbeeld
                in een wachtwoordmanager of op papier. Ben je 'm kwijt? Genereer hieronder een nieuwe —
                de oude vervalt dan.
            </p>

            <div id="rec-locked" class="alert alert-warning py-2 small mb-0" style="display:none">
                <i class="bi bi-lock me-1"></i>
                Je kluis is niet ontgrendeld. Log opnieuw in met je wachtwoord om een nieuwe herstelcode te kunnen maken.
            </div>

            <button class="btn btn-outline-warning" id="rec-gen-btn" onclick="regenerateRecovery()" style="display:none">
                <i class="bi bi-arrow-repeat"></i> Nieuwe herstelcode genereren
            </button>

            <!-- Eenmalige weergave van de nieuwe code -->
            <div id="rec-show" style="display:none">
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Bewaar deze code op een veilige plek (bv. wachtwoordmanager). Hij wordt <strong>maar één keer</strong> getoond
                    en vervangt je vorige herstelcode.
                </div>
                <div id="rec-code" class="font-monospace fs-5 text-center py-3"
                     style="background:#1e1e1e;border:1px solid #2e2e2e;border-radius:6px;letter-spacing:0.1em"></div>
                <button class="btn btn-outline-secondary btn-sm mt-2" onclick="copyRecovery()">
                    <i class="bi bi-clipboard"></i> Kopiëren
                </button>
            </div>
        </div>
    </div>

    <!-- ── 2FA ───────────────────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-shield-lock-fill me-2"></i>Twee-factor authenticatie (2FA)</span>
            <?php if ($totpEnabled): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Actief</span>
            <?php else: ?>
            <span class="badge bg-secondary">Uitgeschakeld</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div id="tfa-alert" class="alert py-2 mb-3" style="display:none"></div>

            <?php if ($totpEnabled): ?>
            <!-- ── Disable / backup codes (2FA actief) ───────────────── -->
            <p class="text-muted small mb-2">
                2FA is ingeschakeld. Nog <strong><?= $backupCodesRemaining ?></strong> ongebruikte backup-code(s).
                <?php if ($backupCodesRemaining <= 2): ?>
                <br><span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Genereer nieuwe codes voordat ze allemaal op zijn.</span>
                <?php endif; ?>
            </p>
            <div id="tfa-disable-form" class="mb-3">
                <label class="form-label">Verificatiecode (van authenticator-app)</label>
                <input type="text" id="tfa-disable-code" class="form-control mb-2"
                       maxlength="6" inputmode="numeric" placeholder="000000"
                       style="letter-spacing:0.3em;max-width:160px">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-secondary" onclick="regenerateBackupCodes()">
                        <i class="bi bi-arrow-repeat"></i> Nieuwe backup-codes
                    </button>
                    <button class="btn btn-outline-danger" onclick="disable2fa()">
                        <i class="bi bi-shield-x"></i> 2FA uitschakelen
                    </button>
                </div>
            </div>

            <?php else: ?>
            <!-- ── Enable 2FA ──────────────────────────────────────────── -->
            <p class="text-muted small mb-3">
                Gebruik een authenticator-app zoals <strong>Google Authenticator</strong>,
                <strong>Authy</strong> of <strong>Microsoft Authenticator</strong>.
                Scan de QR-code of voer de sleutel handmatig in.
            </p>

            <div id="tfa-start-wrap">
                <button class="btn btn-outline-warning" onclick="start2fa()">
                    <i class="bi bi-shield-plus"></i> 2FA inschakelen
                </button>
            </div>

            <div id="tfa-setup-wrap" style="display:none">
                <p class="text-muted small mb-2">
                    <strong>Op je computer</strong>: scan de QR-code met je authenticator-app.
                    <strong>Op je telefoon</strong>: tik op de onderstaande link om je app direct te openen,
                    of voer de sleutel handmatig in.
                </p>

                <div class="mb-3 text-center">
                    <div id="tfa-qr" class="d-inline-block p-2 bg-white rounded"
                         style="line-height:0" aria-label="QR-code voor 2FA"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small">Authenticator-link (tik op je mobiel)</label>
                    <a id="tfa-otpauth-link" class="btn btn-outline-warning w-100 text-truncate" href="#">
                        <i class="bi bi-shield-lock me-1"></i> Open in authenticator-app
                    </a>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small">Handmatige sleutel (kopieer naar je app)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="tfa-secret-display" class="form-control font-monospace"
                               readonly style="letter-spacing:0.1em">
                        <button class="btn btn-outline-secondary" type="button" onclick="copySecret()" title="Kopiëren">
                            <i class="bi bi-clipboard" id="tfa-copy-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Verificatiecode (ter bevestiging)</label>
                    <input type="text" id="tfa-confirm-code" class="form-control"
                           maxlength="6" inputmode="numeric" placeholder="000000"
                           style="letter-spacing:0.3em;max-width:160px">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" onclick="confirm2fa()">
                        <i class="bi bi-shield-check"></i> Bevestigen &amp; activeren
                    </button>
                    <button class="btn btn-secondary" onclick="cancel2fa()">Annuleren</button>
                </div>
            </div>

            <!-- Eenmalige weergave: backup-codes na succesvolle activering (L3) -->
            <div id="tfa-backup-wrap" style="display:none">
                <div class="alert alert-success mb-3">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <strong>2FA is geactiveerd.</strong> Bewaar onderstaande backup-codes op een veilige plek
                    (bv. wachtwoordmanager). Elke code is eenmalig bruikbaar als je geen toegang hebt tot je authenticator.
                </div>
                <ul id="tfa-backup-list" class="font-monospace list-unstyled mb-3"
                    style="background:#1e1e1e;border:1px solid #2e2e2e;border-radius:6px;padding:12px;letter-spacing:0.05em"></ul>
                <button class="btn btn-outline-secondary btn-sm" onclick="copyBackupCodes()">
                    <i class="bi bi-clipboard"></i> Alle codes kopiëren
                </button>
                <button class="btn btn-primary btn-sm" onclick="location.reload()">
                    <i class="bi bi-check"></i> Klaar
                </button>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php
$qrVer = filemtime(APP_ROOT . '/htdocs/live-click/assets/vendor/qrcode/qrcode.min.js');
$extraScripts = '<script src="assets/vendor/qrcode/qrcode.min.js?v=' . $qrVer . '"></script>
<script>
// ── Opslag (fair-use) ─────────────────────────────────────────────────────
function fmtBytes(b) {
    b = Number(b) || 0;
    if (b < 1024) return b + " B";
    var kb = b / 1024;
    if (kb < 1024) return kb.toFixed(kb < 10 ? 1 : 0) + " KB";
    var mb = kb / 1024;
    if (mb < 1024) return mb.toFixed(mb < 10 ? 1 : 0) + " MB";
    return (mb / 1024).toFixed(2) + " GB";
}

$(function() {
    $.get("api/storage.php").done(function(r) {
        if (!r || !r.ok) return;
        var u = r.usage;
        $("#st-quota").text(fmtBytes(u.quota_bytes));
        $("#st-used").text(fmtBytes(u.used_bytes));
        $("#st-remaining").text(fmtBytes(u.remaining_bytes));
        var bar = $("#st-bar");
        bar.css("width", u.percent + "%").text(u.percent + "%");
        // Kleur: groen < 75%, geel < 90%, rood daarboven.
        bar.removeClass("bg-danger bg-warning bg-success")
           .addClass(u.percent >= 90 ? "bg-danger" : (u.percent >= 75 ? "bg-warning" : "bg-success"));
        $("#st-detail").html("Bladmuziek (PDF): " + fmtBytes(u.pdf_bytes)
            + " &middot; Nummers &amp; setlijsten: " + fmtBytes(u.db_bytes));
        $("#st-over").toggle(!!u.over);
    });
});

// ── Herstelcode (kluis) ───────────────────────────────────────────────────
// Toon de kaart alleen als de gebruiker een sleutelpaar heeft. De genereer-knop
// werkt alleen als de kluis ontgrendeld is (privésleutel in de sessie).
$(function() {
    if (!window.LGKeys) return;
    $.get("api/keys.php").done(function(st) {
        if (!st || !st.has_keys) return;            // geen versleuteling in gebruik
        $("#recovery-card").show();
        if (LGKeys.keyState() === "unlocked") {
            $("#rec-gen-btn").show();
            $("#rec-locked").hide();
        } else {
            $("#rec-gen-btn").hide();
            $("#rec-locked").show();
        }
    });
});

function regenerateRecovery() {
    if (!window.LGKeys) return;
    if (!confirm("Een nieuwe herstelcode maken? Je vorige herstelcode vervalt daarmee.")) return;
    var btn = $("#rec-gen-btn").prop("disabled", true);
    LGKeys.setupRecovery().then(function(code) {
        $("#rec-code").text(code);
        window._recCode = code;
        $("#rec-show").show();
        btn.hide();
    }).catch(function(e) {
        var el = $("#rec-alert");
        el.removeClass("alert-success").addClass("alert-danger")
          .html(\'<i class="bi bi-exclamation-triangle me-1"></i>\' + escHtml(e.message || "Genereren mislukt.")).show();
        btn.prop("disabled", false);
    });
}

function copyRecovery() {
    navigator.clipboard.writeText(window._recCode || "").catch(function(){});
}

// ── Change password ──────────────────────────────────────────────────────
function changePassword() {
    var current = $("#pw-current").val();
    var new1    = $("#pw-new1").val();
    var new2    = $("#pw-new2").val();

    // E2EE: als er een sleutelpaar is, de privésleutel onder het NIEUWE
    // wachtwoord herverpakken en meesturen (anders blijft de kluis op slot).
    var prep = (window.LGKeys && new1 && new1 === new2)
        ? LGKeys.rewrapForNewPassword(new1).catch(function () { return null; })
        : Promise.resolve(null);

    prep.then(function (keyMaterial) {
    var payload = {action:"change_password", current:current, new1:new1, new2:new2};
    if (keyMaterial) { payload.kdf_salt = keyMaterial.kdf_salt; payload.enc_privkey = keyMaterial.enc_privkey; }
    $.ajax({
        url: "api/profile.php", type: "POST",
        contentType: "application/json",
        data: JSON.stringify(payload),
        dataType: "json",
        success: function(r) {
            var el = $("#pw-alert");
            if (r.ok) {
                el.removeClass("alert-danger").addClass("alert-success")
                  .html(\'<i class="bi bi-check-circle me-1"></i>Wachtwoord gewijzigd.\').show();
                $("#pw-current, #pw-new1, #pw-new2").val("");
                // If redirected here because of forced password change, go to dashboard
                if (window.location.search.indexOf("force_pw") !== -1) {
                    setTimeout(function() { window.location.href = "dashboard.php"; }, 1200);
                }
            } else {
                el.removeClass("alert-success").addClass("alert-danger")
                  .html(\'<i class="bi bi-exclamation-triangle me-1"></i>\' + escHtml(r.error || "Fout")).show();
            }
        }
    });
    });
}

// ── 2FA setup ────────────────────────────────────────────────────────────
function start2fa() {
    $.ajax({
        url: "api/profile.php", type: "POST",
        contentType: "application/json",
        data: JSON.stringify({action:"enable_2fa_start"}),
        dataType: "json",
        success: function(r) {
            if (!r.ok) {
                var el = $("#tfa-alert");
                el.removeClass("alert-success").addClass("alert-danger")
                  .html(\'<i class="bi bi-exclamation-triangle me-1"></i>\' + escHtml(r.error || "Fout")).show();
                return;
            }
            $("#tfa-secret-display").val(r.secret);
            $("#tfa-otpauth-link").attr("href", r.otpauth_uri);
            $("#tfa-confirm-code").val("");
            renderTfaQr(r.otpauth_uri);
            $("#tfa-start-wrap").hide();
            $("#tfa-setup-wrap").show();
        }
    });
}

// Render de otpauth-URI als QR-code (client-side; de secret verlaat de server
// niet via een externe dienst). Faalt stil terug op de link/sleutel als de
// QR-library niet geladen is.
function renderTfaQr(uri) {
    var el = document.getElementById("tfa-qr");
    if (!el) return;
    el.innerHTML = "";
    if (typeof qrcode !== "function" || !uri) { el.style.display = "none"; return; }
    try {
        var qr = qrcode(0, "M");   // typeNumber 0 = auto, error-correctie M
        qr.addData(uri);
        qr.make();
        el.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 0, scalable: true });
        var svg = el.querySelector("svg");
        if (svg) { svg.style.width = "200px"; svg.style.height = "200px"; }
        el.style.display = "";
    } catch (e) {
        el.style.display = "none";   // val terug op handmatige sleutel/link
    }
}

function cancel2fa() {
    $("#tfa-setup-wrap").hide();
    $("#tfa-start-wrap").show();
    $("#tfa-alert").hide();
}

function copySecret() {
    var s = $("#tfa-secret-display").val();
    navigator.clipboard.writeText(s).then(function() {
        $("#tfa-copy-icon").removeClass("bi-clipboard").addClass("bi-clipboard-check");
        setTimeout(function() { $("#tfa-copy-icon").removeClass("bi-clipboard-check").addClass("bi-clipboard"); }, 1500);
    });
}

function confirm2fa() {
    var code = $("#tfa-confirm-code").val().replace(/\\D/g, "");
    $.ajax({
        url: "api/profile.php", type: "POST",
        contentType: "application/json",
        data: JSON.stringify({action:"enable_2fa_confirm", code:code}),
        dataType: "json",
        success: function(r) {
            if (r.ok) {
                // Toon backup-codes eenmalig — gebruiker moet ze zelf opslaan
                $("#tfa-setup-wrap").hide();
                renderBackupCodes(r.backup_codes || []);
            } else {
                var el = $("#tfa-alert");
                el.removeClass("alert-success").addClass("alert-danger")
                  .html(\'<i class="bi bi-exclamation-triangle me-1"></i>\' + escHtml(r.error || "Fout")).show();
            }
        }
    });
}

function renderBackupCodes(codes) {
    var $list = $("#tfa-backup-list").empty();
    codes.forEach(function(c) {
        $list.append(\'<li class="py-1">\' + escHtml(c) + \'</li>\');
    });
    window._tfaBackupCodes = codes;
    $("#tfa-backup-wrap").show();
}

function copyBackupCodes() {
    var text = (window._tfaBackupCodes || []).join("\\n");
    navigator.clipboard.writeText(text).then(function() {
        // Geen visuele feedback nodig — codes zijn al getoond
    }).catch(function() {});
}

// ── 2FA backup codes regenereren ─────────────────────────────────────────
function regenerateBackupCodes() {
    var code = $("#tfa-disable-code").val().replace(/\\D/g, "");
    if (code.length !== 6) {
        alert("Vul eerst je 6-cijferige verificatiecode in.");
        return;
    }
    if (!confirm("Bestaande backup-codes worden ongeldig. Doorgaan?")) return;
    $.ajax({
        url: "api/profile.php", type: "POST",
        contentType: "application/json",
        data: JSON.stringify({action:"regenerate_backup_codes", code:code}),
        dataType: "json",
        success: function(r) {
            if (r.ok) {
                $("#tfa-disable-form").hide();
                renderBackupCodes(r.backup_codes || []);
            } else {
                var el = $("#tfa-alert");
                el.removeClass("alert-success").addClass("alert-danger")
                  .html(\'<i class="bi bi-exclamation-triangle me-1"></i>\' + escHtml(r.error || "Fout")).show();
            }
        }
    });
}

// ── 2FA disable ───────────────────────────────────────────────────────────
function disable2fa() {
    var code = $("#tfa-disable-code").val().replace(/\\D/g, "");
    $.ajax({
        url: "api/profile.php", type: "POST",
        contentType: "application/json",
        data: JSON.stringify({action:"disable_2fa", code:code}),
        dataType: "json",
        success: function(r) {
            if (r.ok) {
                location.reload();
            } else {
                var el = $("#tfa-alert");
                el.removeClass("alert-success").addClass("alert-danger")
                  .html(\'<i class="bi bi-exclamation-triangle me-1"></i>\' + escHtml(r.error || "Fout")).show();
            }
        }
    });
}
</script>';
require APP_ROOT . '/includes/footer.php';
?>
