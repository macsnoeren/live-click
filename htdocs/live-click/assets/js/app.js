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
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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
    if (typeof window.updateLsUsage === 'function') window.updateLsUsage();
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
   Songtekst ophalen (lyrics.ovh, via api/lyrics.php) en opslaan.
   Wordt aangeroepen vanuit de knop in de Tekst-tab.
   ========================================= */
function fetchLyrics() {
    var id = _currentSongId;
    if (!id) return;

    var btn = document.getElementById('detail-lyrics-fetch');
    var msg = document.getElementById('detail-lyrics-msg');
    if (btn) btn.disabled = true;
    if (msg) msg.textContent = 'Songtekst ophalen...';

    $.ajax({
        url: 'api/lyrics.php', type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ song_id: id }),
        dataType: 'json'
    }).done(function(r) {
        if (r.ok && r.lyrics) {
            // Bijwerken in geheugen + offline-cache
            if (_songById[id]) _songById[id].lyrics = r.lyrics;
            var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
            if (_allSongsCache) {
                for (var i = 0; i < _allSongsCache.length; i++) {
                    if (_allSongsCache[i].id == id) _allSongsCache[i].lyrics = r.lyrics;
                }
                if (bandId) lgSave('songs', bandId, _allSongsCache);
            }
            // Alleen de tab verversen als dit nog steeds het actieve nummer is
            if (_currentSongId == id) _renderLyrics(_songById[id] || { lyrics: r.lyrics });
        } else {
            if (msg) msg.textContent = r.error || 'Geen songtekst gevonden.';
            if (btn) btn.disabled = false;
        }
    }).fail(function(xhr) {
        if (msg) msg.textContent = (xhr.status === 0)
            ? 'Geen verbinding — songtekst kan niet worden opgehaald.'
            : 'Ophalen mislukt (HTTP ' + xhr.status + ').';
        if (btn) btn.disabled = false;
    });
}

/* =========================================
   Dashboard: alle nummers (vult de cache)
   Wordt getoond als de virtuele setlist "Alle Nummers".
   ========================================= */
function loadAllSongs() {
    var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
    if (!bandId) return;

    // Toon gecachede data meteen (werkt ook zonder netwerk)
    var cached = lgLoad('songs', bandId);
    if (cached) {
        _allSongsCache = cached;
        _indexSongs(cached);
        if (_panelIsAll) showAllSongs();
    }

    // Haal verse data op op de achtergrond
    $.get('api/songs.php?band_id=' + bandId)
        .done(function(data) {
            _allSongsCache = data.songs || [];
            lgSave('songs', bandId, _allSongsCache);
            _indexSongs(_allSongsCache);
            if (_panelIsAll) showAllSongs();
            lgSetOffline(false);
        })
        .fail(function() {
            if (!cached && _panelIsAll) {
                $('#setlist-songs').html(
                    '<div class="list-group-item text-danger small">' +
                    '<i class="bi bi-wifi-off me-1"></i>Geen verbinding en geen lokale cache.</div>'
                );
            }
            lgSetOffline(true);
        });
}

/* Toon alle nummers (alfabetisch) als virtuele setlist in het linker paneel. */
function showAllSongs() {
    _panelIsAll = true;
    var sel = document.getElementById('setlist-select');
    if (sel) sel.value = 'all';

    // Nog niets geladen — toon "Laden..." (loadAllSongs roept ons opnieuw aan).
    if (_allSongsCache === null) {
        $('#sl-dash-dur').hide();
        $('#setlist-songs').html('<div class="list-group-item text-muted">Laden...</div>');
        return;
    }

    var songs = _allSongsCache.slice().sort(function(a, b) {
        return (a.title || '').localeCompare(b.title || '', undefined, { sensitivity: 'base' });
    });
    renderSetlistPanel(songs);
}

/* =========================================
   Dashboard: setlist dropdown
   ========================================= */
function _sortSetlists(lists) {
    lists.sort(function(a, b) {
        return a.name.localeCompare(b.name, undefined, {sensitivity: 'base'});
    });
}

