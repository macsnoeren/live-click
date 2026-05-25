/* LiveGig — App helpers */

/* =========================================
   Duration helpers (dashboard + setlists)
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
   Offline-cache helpers (localStorage)
   ========================================= */
var LG_CACHE_VER = 1;

function _lgKey(type, bandId) {
    return 'lg_' + type + '_' + bandId + '_v' + LG_CACHE_VER;
}

function lgSave(type, bandId, data) {
    try {
        localStorage.setItem(_lgKey(type, bandId), JSON.stringify(data));
        localStorage.setItem('lg_ts_' + bandId, Date.now());
    } catch (e) { /* quota overschreden — geen actie */ }
}

function lgLoad(type, bandId) {
    try {
        var raw = localStorage.getItem(_lgKey(type, bandId));
        return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
}

function lgSetOffline(isOffline) {
    var el = document.getElementById('lg-offline-badge');
    if (!el) return;
    el.style.display = isOffline ? '' : 'none';
    if (isOffline) {
        var tsEl = document.getElementById('lg-cache-ts');
        if (tsEl) {
            var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
            var ts = bandId ? localStorage.getItem('lg_ts_' + bandId) : null;
            if (ts) {
                var d = new Date(parseInt(ts, 10));
                tsEl.textContent = '— cache van '
                    + d.toLocaleDateString('nl', { day: 'numeric', month: 'short' })
                    + ' ' + d.toLocaleTimeString('nl', { hour: '2-digit', minute: '2-digit' });
            } else {
                tsEl.textContent = '';
            }
        }
    }
}

/* =========================================
   In-memory song index (id → song object)
   Gevuld vanuit zowel songs- als setlists-cache.
   Hierdoor hoeft de onclick alleen het id te bevatten —
   geen grote drum_svg inline in de HTML.
   ========================================= */
var _allSongsCache = null;
var _songById      = {};

function _indexSongs(songs) {
    (songs || []).forEach(function(s) { _songById[s.id] = s; });
}

/** Selecteer een nummer via zijn id (opgezocht in _songById). */
function selectSongById(idOrEl) {
    var id = (typeof idOrEl === 'object')
           ? parseInt(idOrEl.dataset.id, 10)
           : parseInt(idOrEl, 10);
    var song = _songById[id];
    if (song) { selectSong(song); return; }
    // Fallback: toon waarschuwing (zou niet mogen voorkomen)
    console.warn('selectSongById: nummer', id, 'niet gevonden in cache');
}

/* =========================================
   Dashboard: alle nummers
   ========================================= */
function loadAllSongs() {
    var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
    if (!bandId) {
        $('#all-songs').html('<div class="list-group-item text-muted small">Je bent nog niet aan een band gekoppeld.</div>');
        return;
    }

    // Toon gecachede data meteen (werkt ook zonder netwerk)
    var cached = lgLoad('songs', bandId);
    if (cached) {
        _allSongsCache = cached;
        _indexSongs(cached);
        renderAllSongs(cached);
    }

    // Haal verse data op op de achtergrond
    $.get('api/songs.php?band_id=' + bandId)
        .done(function(data) {
            _allSongsCache = data.songs || [];
            lgSave('songs', bandId, _allSongsCache);
            _indexSongs(_allSongsCache);
            renderAllSongs(_allSongsCache);
            lgSetOffline(false);
        })
        .fail(function() {
            if (!cached) {
                $('#all-songs').html(
                    '<div class="list-group-item text-danger small">' +
                    '<i class="bi bi-wifi-off me-1"></i>Geen verbinding en geen lokale cache.</div>'
                );
            }
            lgSetOffline(true);
        });
}

function renderAllSongs(songs) {
    var c = $('#all-songs');
    c.empty();
    if (!songs.length) {
        c.append('<div class="list-group-item text-muted">Geen nummers gevonden</div>');
        return;
    }
    songs.forEach(function(s) {
        c.append(
            '<button class="list-group-item list-group-item-action list-group-item-dark' +
            ' d-flex justify-content-between align-items-center py-2"' +
            ' data-id="' + s.id + '" onclick="selectSongById(this)">' +
            '<span>' +
            '<span class="fw-semibold">'        + escHtml(s.title)  + '</span>' +
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
   Dashboard: setlist dropdown
   ========================================= */
function loadSetlistDropdown() {
    var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
    if (!bandId) return;

    // Toon gecachede data meteen
    var cached = lgLoad('setlists', bandId);
    if (cached) {
        _renderSetlistOptions(cached);
        // Index de nummers uit de setlists voor offline gebruik
        cached.forEach(function(sl) { _indexSongs(sl.songs || []); });
    }

    // Haal verse data op op de achtergrond
    $.get('api/setlists.php?band_id=' + bandId)
        .done(function(data) {
            var lists = data.setlists || [];
            lgSave('setlists', bandId, lists);
            _renderSetlistOptions(lists);
            lists.forEach(function(sl) { _indexSongs(sl.songs || []); });
            lgSetOffline(false);
        })
        .fail(function() {
            lgSetOffline(true);
        });
}

function _renderSetlistOptions(lists) {
    // Nav-dropdown
    var menu = $('#setlist-dropdown');
    if (menu.length) {
        menu.empty();
        if (!lists.length) {
            menu.append('<li><a class="dropdown-item text-muted" href="#">Nog geen setlists</a></li>');
        } else {
            lists.forEach(function(sl) {
                menu.append(
                    '<li><a class="dropdown-item" href="#"' +
                    ' onclick="loadSetlist(' + sl.id + ');return false;">' +
                    escHtml(sl.name) + '</a></li>'
                );
            });
        }
    }

    // Dashboard select
    var sel = $('#setlist-select');
    if (sel.length) {
        var curVal = sel.val();
        sel.find('option:not(:first)').remove();
        lists.forEach(function(sl) {
            sel.append('<option value="' + sl.id + '">' + escHtml(sl.name) + '</option>');
        });
        if (curVal) sel.val(curVal);
    }
}

/* =========================================
   Dashboard: laad setlist in linker paneel
   ========================================= */
function loadSetlist(id) {
    id = parseInt(id, 10);
    if (!id) return;

    // Probeer cache eerst — werkt ook offline
    var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
    if (bandId) {
        var cached = lgLoad('setlists', bandId);
        if (cached) {
            for (var i = 0; i < cached.length; i++) {
                if (cached[i].id === id) { _applySetlist(cached[i]); return; }
            }
        }
    }

    // Fallback naar API (vereist netwerk)
    $.get('api/setlists.php?id=' + id, function(data) {
        if (data.setlist) _applySetlist(data.setlist);
    });
}

function _applySetlist(sl) {
    var songEl = document.getElementById('ct-song');
    if (songEl) songEl.textContent = sl.name;
    renderSetlistPanel(sl.songs || []);
}

function renderSetlistPanel(songs) {
    var c = $('#setlist-songs');
    c.empty();

    var dur   = calcSetlistDuration(songs);
    var badge = $('#sl-dash-dur');
    if (badge.length && songs.length) {
        var durTxt  = (dur.estimated ? '~' : '') + fmtSecs(dur.totalSecs);
        var estNote = dur.estimated
            ? ' <i class="bi bi-dash-circle text-warning ms-1" title="'
              + dur.estimated + ' nummer(s) zonder duur, geschat op gemiddeld '
              + fmtSecs(dur.avg) + '"></i>'
            : '';
        badge.html('<i class="bi bi-clock me-1"></i>' + durTxt + estNote).show();
    } else if (badge.length) {
        badge.hide();
    }

    if (!songs.length) {
        c.append('<div class="list-group-item text-muted">Lege setlist</div>');
        return;
    }
    songs.forEach(function(s, i) {
        // Index elk nummer zodat selectSongById het kan vinden
        _songById[s.id] = s;

        c.append(
            '<button class="list-group-item list-group-item-action list-group-item-dark' +
            ' d-flex justify-content-between align-items-center py-2"' +
            ' data-id="' + s.id + '" onclick="selectSongById(this)">' +
            '<span>' +
            '<span class="text-muted me-2">'   + (i + 1)          + '.</span>' +
            '<span class="fw-semibold">'        + escHtml(s.title)  + '</span>' +
            '<span class="text-muted small ms-2">' + escHtml(s.artist) + '</span>' +
            '</span>' +
            '<span class="bpm-badge">' + (s.bpm || '--') + '</span>' +
            '</button>'
        );
    });
}
