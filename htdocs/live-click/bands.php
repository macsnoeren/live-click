<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Bands — LiveGig';
require APP_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-people-fill"></i> Mijn bands</h4>
        <button class="btn btn-danger btn-sm" onclick="openAddBand()">
            <i class="bi bi-plus-lg"></i> Band aanmaken
        </button>
    </div>

    <div id="bands-container" class="row g-3">
        <div class="col-12 text-muted">Laden...</div>
    </div>
</div>

<!-- Band Modal -->
<div class="modal fade" id="bandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="bandModalTitle">Band aanmaken</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="band-id">
                <div class="mb-3">
                    <label class="form-label">Bandnaam <span class="text-danger">*</span></label>
                    <input type="text" id="band-name" class="form-control" placeholder="Bijv. The Rolling Stones">
                </div>
                <div class="mb-3">
                    <label class="form-label">Beschrijving</label>
                    <textarea id="band-description" class="form-control" rows="2"></textarea>
                </div>

                <!-- Versleuteling: standaard aan bij het aanmaken van een band -->
                <div id="band-encrypt-section" class="mb-1" style="display:none">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="band-encrypt" checked>
                        <label class="form-check-label" for="band-encrypt">
                            <i class="bi bi-shield-lock text-success me-1"></i>
                            <strong>Band versleutelen</strong> (aanbevolen)
                        </label>
                    </div>
                    <p class="text-muted small mt-2 mb-0">
                        Alle inhoud wordt end-to-end versleuteld; alleen bandleden kunnen het lezen.
                        Je krijgt eenmalig een herstelcode.
                        <a href="privacy.php" target="_blank" rel="noopener">Lees wat dit inhoudt</a>.
                        Zet je dit uit, dan kan de serverbeheerder de inhoud inzien.
                    </p>
                    <div id="band-encrypt-unsupported" class="alert alert-warning py-2 small mt-2 mb-0" style="display:none">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Versleuteling is niet beschikbaar in deze browser (vereist HTTPS). De band wordt onversleuteld aangemaakt.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="saveBand()">
                    <i class="bi bi-check-lg"></i> Opslaan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete band confirm (admin only) -->
<div class="modal fade" id="deleteBandModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Band verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="delete-band-name"></strong> wilt verwijderen?
                <div class="small text-warning mt-2">
                    <i class="bi bi-exclamation-triangle"></i> Alle nummers en setlists van deze band worden ook verwijderd.
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="confirmDeleteBand()">Verwijderen</button>
            </div>
        </div>
    </div>
</div>

<!-- Leave band confirm -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Band verlaten</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="leave-band-name"></strong> wilt verlaten?
                <div id="leave-leader-warning" class="alert alert-warning py-2 mt-2 mb-0 small" style="display:none">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Je bent de bandleider. Het volgende lid wordt automatisch de nieuwe leider.
                    Is er niemand anders, dan wordt de band verwijderd.
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-warning" onclick="confirmLeave()">Verlaten</button>
            </div>
        </div>
    </div>
</div>

<!-- Kluis aanzetten: bevestiging -->
<div class="modal fade" id="vaultEnableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Band versleutelen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Je staat op het punt de inhoud van <strong id="vault-band-name"></strong> end-to-end te versleutelen.</p>
                <ul class="small text-muted">
                    <li>Alleen bandleden kunnen de inhoud daarna nog lezen — de server niet.</li>
                    <li>Leden zonder sleutel (nog nooit ingelogd sinds de update) krijgen later automatisch toegang.</li>
                    <li>Je krijgt eenmalig een <strong>herstelcode</strong>; bewaar die goed.</li>
                </ul>
                <div id="vault-enable-alert" class="alert alert-danger py-2 small" style="display:none"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button type="button" class="btn btn-danger" id="vault-enable-confirm" onclick="confirmEnableVault()">
                    <i class="bi bi-shield-lock"></i> Versleutelen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Herstelcode tonen (eenmalig) -->
