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
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-payments">Betalingen</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-discounts">Kortingscodes</a></li>
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
                <div class="table-responsive">
                <table class="table table-dark table-hover table-sm mb-0">
                    <thead><tr><th>Gebruikersnaam</th><th>E-mail</th><th>Rol</th><th>Bands</th><th>Aangemaakt</th><th></th></tr></thead>
                    <tbody id="users-tbody"><tr><td colspan="6" class="text-muted">Laden...</td></tr></tbody>
                </table>
                </div>
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
            <div class="card">
                <div class="table-responsive">
                <table class="table table-dark table-hover table-sm mb-0">
                    <thead><tr><th>Bandnaam</th><th>Beschrijving</th><th>Leden</th><th class="text-center">Versleuteld</th><th></th></tr></thead>
                    <tbody id="bands-tbody"><tr><td colspan="5" class="text-muted">Laden...</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- PAYMENTS TAB -->
        <div class="tab-pane fade" id="tab-payments">
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="card-body py-2">
                            <div class="h4 mb-0" id="pay-active-subs">—</div>
                            <div class="text-muted small">Actieve abonnementen</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="card-body py-2">
                            <div class="h4 mb-0" id="pay-total">—</div>
                            <div class="text-muted small">Totaal ontvangen</div>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="mb-2">Abonnementen</h5>
            <div class="card mb-4">
                <div class="table-responsive">
                <table class="table table-dark table-hover table-sm mb-0">
                    <thead><tr><th>Gebruiker</th><th>Status</th><th>Bedrag</th><th>Interval</th><th>Proef</th><th>Volgende incasso</th><th>Korting</th><th>Mollie</th></tr></thead>
                    <tbody id="subs-tbody"><tr><td colspan="8" class="text-muted">Laden...</td></tr></tbody>
                </table>
                </div>
            </div>

            <h5 class="mb-2">Betalingen</h5>
            <div class="card">
                <div class="table-responsive">
                <table class="table table-dark table-hover table-sm mb-0">
                    <thead><tr><th>Datum</th><th>Gebruiker</th><th>Omschrijving</th><th>Bedrag</th><th>Status</th></tr></thead>
                    <tbody id="payments-tbody"><tr><td colspan="5" class="text-muted">Laden...</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- DISCOUNTS TAB -->
        <div class="tab-pane fade" id="tab-discounts">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Kortingscodes</h5>
                <button class="btn btn-danger btn-sm" onclick="openAddDiscount()">
                    <i class="bi bi-plus-lg"></i> Code toevoegen
                </button>
            </div>
            <p class="text-muted small">
                Codes worden in deze app beheerd; de korting wordt toegepast bij het
                aanmaken van een abonnement. Een actieve code met een geldige periode
                en (eventueel) onder de gebruikslimiet kan worden ingewisseld.
            </p>
            <div class="card">
                <div class="table-responsive">
                <table class="table table-dark table-hover table-sm mb-0">
                    <thead><tr><th>Code</th><th>Korting</th><th>Duur</th><th>Gebruikt</th><th>Geldig tot</th><th class="text-center">Actief</th><th></th></tr></thead>
                    <tbody id="discounts-tbody"><tr><td colspan="7" class="text-muted">Laden...</td></tr></tbody>
                </table>
                </div>
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

<!-- Gebruiker verwijderen: bevestiging -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Gebruiker verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="del-user-name"></strong> wilt verwijderen?
                <div class="small text-warning mt-2">
                    <i class="bi bi-exclamation-triangle"></i> Het account en alle bandlidmaatschappen worden verwijderd.
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="confirmDeleteUser()">Verwijderen</button>
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

<!-- Discount Modal -->
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="discountModalTitle">Kortingscode toevoegen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="disc-id">
                <div class="mb-3">
                    <label class="form-label">Code *</label>
                    <input type="text" id="disc-code" class="form-control text-uppercase" placeholder="WELKOM2026">
                    <div class="form-text text-muted">Hoofdletters en cijfers; spaties worden verwijderd.</div>
                </div>
                <div class="row">
                    <div class="col-7 mb-3">
                        <label class="form-label">Type</label>
                        <select id="disc-type" class="form-select" onchange="updateDiscUnit()">
                            <option value="percent">Percentage (%)</option>
                            <option value="fixed">Vast bedrag (€)</option>
                        </select>
                    </div>
                    <div class="col-5 mb-3">
                        <label class="form-label">Waarde *</label>
                        <div class="input-group">
                            <span class="input-group-text" id="disc-unit">%</span>
                            <input type="number" id="disc-value" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Duur</label>
                    <select id="disc-duration" class="form-select">
                        <option value="once">Eenmalig (alleen eerste incasso)</option>
                        <option value="forever">Doorlopend (hele looptijd)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Omschrijving</label>
                    <input type="text" id="disc-description" class="form-control" placeholder="Interne notitie (optioneel)">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Max. aantal keer</label>
                        <input type="number" id="disc-max" class="form-control" min="0" placeholder="onbeperkt">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Geldig tot</label>
                        <input type="date" id="disc-valid-until" class="form-control">
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="disc-active" checked>
                    <label class="form-check-label" for="disc-active">Actief</label>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="saveDiscount()">Opslaan</button>
            </div>
        </div>
    </div>