function loadSetlistDropdown() {
    var bandId = typeof BAND_ID !== 'undefined' ? BAND_ID : null;
    if (!bandId) return;

    // Toon gecachede data meteen
    var cached = lgLoad('setlists', bandId);
    if (cached) {
        _sortSetlists(cached);
        _renderSetlistOptions(cached);
        // Index de nummers uit de setlists voor offline gebruik
        cached.forEach(function(sl) { _indexSongs(sl.songs || []); });
    }

    // Haal verse data op op de achtergrond
    $.get('api/setlists.php?band_id=' + bandId)
        .done(function(data) {
            var lists = data.setlists || [];
            _sortSetlists(lists);
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
    // Nav-dropdown — met "Alle Nummers" bovenaan
    var menu = $('#setlist-dropdown');
    if (menu.length) {
        menu.empty();
        menu.append(
            '<li><a class="dropdown-item" href="#"' +
            ' onclick="loadSetlist(\'all\');return false;">' +
            '<i class="bi bi-collection me-1"></i>Alle Nummers</a></li>'
        );
        if (lists.length) {
            menu.append('<li><hr class="dropdown-divider"></li>');
            lists.forEach(function(sl) {
                menu.append(
                    '<li><a class="dropdown-item" href="#"' +
                    ' onclick="loadSetlist(' + sl.id + ');return false;">' +
                    escHtml(sl.name) + '</a></li>'
                );
            });
        }
    }

    // Dashboard select — eerste optie ("Alle Nummers") blijft staan
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
    // Virtuele setlist "Alle Nummers"
    if (id === 'all') { showAllSongs(); return; }

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
    _panelIsAll = false;
    var sel = document.getElementById('setlist-select');
    if (sel) sel.value = sl.id;
    var songEl = document.getElementById('ct-song');
    if (songEl) songEl.textContent = sl.name;
    renderSetlistPanel(sl.songs || []);
}

/* Het momenteel getoonde nummeroverzicht (setlist of "Alle Nummers"). */
var _panelSongs = [];
var _panelIsAll = true;

function renderSetlistPanel(songs) {
    _panelSongs = songs || [];

    // Duur-badge: alleen tonen bij een echte setlist, niet bij "Alle Nummers"
    var badge = $('#sl-dash-dur');
    if (badge.length && _panelSongs.length && !_panelIsAll) {
        var dur     = calcSetlistDuration(_panelSongs);
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

    // Zoekveld leegmaken bij wisselen van lijst
    var search = document.getElementById('dash-search');
    if (search) search.value = '';

    _renderPanelItems(_panelSongs);
}

/* Tekent de nummerknoppen in het linker paneel (zonder de badge/zoek te resetten). */
function _renderPanelItems(songs) {
    var c = $('#setlist-songs');
    c.empty();

    if (!songs.length) {
        c.append('<div class="list-group-item text-muted">' +
                 (_panelIsAll ? 'Geen nummers gevonden' : 'Lege setlist') + '</div>');
        return;
    }

    songs.forEach(function(s, i) {
        // Index elk nummer zodat selectSongById het kan vinden
        _songById[s.id] = s;

        var numHtml = _panelIsAll ? '' : '<span class="sl-num">' + (i + 1) + '.</span>';

        var startsRow = s.starts
            ? '<div class="sl-starts"><i class="bi bi-play-fill"></i>' + escHtml(s.starts) + '</div>'
            : '';

        c.append(
            '<button class="list-group-item list-group-item-action list-group-item-dark sl-item"' +
            ' data-id="' + s.id + '" onclick="selectSongById(this)">' +
            '<span class="sl-main">' +
            '<span class="sl-title-row">' +
            numHtml +
            '<span class="sl-title">'  + escHtml(s.title)  + '</span>' +
            '<span class="sl-artist">' + escHtml(s.artist) + '</span>' +
            '</span>' +
            startsRow +
            '</span>' +
            '<span class="bpm-badge">' + (s.bpm || '--') + '</span>' +
            '</button>'
        );
    });
}

/* Filtert het huidige paneel (setlist of "Alle Nummers") op titel/artiest. */
function filterPanel(q) {
    q = (q || '').toLowerCase().trim();
    if (!q) { _renderPanelItems(_panelSongs); return; }
    var filtered = _panelSongs.filter(function(s) {
        return (s.title  && s.title.toLowerCase().includes(q)) ||
               (s.artist && s.artist.toLowerCase().includes(q));
    });
    _renderPanelItems(filtered);
}
