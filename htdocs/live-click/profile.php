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
                    <strong>Op je telefoon</strong>: tik op de onderstaande link om je authenticator-app direct te openen,
                    of voer de sleutel handmatig in.
                </p>

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
$extraScripts = '<script>
// ── Change password ──────────────────────────────────────────────────────
function changePassword() {
    var current = $("#pw-current").val();
    var new1    = $("#pw-new1").val();
    var new2    = $("#pw-new2").val();
    $.ajax({
        url: "api/profile.php", type: "POST",
        contentType: "application/json",
        data: JSON.stringify({action:"change_password", current:current, new1:new1, new2:new2}),
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
            $("#tfa-start-wrap").hide();
            $("#tfa-setup-wrap").show();
        }
    });
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