</div>

<!-- Kortingscode verwijderen: bevestiging -->
<div class="modal fade" id="deleteDiscountModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Code verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="del-disc-name"></strong> wilt verwijderen?
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="confirmDeleteDiscount()">Verwijderen</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
var _allUsers = [];
var _allBands = [];
var _myUserId = ' . (int)$user['id'] . ';

$(function() {
    loadUsers();
    loadBands();
    // Betalingen/kortingen pas laden zodra het bijbehorende tabblad wordt geopend.
    $(\'a[href="#tab-payments"]\').on("shown.bs.tab", loadPayments);
    $(\'a[href="#tab-discounts"]\').on("shown.bs.tab", loadDiscounts);
});

function fmtMoney(amount, currency) {
    var cur = currency || "EUR";
    try { return Number(amount).toLocaleString("nl-NL", { style: "currency", currency: cur }); }
    catch (e) { return cur + " " + Number(amount).toFixed(2); }
}

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
            + \'<td class="text-nowrap">\'
            + \'<button class="btn btn-xs btn-outline-secondary me-1" onclick="openEditUser(\' + u.id + \')" title="Bewerken"><i class="bi bi-pencil"></i></button>\'
            + (u.id === _myUserId
                ? \'\'
                : \'<button class="btn btn-xs btn-outline-danger" onclick="askDeleteUser(\' + u.id + \')" title="Verwijderen"><i class="bi bi-trash"></i></button>\')
            + \'</td>\'
            + \'</tr>\');
    });
}

var _delUserId = null;
function askDeleteUser(id) {
    var u = _allUsers.find(function(x){ return x.id === id; });
    _delUserId = id;
    $("#del-user-name").text(u ? u.username : "deze gebruiker");
    new bootstrap.Modal("#deleteUserModal").show();
}
function confirmDeleteUser() {
    if (!_delUserId) return;
    $.ajax({ url: "api/users.php", type: "DELETE", contentType: "application/json",
        data: JSON.stringify({ id: _delUserId }), dataType: "json",
        success: function(r) {
            bootstrap.Modal.getInstance("#deleteUserModal").hide();
            if (r.ok) { loadUsers(); loadBands(); }
            else alert(r.error || "Verwijderen mislukt");
        },
        error: function(xhr) { alert("Verwijderen mislukt (HTTP " + xhr.status + ")"); }
    });
}

function loadBands() {
    // Admin-paneel: álle bands (alleen om te kunnen verwijderen).
    $.get("api/bands.php", { all: 1 }, function(data) {
        _allBands = data.bands || [];
        renderBands(_allBands);
    });
}

