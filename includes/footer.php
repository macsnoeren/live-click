
</div><!-- /page-content -->

<!-- Vendor-bestanden lokaal opgeslagen — app werkt ook zonder internet -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script>
// CSRF: stuur token mee bij elke niet-GET jQuery ajax-call.
// ajaxSend (event) ipv ajaxSetup.beforeSend — wordt niet overschreven door call-specifieke beforeSend.
(function() {
    var meta  = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';
    if (!token || !window.jQuery) return;
    window.LG_CSRF = token; // ook beschikbaar voor handmatige fetch() calls
    jQuery(document).ajaxSend(function(event, xhr, settings) {
        var m = (settings.type || 'GET').toUpperCase();
        if (m !== 'GET' && m !== 'HEAD' && m !== 'OPTIONS') {
            xhr.setRequestHeader('X-CSRF-Token', token);
        }
    });
})();
</script>
<script src="assets/js/clicktrack.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/clicktrack.js') ?>"></script>
<script src="assets/js/app.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/app.js') ?>"></script>
<!-- E2EE: cryptomodule + sleutel-bootstrap (zie PRIVACY.md). Ontgrendelt de
     privésleutel zodra die nodig is; faalt stil als versleuteling (nog) niet
     in gebruik is. -->
<script src="assets/js/crypto.js?v=<?= filemtime(APP_ROOT . '/htdocs/live-click/assets/js/crypto.js') ?>"></script>
<?php
// E2EE: is de huidige gebruiker lid van minstens één versleutelde band? Zo ja,
// en is de kluis vergrendeld (bv. na remember-me auto-login), dan tonen we een
// ontgrendel-prompt. We bepalen dit serverside om de prompt alleen relevant te tonen.
$lgInEncryptedBand = false;
if (function_exists('currentUser')) {
    $lgU = currentUser();
    if ($lgU) {
        try {
            $lgStmt = getDB()->prepare(
                'SELECT 1 FROM band_members bm JOIN bands b ON b.id = bm.band_id
                  WHERE bm.user_id = ? AND b.is_encrypted = 1 LIMIT 1'
            );
            $lgStmt->execute([(int)$lgU['id']]);
            $lgInEncryptedBand = (bool)$lgStmt->fetchColumn();
        } catch (Exception $e) { /* kolom bestaat nog niet → geen prompt */ }
    }
}
?>
<?php if ($lgInEncryptedBand): ?>
<!-- E2EE: ontgrendel-prompt (verschijnt alleen als de kluis op slot zit) -->
<div class="modal fade" id="lgUnlockModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Kluis ontgrendelen</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Je bent lid van een versleutelde band. Voer je wachtwoord in om de inhoud te ontgrendelen op dit apparaat.</p>
                <div id="lg-unlock-alert" class="alert alert-danger py-2 small" style="display:none"></div>
                <input type="password" id="lg-unlock-pw" class="form-control mb-2" placeholder="Wachtwoord" autocomplete="current-password">
                <div class="text-end">
                    <a href="#" class="small text-muted" onclick="lgShowRecover();return false;">Wachtwoord vergeten? Herstelcode gebruiken</a>
                </div>

                <!-- Herstel met code -->
                <div id="lg-recover-box" style="display:none" class="mt-3 pt-3 border-top border-secondary">
                    <p class="text-muted small">Voer je herstelcode in en kies een nieuw wachtwoord. Dit stelt in één keer je nieuwe <strong>login-wachtwoord</strong> in én opent je kluis.</p>
                    <input type="text" id="lg-recover-code" class="form-control mb-2 font-monospace" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX">
                    <input type="password" id="lg-recover-pw" class="form-control mb-2" placeholder="Nieuw wachtwoord (min. 12 tekens)" autocomplete="new-password">
                    <input type="password" id="lg-recover-pw2" class="form-control mb-2" placeholder="Nieuw wachtwoord herhalen" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Later</button>
                <button type="button" class="btn btn-danger" id="lg-unlock-btn" onclick="lgDoUnlock()">
                    <i class="bi bi-unlock"></i> Ontgrendelen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- E2EE: herinnering om een herstelcode aan te maken. Verschijnt alleen als de
     kluis ontgrendeld is én er nog geen herstelcode is ingesteld (per gebruiker).
     Zonder herstelcode kan niemand — ook een admin niet — de kluis openen na een
     vergeten wachtwoord. -->