<div class="modal fade" id="recoveryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-key"></i> Herstelcode</h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Bewaar deze code op een veilige plek (bv. wachtwoordmanager). Met deze code kun je
                    je kluis openen als je je wachtwoord vergeet. Hij wordt <strong>maar één keer</strong> getoond.
                </div>
                <div id="recovery-code" class="font-monospace fs-5 text-center py-3"
                     style="background:#1e1e1e;border:1px solid #2e2e2e;border-radius:6px;letter-spacing:0.1em"></div>
                <button class="btn btn-outline-secondary btn-sm mt-2" onclick="copyRecoveryCode()">
                    <i class="bi bi-clipboard"></i> Kopiëren
                </button>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="loadBands()">
                    <i class="bi bi-check"></i> Ik heb de code bewaard
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Op deze pagina telt de admin NIET als beheerder: bandbeheer (versleutelen,
// leden, rollen, deellinks) is voorbehouden aan bandleiders. De admin beheert
// bands alleen via het Admin-paneel (verwijderen). _isAdmin blijft daarom false;
// is de admin zelf lid van een band, dan gelden gewoon zijn bandrol-rechten.
$extraScripts = '<script>
var _allBands  = [];
var _allUsers  = [];
var _isAdmin   = false;
var _myUserId  = ' . (int)$user['id'] . ';
var _deleteBandId = null;
var _leaveBandId  = null;
var _leaveBandName = "";

$(function() {
    loadBands();
});

function loadBands() {
    $.get("api/bands.php", function(data) {
        _allBands = data.bands || [];
        renderBands(_allBands);
        grantKeysToNewMembers();
    });
}

// E2EE: deel als leider stil de BDK uit aan leden die nog geen sleutel hebben,
// en ververs daarna de kluisstatus-badges per lid.
function grantKeysToNewMembers() {
    if (!window.LGVault || !window.LGKeys || LGKeys.keyState() !== "unlocked") return;
    _allBands.forEach(function(b) {
        if (b.is_encrypted != 1) return;
        var amLeader = (b.members || []).some(function(m){ return m.id == _myUserId && m.role === "leader"; });
        if (!amLeader && !_isAdmin) return;
        // Eerst proberen ontbrekende sleutels uit te delen, dan de status tonen.
        LGVault.grantMissing(b.id).catch(function(){}).then(function() {
            refreshVaultStatus(b.id);
        });
    });
}

// Toont per lid of die toegang tot de kluis heeft:
//   🔑 groen  = heeft sleutel
//   ⏳ geel   = wacht op sleutel (nog nooit ingelogd sinds versleuteling)
function refreshVaultStatus(bandId) {
    if (!window.LGVault) return;
    LGVault.status(bandId).then(function(st) {
        if (!st.ok || !st.is_encrypted || !st.can_manage || !st.members) return;
        st.members.forEach(function(m) {
            var el = document.querySelector(
                \'.vault-key-status[data-band-id="\' + bandId + \'"][data-user-id="\' + m.user_id + \'"]\');
            if (!el) return;
            if (m.has_key) {
                el.innerHTML = \' <i class="bi bi-key-fill text-success" title="Heeft toegang tot de kluis"></i>\';
            } else if (!m.pubkey) {
                el.innerHTML = \' <i class="bi bi-hourglass-split text-warning" title="Wacht op sleutel — dit lid moet eerst één keer inloggen"></i>\';
            } else {
                el.innerHTML = \' <i class="bi bi-hourglass-split text-warning" title="Sleutel wordt toegekend zodra een leider de bandpagina opent"></i>\';
            }
        });
    }).catch(function(){});
}


function isLeaderOf(b) {
    return (b.members || []).some(function(m) { return m.id == _myUserId && m.role === "leader"; });
}

// ---- E2EE: band versleutelen (zie PRIVACY.md) ----
var _vaultBandId = null;

function askEnableVault(bandId) {
    if (!window.LGVault || !window.LGKeys) {
        alert("Versleuteling is niet beschikbaar in deze browser (vereist HTTPS).");
        return;
    }
    if (LGKeys.keyState() !== "unlocked") {
        alert("Je kluis is nog niet ontgrendeld. Log opnieuw in met je wachtwoord en probeer het daarna nogmaals.");
        return;
    }
    _vaultBandId = bandId;
    var b = _allBands.find(function(x) { return x.id == bandId; });
    $("#vault-band-name").text(b ? b.name : "deze band");
    $("#vault-enable-alert").hide();
    $("#vault-enable-confirm").prop("disabled", false);
    new bootstrap.Modal("#vaultEnableModal").show();
}

function confirmEnableVault() {
    var bandId = _vaultBandId;
    if (!bandId) return;
    $("#vault-enable-confirm").prop("disabled", true);

    LGVault.enableVault(bandId).then(function(res) {
        // Zorg dat er een herstelcode is; zo niet, genereer er één en toon die.
        return fetch("api/keys.php", {credentials:"same-origin", headers:{Accept:"application/json"}})
            .then(function(r){ return r.json(); })
            .then(function(st) {
                bootstrap.Modal.getInstance("#vaultEnableModal").hide();
                if (res.without_keys && res.without_keys.length) {
                    // Niet blokkerend — informeren is genoeg
                    console.info("Leden zonder sleutel (krijgen later toegang):", res.without_keys);
                }
                if (st.ok && !st.has_recovery) {
                    return LGKeys.setupRecovery().then(function(code) { showRecoveryCode(code); });
                }
                // Al een herstelcode → bevestigen dat die ook voor deze band geldt.
                showVaultDone();
                loadBands();
            });
    }).catch(function(e) {
        $("#vault-enable-alert").text(e.message || "Versleutelen mislukt.").show();
        $("#vault-enable-confirm").prop("disabled", false);
    });
}

// Korte bevestiging na het versleutelen van een band wanneer er al een
// herstelcode bestaat (die geldt voor al je bands — er is er maar één nodig).
function showVaultDone() {
    $("#vault-done-modal").remove();
    $("body").append(
        \'<div class="modal fade" id="vault-done-modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content bg-dark">\'
        + \'<div class="modal-header border-secondary"><h5 class="modal-title"><i class="bi bi-shield-check text-success"></i> Band versleuteld</h5>\'
        + \'<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>\'
        + \'<div class="modal-body"><p>Deze band is nu end-to-end versleuteld.</p>\'
        + \'<div class="alert alert-info py-2 small mb-0"><i class="bi bi-key me-1"></i>\'
        + \'Je bestaande <strong>herstelcode</strong> blijft geldig: \\u00e9\\u00e9n herstelcode dekt al je versleutelde bands. \'
        + \'Je hoeft dus geen nieuwe te bewaren.</div></div>\'
        + \'<div class="modal-footer border-secondary"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Begrepen</button></div>\'
        + \'</div></div></div>\'
    );
    new bootstrap.Modal("#vault-done-modal").show();
}

function showRecoveryCode(code) {
    $("#recovery-code").text(code);
    window._lgRecoveryCode = code;
    new bootstrap.Modal("#recoveryModal").show();
}

function copyRecoveryCode() {
    navigator.clipboard.writeText(window._lgRecoveryCode || "").catch(function(){});
}

function roleLabel(role) {
    if (role === "leader") return "Leider";
    if (role === "viewer") return "Kijker";
    return "Lid";
}

// Rol van een lid wijzigen (alleen leider/admin; backend bewaakt de laatste leider).
function setMemberRole(el) {
    var bandId = parseInt(el.dataset.bandId, 10);
    var userId = parseInt(el.dataset.userId, 10);
    var role   = el.value;
    $.post("api/bands.php", JSON.stringify({action: "set_role", band_id: bandId, user_id: userId, role: role}), function(r) {
        if (!r.ok) alert(r.error || "Rol wijzigen mislukt");
        loadBands(); // altijd herladen — herstelt de dropdown bij een geweigerde wijziging
    }, "json").fail(function() { alert("Rol wijzigen mislukt"); loadBands(); });
}

function renderBands(bands) {
    var c = $("#bands-container"); c.empty();
    if (!bands.length) {
        c.html(\'<div class="col-12"><div class="card"><div class="card-body text-muted py-4 text-center">\'
            + \'<i class="bi bi-people fs-3 d-block mb-2"></i>\'
            + \'Je bent nog geen lid van een band. Maak een nieuwe band aan of gebruik een uitnodigingslink.\'
            + \'</div></div></div>\');
        return;
    }
    bands.forEach(function(b, i) {
        var isActive    = (b.id == ' . (int)($user['band_id'] ?? 0) . ');
        var amLeader    = isLeaderOf(b);
        var canManage   = amLeader || _isAdmin;
        var isEncrypted = (b.is_encrypted == 1);

        var activeBadge = isActive ? \'<span class="badge bg-danger ms-2 align-middle" style="font-size:0.65rem">Actief</span>\' : \'\';
        var vaultBadge  = isEncrypted
            ? \' <i class="bi bi-shield-lock-fill text-success align-middle" title="End-to-end versleuteld" style="font-size:0.8rem"></i>\'
            : \'\';
        var editBtn  = canManage
            ? \'<button class="btn btn-xs btn-outline-secondary" onclick="openEditBand(\' + i + \')" title="Bewerken"><i class="bi bi-pencil"></i></button>\'
            : \'\';
        var leaveBtn = \'<button class="btn btn-xs btn-outline-warning ms-1"\'
            + \' data-band-id="\' + b.id + \'" data-band-name="\' + escHtml(b.name) + \'" data-is-leader="\' + (amLeader ? "1" : "0") + \'"\'
            + \' onclick="askLeave(this)" title="Band verlaten"><i class="bi bi-box-arrow-left"></i></button>\';
        var deleteBtn = _isAdmin
            ? \'<button class="btn btn-xs btn-outline-danger ms-1"\'
              + \' data-band-id="\' + b.id + \'" data-band-name="\' + escHtml(b.name) + \'"\'
              + \' onclick="askDeleteBand(this)" title="Verwijderen"><i class="bi bi-trash"></i></button>\'
            : \'\';

        // Members list
        var membersHtml = \'\';
        (b.members || []).forEach(function(m) {
            var isMe       = (m.id == _myUserId);
            var isLeader   = (m.role === "leader");
            var leaderIcon = isLeader ? \'<i class="bi bi-star-fill text-warning me-1" title="Bandleider" style="font-size:0.65rem"></i>\' : \'\';

            // Rol-besturing: leider/admin kan rollen van anderen wijzigen (niet de eigen rol).
            var roleControl;
            if (canManage && !isMe) {
                roleControl = \'<select class="form-select form-select-sm ms-auto lg-role-select"\'
                    + \' data-band-id="\' + b.id + \'" data-user-id="\' + m.id + \'" onchange="setMemberRole(this)">\'
                    + \'<option value="leader"\' + (m.role === "leader" ? " selected" : "") + \'>Leider</option>\'
                    + \'<option value="member"\' + (m.role === "member" ? " selected" : "") + \'>Lid</option>\'
                    + \'<option value="viewer"\' + (m.role === "viewer" ? " selected" : "") + \'>Kijker</option>\'
                    + \'</select>\';
            } else {
                roleControl = \'<span class="badge bg-secondary ms-auto" style="font-size:0.6rem">\' + roleLabel(m.role) + \'</span>\';
            }

            var removeBtn  = (!isMe && canManage)
                ? \'<button class="btn btn-xs btn-link text-danger p-0 ms-1"\'
                  + \' data-band-id="\' + b.id + \'" data-user-id="\' + m.id + \'" data-username="\' + escHtml(m.username) + \'"\'
                  + \' onclick="removeMember(this)" title="Toegang ontzeggen"><i class="bi bi-x-lg"></i></button>\'
                : \'\';
            // Kluisstatus per lid (alleen bij versleutelde band): placeholder die
            // grantKeysToNewMembers()/refreshVaultStatus() later invult.
            var keyStatus = isEncrypted
                ? \' <span class="vault-key-status" data-band-id="\' + b.id + \'" data-user-id="\' + m.id + \'"></span>\'
                : \'\';

            membersHtml += \'<div class="d-flex align-items-center py-1 border-bottom border-secondary" style="border-bottom-style:dashed!important">\'
                + leaderIcon
                + \'<span class="small \' + (isMe ? "text-white" : "text-muted") + \'">\' + escHtml(m.username) + (isMe ? \' <span class="text-muted">(jij)</span>\' : \'\') + \'</span>\'
                + keyStatus
                + roleControl
                + removeBtn
                + \'</div>\';
        });
        if (!membersHtml) membersHtml = \'<span class="text-muted small">Geen leden</span>\';

        var vaultRow = \'\';
        if (canManage && window.LGVault) {
            vaultRow = isEncrypted
                ? \'<div class="px-3 py-2 small text-success"><i class="bi bi-shield-lock-fill me-1"></i>End-to-end versleuteld</div>\'
                : \'<button class="btn btn-link btn-sm text-muted w-100 text-start px-3 py-2" onclick="askEnableVault(\' + b.id + \')"><i class="bi bi-shield-lock me-1"></i> Band versleutelen</button>\';
        }

        var inviteFooter = canManage
            ? \'<div class="card-footer border-secondary p-0">\'
              + vaultRow
              + (vaultRow ? \'<div class="border-top border-secondary" style="border-top-style:dashed!important"></div>\' : \'\')
              + \'<button class="btn btn-link btn-sm text-muted w-100 text-start px-3 py-2" onclick="toggleInvite(\' + b.id + \')"><i class="bi bi-link-45deg me-1"></i> Uitnodigingslink</button>\'
              + \'<div id="invite-\' + b.id + \'" class="px-3 pb-3" style="display:none"></div>\'
              + \'<div class="border-top border-secondary" style="border-top-style:dashed!important">\'
              + \'<button class="btn btn-link btn-sm text-muted w-100 text-start px-3 py-2" onclick="toggleShare(\' + b.id + \')"><i class="bi bi-eye me-1"></i> Setlijst deellink</button>\'
              + \'<div id="share-\' + b.id + \'" class="px-3 pb-3" style="display:none"></div>\'
              + \'</div>\'
              + \'</div>\'
            : \'\';

        c.append(\'<div class="col-md-6 col-lg-4">\'
            + \'<div class="card h-100 d-flex flex-column">\'
            + \'<div class="card-body pb-2">\'
            + \'<div class="d-flex justify-content-between align-items-start mb-2">\'
            + \'<h6 class="fw-bold mb-0">\' + escHtml(b.name) + activeBadge + vaultBadge + \'</h6>\'
            + \'<div class="d-flex">\' + editBtn + leaveBtn + deleteBtn + \'</div>\'
            + \'</div>\'
            + (b.description ? \'<p class="text-muted small mb-2">\' + escHtml(b.description) + \'</p>\' : \'\')
            + \'<div class="mb-2">\' + membersHtml + \'</div>\'
            + \'</div>\'
            + inviteFooter
            + \'</div></div>\');
    });
}

// ---- Edit / create band ----

function openAddBand() {
    $("#bandModalTitle").text("Band aanmaken");
    $("#band-id").val("");
    $("#band-name").val("");
    $("#band-description").val("");
    // Versleutel-optie tonen (alleen bij aanmaken), standaard aan.
    var canEncrypt = !!(window.LGVault && window.LGKeys && LGKeys.keyState() !== "unsupported");
    $("#band-encrypt-section").show();
    $("#band-encrypt").prop("checked", canEncrypt).prop("disabled", !canEncrypt);
    $("#band-encrypt-unsupported").toggle(!canEncrypt);
    new bootstrap.Modal("#bandModal").show();
}

function openEditBand(i) {
    var b = _allBands[i];
    if (!b) return;
    $("#bandModalTitle").text("Band bewerken");
    $("#band-id").val(b.id);
    $("#band-name").val(b.name);
    $("#band-description").val(b.description || "");
    $("#band-encrypt-section").hide(); // versleutelen gebeurt apart, niet bij bewerken
    new bootstrap.Modal("#bandModal").show();
}

function saveBand() {
    var name = $("#band-name").val().trim();
    if (!name) { alert("Bandnaam is verplicht."); return; }
    var id = $("#band-id").val();
    var data = { id: id, name: name, description: $("#band-description").val().trim() };
    var wantEncrypt = !id && $("#band-encrypt-section").is(":visible")
                      && $("#band-encrypt").is(":checked") && !$("#band-encrypt").prop("disabled");

    $.post("api/bands.php", JSON.stringify(data), function(r) {
        if (!r.ok) { alert(r.error || "Fout bij opslaan"); return; }
        bootstrap.Modal.getInstance("#bandModal").hide();
        if (wantEncrypt && r.id) {
            // Nieuwe band meteen versleutelen (kluis aanzetten + herstelcode tonen).
            enableVaultForNewBand(r.id);
        } else {
            loadBands();
        }
    }, "json");
}

// Versleutelt een zojuist aangemaakte band en toont de herstelcode.
function enableVaultForNewBand(bandId) {
    if (!window.LGVault || !window.LGKeys) { loadBands(); return; }
    if (LGKeys.keyState() !== "unlocked") {
        alert("Je kluis is nog niet ontgrendeld; de band is onversleuteld aangemaakt. Je kunt hem later versleutelen via \'Band versleutelen\'.");
        loadBands(); return;
    }
    LGVault.enableVault(bandId).then(function() {
        return fetch("api/keys.php", {credentials:"same-origin", headers:{Accept:"application/json"}})
            .then(function(r){ return r.json(); })
            .then(function(st) {
                if (st.ok && !st.has_recovery) {
                    return LGKeys.setupRecovery().then(function(code) { showRecoveryCode(code); });
                }
                // Al een herstelcode → bevestigen dat die ook voor deze band geldt.
                showVaultDone();
                loadBands();
            });
    }).catch(function(e) {
        alert("Band aangemaakt, maar versleutelen mislukte: " + (e.message || "onbekende fout") + "\\nJe kunt het later opnieuw proberen via \'Band versleutelen\'.");
        loadBands();
    });
}

// ---- Member removal ----

function removeMember(el) {
    var bandId   = parseInt(el.dataset.bandId,  10);
    var userId   = parseInt(el.dataset.userId,  10);
    var username = el.dataset.username; // browser decodeert HTML-entities automatisch
    if (!confirm("Toegang ontzeggen aan " + username + "?")) return;
    $.ajax({ url: "api/bands.php", type: "DELETE",
        data: JSON.stringify({band_id: bandId, user_id: userId}),
        contentType: "application/json",
        success: function(r) {
            if (r.ok) loadBands();
            else alert(r.error || "Fout");
        }
    });
}

// ---- Leave band ----

function askLeave(el) {
    _leaveBandId = parseInt(el.dataset.bandId, 10);
    var name     = el.dataset.bandName; // browser decodeert HTML-entities automatisch
    var amLeader = el.dataset.isLeader === "1";
    $("#leave-band-name").text(name);
    var warning = $("#leave-leader-warning");
    if (amLeader) { warning.show(); } else { warning.hide(); }
    new bootstrap.Modal("#leaveModal").show();
}

function confirmLeave() {
    $.ajax({ url: "api/bands.php", type: "DELETE",
        data: JSON.stringify({band_id: _leaveBandId, user_id: _myUserId}),
        contentType: "application/json",
        success: function(r) {
            bootstrap.Modal.getInstance("#leaveModal").hide();
            if (r.ok) loadBands();
            else alert(r.error || "Fout");
        }
    });
}

// ---- Delete band (admin) ----

function askDeleteBand(el) {
    _deleteBandId = parseInt(el.dataset.bandId, 10);
    var name      = el.dataset.bandName; // browser decodeert HTML-entities automatisch
    $("#delete-band-name").text(name);
    new bootstrap.Modal("#deleteBandModal").show();
}

function confirmDeleteBand() {
    $.ajax({ url: "api/bands.php", type: "DELETE",
        data: JSON.stringify({id: _deleteBandId}),
        contentType: "application/json",
        success: function(r) {
            bootstrap.Modal.getInstance("#deleteBandModal").hide();
            if (r.ok) loadBands();
            else alert(r.error || "Fout");
        }
    });
}

// ---- Invite link ----

function toggleInvite(bandId) {
    var section = $("#invite-" + bandId);
    if (section.is(":visible")) { section.hide(); return; }
    section.show();
    if (!section.data("loaded")) {
        section.html(\'<div class="text-muted small"><i class="bi bi-hourglass-split"></i> Laden...</div>\');
        $.get("api/invite.php", {band_id: bandId}, function(r) {
            section.data("loaded", true);
            // Plaintext-token wordt alleen bij creatie geretourneerd; bij GET zien we alleen of er één is.
            if (r.has_token) {
                renderInviteExists(bandId);
            } else {
                renderNoInvite(bandId);
            }
        });
    }
}

function inviteUrl(token) {
    var base = window.location.href.replace(/\/[^\/]*(\?.*)?$/, "/");
    return base + "join.php?token=" + token;
}

function renderInviteToken(bandId, token) {
    var url = inviteUrl(token);
    var section = $("#invite-" + bandId);
    section.html(
        \'<div class="input-group input-group-sm mb-2">\'
        + \'<input type="text" class="form-control form-control-sm" id="invite-url-\' + bandId + \'" value="\' + escHtml(url) + \'" readonly onclick="this.select()">\'
        + \'<button class="btn btn-outline-secondary btn-sm" onclick="copyInvite(\' + bandId + \')" title="Kopieer"><i class="bi bi-clipboard"></i></button>\'
        + \'</div>\'
        + \'<div class="d-flex gap-2">\'
        + \'<button class="btn btn-xs btn-outline-secondary" onclick="regenerateInvite(\' + bandId + \')"><i class="bi bi-arrow-repeat me-1"></i>Nieuwe link</button>\'
        + \'<button class="btn btn-xs btn-outline-danger" onclick="revokeInvite(\' + bandId + \')"><i class="bi bi-trash me-1"></i>Verwijder link</button>\'
        + \'</div>\'
    );
}

function renderNoInvite(bandId) {
    var section = $("#invite-" + bandId);
    section.html(
        \'<p class="text-muted small mb-2">Nog geen uitnodigingslink aangemaakt.</p>\'
        + \'<button class="btn btn-xs btn-outline-secondary" onclick="regenerateInvite(\' + bandId + \')"><i class="bi bi-plus-lg me-1"></i>Link aanmaken</button>\'
    );
}

// Token bestaat al maar plaintext is niet bekend (alleen bij creatie zichtbaar).
function renderInviteExists(bandId) {
    var section = $("#invite-" + bandId);
    section.html(
        \'<p class="text-muted small mb-2">Er bestaat een uitnodigingslink. De URL wordt om veiligheidsredenen alleen bij aanmaak getoond.</p>\'
        + \'<div class="d-flex gap-2">\'
        + \'<button class="btn btn-xs btn-outline-secondary" onclick="regenerateInvite(\' + bandId + \')"><i class="bi bi-arrow-repeat me-1"></i>Nieuwe link</button>\'
        + \'<button class="btn btn-xs btn-outline-danger" onclick="revokeInvite(\' + bandId + \')"><i class="bi bi-trash me-1"></i>Verwijder link</button>\'
        + \'</div>\'
    );
}

function copyInvite(bandId) {
    var input = document.getElementById("invite-url-" + bandId);
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = $(input).next("button");
        btn.html(\'<i class="bi bi-check-lg"></i>\');
        setTimeout(function() { btn.html(\'<i class="bi bi-clipboard"></i>\'); }, 1500);
    }).catch(function() {
        input.select(); document.execCommand("copy");
    });
}

function regenerateInvite(bandId) {
    $.post("api/invite.php", JSON.stringify({band_id: bandId}), function(r) {
        if (r.ok) {
            $("#invite-" + bandId).data("loaded", true);
            renderInviteToken(bandId, r.token);
        } else { alert(r.error || "Fout"); }
    }, "json");
}

function revokeInvite(bandId) {
    if (!confirm("Uitnodigingslink verwijderen? Bestaande links werken dan niet meer.")) return;
    $.ajax({ url: "api/invite.php", type: "DELETE",
        data: JSON.stringify({band_id: bandId}),
        contentType: "application/json",
        success: function(r) {
            if (r.ok) {
                var section = $("#invite-" + bandId);
                section.data("loaded", true);
                renderNoInvite(bandId);
            } else { alert(r.error || "Fout"); }
        }
    });
}

// ---- Setlijst deellink ----

function shareUrl(token, keyB64) {
    var base = window.location.href.replace(/\/[^\/]*(\?.*)?$/, "/");
    var url = base + "public.php?t=" + token;
    // E2EE: de deelsleutel gaat in de URL-fragment (#k=…) en bereikt de server nooit.
    if (keyB64) url += "#k=" + encodeURIComponent(keyB64);
    return url;
}

// Is deze band versleuteld? (op basis van de geladen bandgegevens)
function bandIsEnc(bandId) {
    var b = _allBands.find(function(x){ return x.id == bandId; });
    return b && b.is_encrypted == 1;
}

/* Bouwt de publieke projectie van een versleutelde band: alleen de velden die
   op de deelpagina getoond worden (geen notities/tekst/akkoorden/PDF). Haalt de
   setlijsten op en ontsleutelt namen + nummers in de browser. */
function buildShareProjection(bandId) {
    var b = _allBands.find(function(x){ return x.id == bandId; });
    var bandName = b ? b.name : "";
    return $.get("api/setlists.php", {band_id: bandId}).then(function(data) {
        var lists = data.setlists || [];
        var namesDone = (window.LGVault && lists.some(function(sl){ return sl.enc_blob; }))
            ? LGVault.decryptSetlists(bandId, lists) : Promise.resolve(lists);
        return namesDone.then(function() {
            return Promise.all(lists.map(function(sl) {
                var songsDone = (window.LGVault && (sl.songs||[]).some(function(s){ return s.enc_blob; }))
                    ? LGVault.decryptSongs(bandId, sl.songs || []) : Promise.resolve(sl.songs || []);
                return songsDone.then(function(songs) {
                    return {
                        name: sl.name,
                        songs: songs.map(function(s) {
                            return { title: s.title, artist: s.artist, bpm: s.bpm,
                                     duration: s.duration, starts: s.starts };
                        })
                    };
                });
            })).then(function(projLists) {
                projLists.sort(function(a,b){ return (a.name||"").localeCompare(b.name||"", undefined, {sensitivity:"base"}); });
                return { band: bandName, setlists: projLists };
            });
        });
    });
}

function toggleShare(bandId) {
    var section = $("#share-" + bandId);
    if (section.is(":visible")) { section.hide(); return; }
    section.show();
    if (!section.data("loaded")) {
        section.html(\'<div class="text-muted small"><i class="bi bi-hourglass-split"></i> Laden...</div>\');
        $.get("api/share.php?band_id=" + bandId, function(r) {
            section.data("loaded", true);
            // Plaintext-token alleen bij creatie geretourneerd; bij GET zien we alleen of er één is.
            if (r.has_token) { renderShareExists(bandId); }
            else             { renderNoShare(bandId); }
        });
    }
}

function renderShareToken(bandId, token, keyB64) {
    var url = shareUrl(token, keyB64);
    var encNote = keyB64
        ? \'<p class="text-muted small mb-2"><i class="bi bi-shield-lock me-1"></i>Deze link bevat de sleutel (na #) — deel de volledige link. De server kan de inhoud niet lezen.</p>\'
        : \'\';
    $("#share-" + bandId).html(
        encNote
        + \'<div class="input-group input-group-sm mb-2">\'
        + \'<input type="text" class="form-control form-control-sm" id="share-url-\' + bandId + \'" value="\' + escHtml(url) + \'" readonly onclick="this.select()">\'
        + \'<button class="btn btn-outline-secondary btn-sm" onclick="copyShare(\' + bandId + \')" title="Kopieer"><i class="bi bi-clipboard"></i></button>\'
        + \'</div>\'
        + \'<div class="d-flex gap-2">\'
        + \'<button class="btn btn-xs btn-outline-secondary" onclick="regenerateShare(\' + bandId + \')"><i class="bi bi-arrow-repeat me-1"></i>Nieuwe link</button>\'
        + \'<button class="btn btn-xs btn-outline-danger" onclick="revokeShare(\' + bandId + \')"><i class="bi bi-trash me-1"></i>Verwijder</button>\'
        + \'</div>\'
    );
}

function renderNoShare(bandId) {
    $("#share-" + bandId).html(
        \'<p class="text-muted small mb-2">Nog geen deellink aangemaakt.</p>\'
        + \'<button class="btn btn-xs btn-outline-secondary" onclick="regenerateShare(\' + bandId + \')"><i class="bi bi-plus-lg me-1"></i>Link aanmaken</button>\'
    );
}

// Token bestaat al maar plaintext is niet bekend (alleen bij creatie zichtbaar).
// Bij een versleutelde band is de deelsleutel sowieso niet herleidbaar — een
// nieuwe link aanmaken is dan de enige optie.
function renderShareExists(bandId) {
    var note = bandIsEnc(bandId)
        ? "Er bestaat een deellink. De sleutel zat in de eerder getoonde link; maak een nieuwe link als je hem kwijt bent."
        : "Er bestaat een deellink. De URL wordt om veiligheidsredenen alleen bij aanmaak getoond.";
    $("#share-" + bandId).html(
        \'<p class="text-muted small mb-2">\' + note + \'</p>\'
        + \'<div class="d-flex gap-2">\'
        + \'<button class="btn btn-xs btn-outline-secondary" onclick="regenerateShare(\' + bandId + \')"><i class="bi bi-arrow-repeat me-1"></i>Nieuwe link</button>\'
        + \'<button class="btn btn-xs btn-outline-danger" onclick="revokeShare(\' + bandId + \')"><i class="bi bi-trash me-1"></i>Verwijder</button>\'
        + \'</div>\'
    );
}

function copyShare(bandId) {
    var input = document.getElementById("share-url-" + bandId);
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = $(input).next("button");
        btn.html(\'<i class="bi bi-check-lg"></i>\');
        setTimeout(function() { btn.html(\'<i class="bi bi-clipboard"></i>\'); }, 1500);
    }).catch(function() { input.select(); document.execCommand("copy"); });
}

function regenerateShare(bandId) {
    if (bandIsEnc(bandId)) {
        // Versleutelde band: projectie bouwen, versleutelen, blob + token aanmaken,
        // en de deelsleutel in de fragment van de getoonde URL zetten.
        if (window.LGKeys && LGKeys.keyState() !== "unlocked") {
            alert("Je kluis is niet ontgrendeld. Log opnieuw in met je wachtwoord."); return;
        }
        $("#share-" + bandId).html(\'<div class="text-muted small"><i class="bi bi-hourglass-split"></i> Versleutelen...</div>\');
        buildShareProjection(bandId).then(function(proj) {
            return LGShare.encrypt(proj).then(function(enc) {
                return $.ajax({ url: "api/share.php", type: "POST", contentType: "application/json",
                    data: JSON.stringify({ band_id: bandId, share_blob: enc.blob }), dataType: "json" })
                    .then(function(r) {
                        if (!r.ok) throw new Error(r.error || "Fout");
                        $("#share-" + bandId).data("loaded", true);
                        renderShareToken(bandId, r.token, enc.keyB64);
                    });
            });
        }).catch(function(e) { alert(e.message || "Deellink aanmaken mislukt."); renderNoShare(bandId); });
        return;
    }
    $.post("api/share.php", JSON.stringify({band_id: bandId}), function(r) {
        if (r.ok) {
            $("#share-" + bandId).data("loaded", true);
            renderShareToken(bandId, r.token);
        } else { alert(r.error || "Fout"); }
    }, "json");
}

function revokeShare(bandId) {
    if (!confirm("Deellink verwijderen? Gasten kunnen de setlijsten dan niet meer bekijken.")) return;
    $.ajax({ url: "api/share.php", type: "DELETE",
        data: JSON.stringify({band_id: bandId}),
        contentType: "application/json",
        success: function(r) {
            if (r.ok) {
                var s = $("#share-" + bandId);
                s.data("loaded", true);
                renderNoShare(bandId);
            } else { alert(r.error || "Fout"); }
        }
    });
}
</script>';
require APP_ROOT . '/includes/footer.php';
?>
