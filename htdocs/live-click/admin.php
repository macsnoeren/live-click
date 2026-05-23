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
                <button class="btn btn-danger btn-sm" onclick="openAddBand()">
                    <i class="bi bi-plus-lg"></i> Band toevoegen
                </button>
            </div>
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
                <div class="mb-3">
                    <label class="form-label">Bands</label>
                    <div id="user-bands-list"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="saveUser()">Opslaan</button>
            </div>
        </div>
    </div>
</div>

<!-- Band Modal -->
<div class="modal fade" id="bandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="bandModalTitle">Band toevoegen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="band-id">
                <div class="mb-3">
                    <label class="form-label">Bandnaam *</label>
                    <input type="text" id="band-name" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Beschrijving</label>
                    <textarea id="band-description" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Leden</label>
                    <div id="band-members-list"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="saveBand()">Opslaan</button>
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
        c.append(\'<div class="col-md-6 col-lg-4">\'
            + \'<div class="card"><div class="card-body">\'
            + \'<div class="d-flex justify-content-between align-items-start">\'
            + \'<div><h6 class="fw-bold mb-1">\' + escHtml(b.name) + \'</h6>\'
            + \'<p class="text-muted small mb-1">\' + escHtml(b.description || "") + \'</p>\'
            + \'<p class="small mb-0">Leden: \' + (members || "—") + \'</p></div>\'
            + \'<button class="btn btn-xs btn-outline-secondary" onclick="openEditBand(\' + b.id + \')"><i class="bi bi-pencil"></i></button>\'
            + \'</div></div></div></div>\');
    });
}

function openAddUser() {
    $("#userModalTitle").text("Gebruiker toevoegen");
    $("#user-id").val(""); $("#user-username").val(""); $("#user-email").val("");
    $("#user-password").val(""); $("#user-role").val("user");
    $("#user-must-change-password").prop("checked", false);
    renderUserBandCheckboxes([]);
    new bootstrap.Modal("#userModal").show();
}

function openEditUser(id) {
    var u = _allUsers.find(function(x) { return x.id === id; });
    if (!u) return;
    $("#userModalTitle").text("Gebruiker bewerken");
    $("#user-id").val(u.id); $("#user-username").val(u.username); $("#user-email").val(u.email);
    $("#user-password").val(""); $("#user-role").val(u.role);
    $("#user-must-change-password").prop("checked", !!u.must_change_password);
    renderUserBandCheckboxes((u.bands||[]).map(function(b){return b.id;}));
    new bootstrap.Modal("#userModal").show();
}

function renderUserBandCheckboxes(selectedIds) {
    var c = $("#user-bands-list"); c.empty();
    _allBands.forEach(function(b) {
        c.append(\'<div class="form-check">\'
            + \'<input class="form-check-input" type="checkbox" id="ub_\' + b.id + \'" value="\' + b.id + \'" \' + (selectedIds.includes(b.id) ? "checked" : "") + \'>\'
            + \'<label class="form-check-label" for="ub_\' + b.id + \'">\' + escHtml(b.name) + \'</label>\'
            + \'</div>\');
    });
}

function saveUser() {
    var bandIds = [];
    $("#user-bands-list input:checked").each(function() { bandIds.push(parseInt($(this).val())); });
    var data = {
        id: $("#user-id").val(), username: $("#user-username").val().trim(),
        email: $("#user-email").val().trim(), password: $("#user-password").val(),
        role: $("#user-role").val(), band_ids: bandIds,
        must_change_password: $("#user-must-change-password").is(":checked") ? 1 : 0
    };
    if (!data.username || !data.email) { alert("Gebruikersnaam en e-mail zijn verplicht."); return; }
    $.post("api/users.php", JSON.stringify(data), function(r) {
        if (r.ok) { bootstrap.Modal.getInstance("#userModal").hide(); loadUsers(); loadBands(); }
        else { alert(r.error || "Fout"); }
    }, "json");
}

function openAddBand() {
    $("#bandModalTitle").text("Band toevoegen");
    $("#band-id").val(""); $("#band-name").val(""); $("#band-description").val("");
    renderBandMemberCheckboxes([]);
    new bootstrap.Modal("#bandModal").show();
}

function openEditBand(id) {
    var b = _allBands.find(function(x) { return x.id === id; });
    if (!b) return;
    $("#bandModalTitle").text("Band bewerken");
    $("#band-id").val(b.id); $("#band-name").val(b.name); $("#band-description").val(b.description||"");
    renderBandMemberCheckboxes((b.members||[]).map(function(m){return m.id;}));
    new bootstrap.Modal("#bandModal").show();
}

function renderBandMemberCheckboxes(selectedIds) {
    var c = $("#band-members-list"); c.empty();
    _allUsers.forEach(function(u) {
        c.append(\'<div class="form-check">\'
            + \'<input class="form-check-input" type="checkbox" id="bm_\' + u.id + \'" value="\' + u.id + \'" \' + (selectedIds.includes(u.id) ? "checked" : "") + \'>\'
            + \'<label class="form-check-label" for="bm_\' + u.id + \'">\' + escHtml(u.username) + \'</label>\'
            + \'</div>\');
    });
}

function saveBand() {
    var memberIds = [];
    $("#band-members-list input:checked").each(function() { memberIds.push(parseInt($(this).val())); });
    var data = {
        id: $("#band-id").val(), name: $("#band-name").val().trim(),
        description: $("#band-description").val().trim(), member_ids: memberIds
    };
    if (!data.name) { alert("Bandnaam is verplicht."); return; }
    $.post("api/bands.php", JSON.stringify(data), function(r) {
        if (r.ok) { bootstrap.Modal.getInstance("#bandModal").hide(); loadBands(); loadUsers(); }
        else { alert(r.error || "Fout"); }
    }, "json");
}
</script>';
require APP_ROOT . '/includes/footer.php';
?>
