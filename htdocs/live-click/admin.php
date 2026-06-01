<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireAdmin();
$user = currentUser();
$pageTitle = 'Admin — LiveGig';
require APP_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3">
    <h4 class="mb-3"><i class="bi bi-shield"></i> Admin beheer</h4>

    <ul class="nav nav-tabs border-secondary mb-3" id="adminTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-users">Gebruikers</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-bands">Bands</a></li>
    </ul>

    <div class="tab-content">
        <!-- USERS TAB -->
        <div class="tab-pane fade show active" id="tab-users">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Gebruikers</h5>
                <button class="btn btn-danger btn-sm" onclick="openAddUser()">
                    <i class="bi bi-plus-lg"></i> Gebruiker toevoegen
                </button>
            </div>
            <div class="card">
                <table class="table table-dark table-hover table-sm mb-0">
                    <thead><tr><th>Gebruikersnaam</th><th>E-mail</th><th>Rol</th><th>Bands</th><th>Aangemaakt</th><th></th></tr></thead>
                    <tbody id="users-tbody"><tr><td colspan="6" class="text-muted">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- BANDS TAB -->
        <div class="tab-pane fade" id="tab-bands">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Bands</h5>
            </div>
            <p class="text-muted small">
                Als beheerder kun je bands alleen <strong>verwijderen</strong> (opschonen).
                Aanmaken, hernoemen, leden en versleuteling beheren de bandleiders zelf.
            </p>
            <div id="bands-container" class="row g-3">
                <div class="col-12 text-muted">Laden...</div>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="userModalTitle">Gebruiker toevoegen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="user-id">
                <div class="mb-3">
                    <label class="form-label">Gebruikersnaam *</label>
                    <input type="text" id="user-username" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail *</label>
                    <input type="email" id="user-email" class="form-control">
                </div>
                <div class="mb-3" id="user-pass-group">
                    <label class="form-label">Wachtwoord <span id="pass-required">*</span></label>
                    <input type="password" id="user-password" class="form-control">
                    <div class="form-text text-muted">Laat leeg om huidig wachtwoord te behouden</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <select id="user-role" class="form-select">
                        <option value="user">Gebruiker</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="user-must-change-password">
                        <label class="form-check-label" for="user-must-change-password">
                            Verplicht wachtwoord wijzigen bij volgende login
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="saveUser()">Opslaan</button>
            </div>
        </div>
    </div>
</div>

<!-- Band verwijderen: bevestiging -->
<div class="modal fade" id="deleteBandModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Band verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="del-band-name"></strong> wilt verwijderen?
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

<?php
$extraScripts = '<script>
var _allUsers = [];
var _allBands = [];

$(function() {
    loadUsers();
    loadBands();
});

function loadUsers() {
    $.get("api/users.php", function(data) {
        _allUsers = data.users || [];
        renderUsers(_allUsers);
    });
}