function renderBands(bands) {
    var tb = $("#bands-tbody"); tb.empty();
    if (!bands.length) { tb.append(\'<tr><td colspan="5" class="text-muted">Nog geen bands</td></tr>\'); return; }
    bands.forEach(function(b) {
        var members = (b.members || []).map(function(m){ return escHtml(m.username); }).join(", ");
        var enc = (b.is_encrypted == 1)
            ? \'<i class="bi bi-shield-lock-fill text-success" title="End-to-end versleuteld"></i>\'
            : \'<span class="text-muted">—</span>\';
        tb.append(\'<tr>\'
            + \'<td class="fw-semibold">\' + escHtml(b.name) + \'</td>\'
            + \'<td class="text-muted small">\' + escHtml(b.description || "") + \'</td>\'
            + \'<td class="text-muted small">\' + (members || "—") + \'</td>\'
            + \'<td class="text-center">\' + enc + \'</td>\'
            + \'<td><button class="btn btn-xs btn-outline-danger" onclick="askDeleteBand(\' + b.id + \')" title="Band verwijderen"><i class="bi bi-trash"></i></button></td>\'
            + \'</tr>\');
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

// ── Betalingen ───────────────────────────────────────────────────────────────
var SUB_STATUS = {
    pending:  ["secondary", "In afwachting"],
    trialing: ["info text-dark", "Proefperiode"],
    active:   ["success", "Actief"],
    canceled: ["secondary", "Opgezegd"],
    suspended:["warning text-dark", "Onderbroken"]
};
var PAY_STATUS = {
    open:    ["secondary", "Open"],
    pending: ["secondary", "In behandeling"],
    paid:    ["success", "Betaald"],
    failed:  ["danger", "Mislukt"],
    canceled:["secondary", "Geannuleerd"],
    expired: ["secondary", "Verlopen"],
    refunded:["warning text-dark", "Terugbetaald"]
};
function statusBadge(map, key) {
    var s = map[key] || ["secondary", key || "—"];
    return \'<span class="badge bg-\' + s[0] + \'">\' + escHtml(s[1]) + \'</span>\';
}

function loadPayments() {
    $.get("api/payments.php", function(data) {
        if (!data.ok) return;
        $("#pay-active-subs").text(data.summary.active_subscriptions);
        $("#pay-total").text(fmtMoney(data.summary.total_paid, "EUR"));
        renderSubs(data.subscriptions || []);
        renderPaymentsList(data.payments || []);
    });
}

function renderSubs(subs) {
    var tb = $("#subs-tbody"); tb.empty();
    if (!subs.length) { tb.append(\'<tr><td colspan="8" class="text-muted">Nog geen abonnementen</td></tr>\'); return; }
    subs.forEach(function(s) {
        var proef = (s.status === "trialing" && s.trial_ends_at)
            ? "tot " + escHtml(s.trial_ends_at)
            : (s.trial_used == 1 ? "verbruikt" : "—");
        var ids = [];
        if (s.mollie_customer_id)     ids.push(escHtml(s.mollie_customer_id));
        if (s.mollie_subscription_id) ids.push(escHtml(s.mollie_subscription_id));
        if (s.mollie_mandate_id)      ids.push(escHtml(s.mollie_mandate_id));
        var mollie = ids.length ? ids.join("<br>") : "—";
        tb.append(\'<tr>\'
            + \'<td class="fw-semibold">\' + escHtml(s.username || "—") + \'<div class="text-muted small">\' + escHtml(s.email || "") + \'</div></td>\'
            + \'<td>\' + statusBadge(SUB_STATUS, s.status) + \'</td>\'
            + \'<td>\' + fmtMoney(s.amount, s.currency) + \'</td>\'
            + \'<td class="text-muted small">\' + escHtml(s.interval || "") + \'</td>\'
            + \'<td class="text-muted small">\' + proef + \'</td>\'
            + \'<td class="text-muted small">\' + escHtml(s.next_payment_at || "—") + \'</td>\'
            + \'<td class="text-muted small">\' + (s.discount_code ? escHtml(s.discount_code) : "—") + \'</td>\'
            + \'<td class="text-muted small font-monospace" style="font-size:0.7rem">\' + mollie + \'</td>\'
            + \'</tr>\');
    });
}

function renderPaymentsList(pays) {
    var tb = $("#payments-tbody"); tb.empty();
    if (!pays.length) { tb.append(\'<tr><td colspan="5" class="text-muted">Nog geen betalingen</td></tr>\'); return; }
    pays.forEach(function(p) {
        tb.append(\'<tr>\'
            + \'<td class="text-muted small">\' + escHtml(p.paid_at || p.created_at || "") + \'</td>\'
            + \'<td>\' + escHtml(p.username || "—") + \'</td>\'
            + \'<td class="text-muted small">\' + escHtml(p.description || "") + \'</td>\'
            + \'<td>\' + fmtMoney(p.amount, p.currency) + \'</td>\'
            + \'<td>\' + statusBadge(PAY_STATUS, p.status) + \'</td>\'
            + \'</tr>\');
    });
}

// ── Kortingscodes ────────────────────────────────────────────────────────────
var _allDiscounts = [];

function loadDiscounts() {
    $.get("api/discounts.php", function(data) {
        _allDiscounts = data.codes || [];
        renderDiscounts(_allDiscounts);
    });
}

function renderDiscounts(codes) {
    var tb = $("#discounts-tbody"); tb.empty();
    if (!codes.length) { tb.append(\'<tr><td colspan="7" class="text-muted">Nog geen codes</td></tr>\'); return; }
    codes.forEach(function(c) {
        var korting = c.type === "percent" ? (Number(c.value) + "%") : fmtMoney(c.value, "EUR");
        var duur = c.duration === "forever" ? "Doorlopend" : "Eenmalig";
        var gebruikt = c.times_redeemed + (c.max_redemptions != null ? " / " + c.max_redemptions : "");
        var actief = (c.active == 1)
            ? \'<i class="bi bi-check-circle-fill text-success"></i>\'
            : \'<span class="text-muted">—</span>\';
        tb.append(\'<tr>\'
            + \'<td class="fw-semibold font-monospace">\' + escHtml(c.code) + \'</td>\'
            + \'<td>\' + korting + \'</td>\'
            + \'<td class="text-muted small">\' + duur + \'</td>\'
            + \'<td class="text-muted small">\' + escHtml(gebruikt) + \'</td>\'
            + \'<td class="text-muted small">\' + escHtml(c.valid_until || "—") + \'</td>\'
            + \'<td class="text-center">\' + actief + \'</td>\'
            + \'<td class="text-nowrap">\'
            + \'<button class="btn btn-xs btn-outline-secondary me-1" onclick="openEditDiscount(\' + c.id + \')" title="Bewerken"><i class="bi bi-pencil"></i></button>\'
            + \'<button class="btn btn-xs btn-outline-danger" onclick="askDeleteDiscount(\' + c.id + \')" title="Verwijderen"><i class="bi bi-trash"></i></button>\'
            + \'</td>\'
            + \'</tr>\');
    });
}

function updateDiscUnit() {
    $("#disc-unit").text($("#disc-type").val() === "fixed" ? "€" : "%");
}

function openAddDiscount() {
    $("#discountModalTitle").text("Kortingscode toevoegen");
    $("#disc-id").val(""); $("#disc-code").val(""); $("#disc-type").val("percent");
    $("#disc-value").val(""); $("#disc-duration").val("once"); $("#disc-description").val("");
    $("#disc-max").val(""); $("#disc-valid-until").val(""); $("#disc-active").prop("checked", true);
    updateDiscUnit();
    new bootstrap.Modal("#discountModal").show();
}

function openEditDiscount(id) {
    var c = _allDiscounts.find(function(x) { return x.id === id; });
    if (!c) return;
    $("#discountModalTitle").text("Kortingscode bewerken");
    $("#disc-id").val(c.id); $("#disc-code").val(c.code); $("#disc-type").val(c.type);
    $("#disc-value").val(c.value); $("#disc-duration").val(c.duration);
    $("#disc-description").val(c.description || "");
    $("#disc-max").val(c.max_redemptions != null ? c.max_redemptions : "");
    $("#disc-valid-until").val(c.valid_until ? String(c.valid_until).substring(0, 10) : "");
    $("#disc-active").prop("checked", c.active == 1);
    updateDiscUnit();
    new bootstrap.Modal("#discountModal").show();
}

function saveDiscount() {
    var data = {
        id: $("#disc-id").val(),
        code: $("#disc-code").val().trim(),
        type: $("#disc-type").val(),
        value: $("#disc-value").val(),
        duration: $("#disc-duration").val(),
        description: $("#disc-description").val().trim(),
        max_redemptions: $("#disc-max").val(),
        valid_until: $("#disc-valid-until").val(),
        active: $("#disc-active").is(":checked") ? 1 : 0
    };
    if (!data.code) { alert("Code is verplicht."); return; }
    if (!(parseFloat(data.value) > 0)) { alert("Waarde moet groter dan 0 zijn."); return; }
    $.post("api/discounts.php", JSON.stringify(data), function(r) {
        if (r.ok) { bootstrap.Modal.getInstance("#discountModal").hide(); loadDiscounts(); }
        else { alert(r.error || "Fout"); }
    }, "json");
}

var _delDiscId = null;
function askDeleteDiscount(id) {
    var c = _allDiscounts.find(function(x) { return x.id === id; });
    _delDiscId = id;
    $("#del-disc-name").text(c ? c.code : "deze code");
    new bootstrap.Modal("#deleteDiscountModal").show();
}
function confirmDeleteDiscount() {
    if (!_delDiscId) return;
    $.ajax({ url: "api/discounts.php", type: "DELETE", contentType: "application/json",
        data: JSON.stringify({ id: _delDiscId }), dataType: "json",
        success: function(r) {
            bootstrap.Modal.getInstance("#deleteDiscountModal").hide();
            if (r.ok) loadDiscounts();
            else alert(r.error || "Verwijderen mislukt");
        },
        error: function(xhr) { alert("Verwijderen mislukt (HTTP " + xhr.status + ")"); }
    });
}
</script>';
require APP_ROOT . '/includes/footer.php';
?>