<div class="modal fade" id="lgRecoveryNudge" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-shield-exclamation"></i> Maak een herstelcode aan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Sluiten"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-0">
                    Je bent lid van een <strong>versleutelde band</strong>, maar je hebt zelf nog geen herstelcode.
                    Vergeet je je wachtwoord, dan kan <strong>niemand</strong> — ook een beheerder niet — je toegang
                    tot de versleutelde inhoud herstellen. Met een herstelcode open je je kluis altijd weer.
                </p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Later</button>
                <a href="profile.php#recovery-card" class="btn btn-warning">
                    <i class="bi bi-shield-lock"></i> Herstelcode aanmaken
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
(function () {
    var inEncBand = <?= $lgInEncryptedBand ? 'true' : 'false' ?>;
    if (!window.LGKeys) return;

    // Eénmalig per sessie nudgen om een herstelcode in te stellen. We tonen het
    // alleen als de kluis ontgrendeld is (dan kán de gebruiker er een maken) en
    // er nog geen herstelcode bestaat (api/keys.php → has_recovery).
    function maybeNudgeRecovery() {
        try { if (sessionStorage.getItem('lgRecoveryNudgeDismissed')) return; } catch (e) {}
        if (!window.jQuery) return;
        jQuery.get('api/keys.php').done(function (st) {
            if (!st || !st.ok || !st.has_keys || st.has_recovery) return;
            var el = document.getElementById('lgRecoveryNudge');
            if (window.bootstrap && el) {
                try {
                    new bootstrap.Modal(el).show();
                    el.addEventListener('hidden.bs.modal', function () {
                        try { sessionStorage.setItem('lgRecoveryNudgeDismissed', '1'); } catch (e) {}
                    }, { once: true });
                } catch (e) {}
            }
        });
    }

    LGKeys.bootstrap().then(function (state) {
        if (!inEncBand) return;
        if (state === 'unsupported') return; // geen WebCrypto → niets te ontgrendelen
        if (state === 'unlocked') { maybeNudgeRecovery(); return; }
        // Kluis zit op slot terwijl er versleutelde bands zijn → prompt tonen.
        if (window.bootstrap && document.getElementById('lgUnlockModal')) {
            try { new bootstrap.Modal('#lgUnlockModal').show(); } catch (e) {}
        }
    }).catch(function () {});
})();

function lgShowRecover() {
    document.getElementById('lg-recover-box').style.display = '';
    var btn = document.getElementById('lg-unlock-btn');
    btn.innerHTML = '<i class="bi bi-key"></i> Herstellen';
    btn.setAttribute('data-mode', 'recover');
}

function lgDoUnlock() {
    var alertEl = document.getElementById('lg-unlock-alert');
    var btn = document.getElementById('lg-unlock-btn');
    var mode = btn.getAttribute('data-mode') || 'unlock';
    alertEl.style.display = 'none';
    btn.disabled = true;

    function fail(msg) { alertEl.textContent = msg; alertEl.style.display = ''; btn.disabled = false; }

    if (mode === 'recover') {
        var code = document.getElementById('lg-recover-code').value;
        var npw  = document.getElementById('lg-recover-pw').value;
        var npw2 = document.getElementById('lg-recover-pw2').value;
        if (!code || npw.length < 12) { fail('Vul de herstelcode in en een nieuw wachtwoord van minstens 12 tekens.'); return; }
        if (npw !== npw2) { fail('De nieuwe wachtwoorden komen niet overeen.'); return; }
        LGKeys.recoverWithCode(code, npw).then(function () {
            location.reload();
        }).catch(function (e) { fail(e.message || 'Herstellen mislukt.'); });
        return;
    }

    var pw = document.getElementById('lg-unlock-pw').value;
    if (!pw) { fail('Voer je wachtwoord in.'); return; }
    LGKeys.unlock(pw).then(function (state) {
        if (state === 'unlocked') { location.reload(); }
        else { fail('Onjuist wachtwoord, of de kluis kon niet worden geopend.'); }
    }).catch(function () { fail('Ontgrendelen mislukt.'); });
}
</script>
<?= $extraScripts ?? '' ?>
<script>
(function() {
    var LS_MAX = 5 * 1024 * 1024; // 5 MB

    function updateLsUsage() {
        var el = document.getElementById('ls-usage');
        if (!el) return;
        try {
            var bytes = 0;
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                if (!k) continue;
                bytes += (k.length + (localStorage.getItem(k) || '').length) * 2;
            }
            var kb   = bytes / 1024;
            var pct  = Math.min(100, Math.round(bytes / LS_MAX * 100));
            var txt  = kb < 1 ? '<1 KB' : Math.round(kb) + ' KB';
            var tip  = 'Lokale cache: ' + txt + ' van 5 MB (' + pct + '%)';

            // Lazy-build inner structure once
            var fill = el.querySelector('.ls-usage-fill');
            var lbl  = el.querySelector('.ls-usage-label');
            if (!fill) {
                el.innerHTML =
                    '<span class="ls-usage-bar"><span class="ls-usage-fill"></span></span>' +
                    '<span class="ls-usage-label"></span>';
                fill = el.querySelector('.ls-usage-fill');
                lbl  = el.querySelector('.ls-usage-label');
            }

            fill.style.width = pct + '%';
            lbl.textContent  = txt;
            el.title         = tip;
            el.className     = 'ls-usage' +
                (pct >= 90 ? ' ls-full' : pct >= 60 ? ' ls-warn' : '');
        } catch (e) {
            el.innerHTML = ''; // private browsing of quota error
        }
    }
    updateLsUsage();
    window.addEventListener('storage', updateLsUsage);
    window.updateLsUsage = updateLsUsage;
})();
</script>
</body>
</html>
