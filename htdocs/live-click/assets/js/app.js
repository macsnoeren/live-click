/* LiveGig — App helpers */

/* =========================================
   Duration helpers (used on dashboard + setlists)
   ========================================= */
function parseDurSecs(str) {
    if (!str) return null;
    var p = str.split(':');
    if (p.length !== 2) return null;
    var m = parseInt(p[0], 10), s = parseInt(p[1], 10);
    if (isNaN(m) || isNaN(s)) return null;
    return m * 60 + s;
}

function calcSetlistDuration(songs) {
    var knownSecs = [], unknown = 0;
    songs.forEach(function(s) {
        var secs = parseDurSecs(s.duration);
        if (secs !== null) knownSecs.push(secs);
        else unknown++;
    });
    var total = knownSecs.reduce(function(a, b) { return a + b; }, 0);
    var avg = knownSecs.length ? Math.round(total / knownSecs.length) : 0;
    total += unknown * avg;
    return { totalSecs: total, estimated: unknown, avg: avg, known: knownSecs.length };
}

function fmtSecs(secs) {
    var m = Math.floor(secs / 60), s = secs % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
}

function escHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* =========================================
   Dashboard: all songs list
   ========================================= */
var _allSongsCache = null;

function loadAllSongs() {
    var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
    if (!bandId) {
        $('#all-songs').html('<div class="list-group-item text-muted small">Je bent nog niet aan een band gekoppeld.</div>');
        return;
    }
    $.get('api/songs.php?band_id=' + bandId, function(data) {
        _allSongsCache = data.songs || [];
        renderAllSongs(_allSongsCache);
    });
}

function renderAllSongs(songs) {
    var c = $('#all-songs');
    c.empty();
    if (!songs.length) { c.append('<div class="list-group-item text-muted">Geen nummers gevonden</div>'); return; }
    songs.forEach(function(s) {
        c.append(
            '<button class="list-group-item list-group-item-action list-group-item-dark d-flex justify-content-between align-items-center py-2" ' +
            'data-id="' + s.id + '" onclick=\'selectSong(' + JSON.stringify(s) + ')\'>' +
            '<span>' +
            '<span class="fw-semibold">' + escHtml(s.title) + '</span>' +
            '<span class="text-muted small ms-2">' + escHtml(s.artist) + '</span>' +
            (s.starts ? '<span class="text-muted small ms-2">▶ ' + escHtml(s.starts) + '</span>' : '') +
            '</span>' +
            '<span class="bpm-badge">' + (s.bpm || '--') + '</span>' +
            '</button>'
        );
    });
}

function filterSongs(q) {
    if (!_allSongsCache) return;
    q = (q || '').toLowerCase();
    var filtered = _allSongsCache.filter(function(s) {
        return !q || s.title.toLowerCase().includes(q) || s.artist.toLowerCase().includes(q);
    });
    renderAllSongs(filtered);
}

/* =========================================
   Dashboard: setlist dropdown in header
   ========================================= */
function loadSetlistDropdown() {
    var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
    if (!bandId) return;
    $.get('api/setlists.php?band_id=' + bandId, function(data) {
        var lists = data.setlists || [];
        var menu = $('#setlist-dropdown');
        menu.empty();
        if (!lists.length) {
            menu.append('<li><a class="dropdown-item text-muted" href="#">Nog geen setlists</a></li>');
            return;
        }
        lists.forEach(function(sl) {
            menu.append('<li><a class="dropdown-item" href="#" onclick="loadSetlist(' + sl.id + ');return false;">' + escHtml(sl.name) + '</a></li>');
        });

        // Also populate dashboard select if present
        var sel = $('#setlist-select');
        if (sel.length) {
            lists.forEach(function(sl) {
                sel.append('<option value="' + sl.id + '">' + escHtml(sl.name) + '</option>');
            });
        }
    });
}

/* =========================================
   Dashboard: load setlist into left panel
   ========================================= */
function loadSetlist(id) {
    $.get('api/setlists.php?id=' + id, function(data) {
        var sl = data.setlist;
        if (!sl) return;

        // Update header dropdown label
        var songEl = document.getElementById('ct-song');
        if (songEl) songEl.textContent = sl.name;

        renderSetlistPanel(sl.songs || []);
    });
}

function renderSetlistPanel(songs) {
    var c = $('#setlist-songs');
    c.empty();

    var dur = calcSetlistDuration(songs);
    var badge = $('#sl-dash-dur');
    if (badge.length && songs.length) {
        var durTxt = (dur.estimated ? '~' : '') + fmtSecs(dur.totalSecs);
        var estNote = dur.estimated
            ? ' <i class="bi bi-dash-circle text-warning ms-1" title="' + dur.estimated + ' nummer(s) zonder duur, geschat op gemiddeld ' + fmtSecs(dur.avg) + '"></i>'
            : '';
        badge.html('<i class="bi bi-clock me-1"></i>' + durTxt + estNote).show();
    } else if (badge.length) {
        badge.hide();
    }

    if (!songs.length) { c.append('<div class="list-group-item text-muted">Lege setlist</div>'); return; }
    songs.forEach(function(s, i) {
        c.append(
            '<button class="list-group-item list-group-item-action list-group-item-dark d-flex justify-content-between align-items-center py-2" ' +
            'data-id="' + s.id + '" onclick=\'selectSong(' + JSON.stringify(s) + ')\'>' +
            '<span>' +
            '<span class="text-muted me-2">' + (i + 1) + '.</span>' +
            '<span class="fw-semibold">' + escHtml(s.title) + '</span>' +
            '<span class="text-muted small ms-2">' + escHtml(s.artist) + '</span>' +
            '</span>' +
            '<span class="bpm-badge">' + (s.bpm || '--') + '</span>' +
            '</button>'
        );
    });
}
