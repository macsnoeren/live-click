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
                <?php if ($user['role'] === 'admin'): ?>
                <div class="mb-3">
                    <label class="form-label">Leden</label>
                    <div id="band-members-checkboxes" class="small"></div>
                </div>
                <?php endif; ?>
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

<?php
$isAdmin = $user['role'] === 'admin';
$extraScripts = '<script>
var _allBands  = [];
var _allUsers  = [];
var _isAdmin   = ' . ($isAdmin ? 'true' : 'false') . ';
var _myUserId  = ' . (int)$user['id'] . ';
var _deleteBandId = null;
var _leaveBandId  = null;
var _leaveBandName = "";

$(function() {
    loadBands();
    ' . ($isAdmin ? 'loadAllUsers();' : '') . '
});

function loadBands() {
    $.get("api/bands.php", function(data) {
        _allBands = data.bands || [];
        renderBands(_allBands);
    });
}

function loadAllUsers() {
    $.get("api/users.php", function(data) {
        _allUsers = data.users || [];
    });
}

function isLeaderOf(b) {
    return (b.members || []).some(function(m) { return m.id == _myUserId && m.role === "leader"; });
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

        var activeBadge = isActive ? \'<span class="badge bg-danger ms-2 align-middle" style="font-size:0.65rem">Actief</span>\' : \'\';
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
            var removeBtn  = (!isMe && canManage)
                ? \'<button class="btn btn-xs btn-link text-danger p-0 ms-auto"\'
                  + \' data-band-id="\' + b.id + \'" data-user-id="\' + m.id + \'" data-username="\' + escHtml(m.username) + \'"\'
                  + \' onclick="removeMember(this)" title="Toegang ontzeggen"><i class="bi bi-x-lg"></i></button>\'
                : \'\';
            membersHtml += \'<div class="d-flex align-items-center py-1 border-bottom border-secondary" style="border-bottom-style:dashed!important">\'
                + leaderIcon
                + \'<span class="small \' + (isMe ? "text-white" : "text-muted") + \'">\' + escHtml(m.username) + (isMe ? \' <span class="text-muted">(jij)</span>\' : \'\') + \'</span>\'
                + removeBtn
                + \'</div>\';
        });
        if (!membersHtml) membersHtml = \'<span class="text-muted small">Geen leden</span>\';

        var inviteFooter = canManage
            ? \'<div class="card-footer border-secondary p-0">\'
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
            + \'<h6 class="fw-bold mb-0">\' + escHtml(b.name) + activeBadge + \'</h6>\'
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
    if (_isAdmin) renderMemberCheckboxes([]);
    new bootstrap.Modal("#bandModal").show();
}

function openEditBand(i) {
    var b = _allBands[i];
    if (!b) return;
    $("#bandModalTitle").text("Band bewerken");
    $("#band-id").val(b.id);
    $("#band-name").val(b.name);
    $("#band-description").val(b.description || "");
    if (_isAdmin) renderMemberCheckboxes((b.members || []).map(function(m){ return m.id; }));
    new bootstrap.Modal("#bandModal").show();
}

function renderMemberCheckboxes(selectedIds) {
    var c = $("#band-members-checkboxes"); c.empty();
    if (!_allUsers.length) { c.text("Geen gebruikers beschikbaar."); return; }
    _allUsers.forEach(function(u) {
        c.append(\'<div class="form-check">\'
            + \'<input class="form-check-input" type="checkbox" id="bm_\' + u.id + \'" value="\' + u.id + \'" \' + (selectedIds.includes(u.id) ? "checked" : "") + \'>\'
            + \'<label class="form-check-label" for="bm_\' + u.id + \'">\' + escHtml(u.username) + \'</label>\'
            + \'</div>\');
    });
}

function saveBand() {
    var name = $("#band-name").val().trim();
    if (!name) { alert("Bandnaam is verplicht."); return; }
    var data = { id: $("#band-id").val(), name: name, description: $("#band-description").val().trim() };
    if (_isAdmin) {
        var memberIds = [];
        $("#band-members-checkboxes input:checked").each(function() { memberIds.push(parseInt($(this).val())); });
        data.member_ids = memberIds;
    }
    $.post("api/bands.php", JSON.stringify(data), function(r) {
        if (r.ok) { bootstrap.Modal.getInstance("#bandModal").hide(); loadBands(); }
        else { alert(r.error || "Fout bij opslaan"); }
    }, "json");
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
            if (r.token) {
                renderInviteToken(bandId, r.token);
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

function shareUrl(token) {
    var base = window.location.href.replace(/\/[^\/]*(\?.*)?$/, "/");
    return base + "public.php?t=" + token;
}

function toggleShare(bandId) {
    var section = $("#share-" + bandId);
    if (section.is(":visible")) { section.hide(); return; }
    section.show();
    if (!section.data("loaded")) {
        section.html(\'<div class="text-muted small"><i class="bi bi-hourglass-split"></i> Laden...</div>\');
        $.get("api/share.php?band_id=" + bandId, function(r) {
            section.data("loaded", true);
            if (r.token) { renderShareToken(bandId, r.token); }
            else         { renderNoShare(bandId); }
        });
    }
}

function renderShareToken(bandId, token) {
    var url = shareUrl(token);
    $("#share-" + bandId).html(
        \'<div class="input-group input-group-sm mb-2">\'
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

function copyShare(bandId) {
    var input = document.getElementById("share-url-" + bandId);
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = $(input).next("button");
        btn.html(\'<i class="bi bi-check-lg"></i>\');
        setTimeout(function() { btn.html(\'<i class="bi bi-clipboard"></i>\'); }, 1500);
    }).catch(function() { input.select(); document.execCommand("copy"); });
}

function regenerateShare(bandId) {
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