function renderUsers(users) {
    var tb = $("#users-tbody"); tb.empty();
    if (!users.length) { tb.append(\'<tr><td colspan="6" class="text-muted">Geen gebruikers</td></tr>\'); return; }
    users.forEach(function(u) {
        var bands = (u.bands || []).map(function(b){ return escHtml(b.name); }).join(", ");
        tb.append(\'<tr>\'
            + \'<td class="fw-semibold">\' + escHtml(u.username) + \'</td>\'
            + \'<td class="text-muted">\' + escHtml(u.email) + \'</td>\'
            + \'<td><span class="badge \' + (u.role==="admin"?"bg-warning text-dark":"bg-secondary") + \'">\' + u.role + \'</span></td>\'
            + \'<td class="text-muted">\' + (bands || "—") + \'</td>\'
            + \'<td class="text-muted small">\' + escHtml(u.created_at||"") + \'</td>\'
            + \'<td><button class="btn btn-xs btn-outline-secondary" onclick="openEditUser(\' + u.id + \')"><i class="bi bi-pencil"></i></button></td>\'
            + \'</tr>\');
    });
}

function loadBands() {
    $.get("api/bands.php", function(data) {
        _allBands = data.bands || [];
        renderBands(_allBands);
    });
}

function renderBands(bands) {
    var c = $("#bands-container"); c.empty();
    if (!bands.length) { c.html(\'<div class="col-12 text-muted">Nog geen bands</div>\'); return; }
    bands.forEach(function(b) {
        var members = (b.members || []).map(function(m){ return escHtml(m.username); }).join(", ");
        var enc = (b.is_encrypted == 1)
            ? \' <i class="bi bi-shield-lock-fill text-success" title="Versleuteld"></i>\' : "";
        c.append(\'<div class="col-md-6 col-lg-4">\'
            + \'<div class="card"><div class="card-body">\'
            + \'<div class="d-flex justify-content-between align-items-start">\'
            + \'<div><h6 class="fw-bold mb-1">\' + escHtml(b.name) + enc + \'</h6>\'
            + \'<p class="text-muted small mb-1">\' + escHtml(b.description || "") + \'</p>\'
            + \'<p class="small mb-0">Leden: \' + (members || "—") + \'</p></div>\'
            + \'<button class="btn btn-xs btn-outline-danger" onclick="askDeleteBand(\' + b.id + \')" title="Band verwijderen"><i class="bi bi-trash"></i></button>\'
            + \'</div></div></div></div>\');
    });
}

var _delBandId = null;
function askDeleteBand(id) {
    var b = _allBands.find(function(x){ return x.id === id; });
    _delBandId = id;
    $("#del-band-name").text(b ? b.name : "deze band");
    new bootstrap.Modal("#deleteBandModal").show();
}
function confirmDeleteBand() {
    if (!_delBandId) return;
    $.ajax({ url: "api/bands.php", type: "DELETE", contentType: "application/json",
        data: JSON.stringify({ id: _delBandId }), dataType: "json",
        success: function(r) {
            bootstrap.Modal.getInstance("#deleteBandModal").hide();
            if (r.ok) { loadBands(); loadUsers(); }
            else alert(r.error || "Verwijderen mislukt");
        },
        error: function(xhr) { alert("Verwijderen mislukt (HTTP " + xhr.status + ")"); }
    });
}

function openAddUser() {
    $("#userModalTitle").text("Gebruiker toevoegen");
    $("#user-id").val(""); $("#user-username").val(""); $("#user-email").val("");
    $("#user-password").val(""); $("#user-role").val("user");
    $("#user-must-change-password").prop("checked", false);
    new bootstrap.Modal("#userModal").show();
}

function openEditUser(id) {
    var u = _allUsers.find(function(x) { return x.id === id; });
    if (!u) return;
    $("#userModalTitle").text("Gebruiker bewerken");
    $("#user-id").val(u.id); $("#user-username").val(u.username); $("#user-email").val(u.email);
    $("#user-password").val(""); $("#user-role").val(u.role);
    $("#user-must-change-password").prop("checked", !!u.must_change_password);
    new bootstrap.Modal("#userModal").show();
}

function saveUser() {
    var data = {
        id: $("#user-id").val(), username: $("#user-username").val().trim(),
        email: $("#user-email").val().trim(), password: $("#user-password").val(),
        role: $("#user-role").val(),
        must_change_password: $("#user-must-change-password").is(":checked") ? 1 : 0
    };
    if (!data.username || !data.email) { alert("Gebruikersnaam en e-mail zijn verplicht."); return; }
    $.post("api/users.php", JSON.stringify(data), function(r) {
        if (r.ok) { bootstrap.Modal.getInstance("#userModal").hide(); loadUsers(); }
        else { alert(r.error || "Fout"); }
    }, "json");
}
</script>';
require APP_ROOT . '/includes/footer.php';
?>
