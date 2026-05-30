/* LiveGig Click Track Engine */
var _ct = {
    bpm:          80,
    running:      false,
    counter:      0,
    autoStop:     25,
    autoEnabled:  true,
    soundEnabled: false,
    autoTimer:    null,
    numBeats:     4,
};

/* ── Audio — volledig gesynthetiseerd, geen extern bestand nodig ── */
var _audioCtx = null;

function ctInitAudio() {
    if (_audioCtx) return;
    _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
}

/* ── Web Audio lookahead-scheduler ────────────────────────────────────
 *
 * Aanpak (Chris Wilson / Google): plan beats 100 ms vooruit in de
 * AudioContext-klok. Die klok loopt in een aparte audio-thread en
 * heeft sub-milliseconde nauwkeurigheid, ongeacht JS-load of
 * browser-throttling.
 *
 * De scheduler zelf draait op setTimeout(25 ms) — alleen om te kijken
 * of er nieuwe beats gepland moeten worden. Er is GEEN spin-wait meer.
 *
 * Visuele dots: setTimeout afgestemd op AudioContext-tijd, dus oog en
 * oor lopen synchroon.
 * ────────────────────────────────────────────────────────────────── */
var _nextBeatTime = 0;    // AudioContext-tijd van de eerstvolgende beat
var _nextBeatIdx  = 0;    // Beat-index (0–3) van de eerstvolgende beat
var _schedTimer   = null;
var CT_LOOKAHEAD  = 0.10; // 100 ms vooruit plannen (seconden)
var CT_SCHED_INT  = 25;   // Scheduler-interval (ms)

function ctStart(bpm) {
    ctInitAudio();
    if (_audioCtx.state === 'suspended') _audioCtx.resume();
    if (bpm) _ct.bpm = parseInt(bpm, 10);
    if (_ct.running) return;

    _ct.running   = true;
    _ct.counter   = 0;
    _nextBeatIdx  = 0;
    _nextBeatTime = _audioCtx.currentTime; // eerste beat direct
    ctUpdateBpmDisplay();

    if (_ct.autoEnabled) {
        _ct.autoTimer = setTimeout(ctStop, _ct.autoStop * 1000);
    }
    _ctSchedule();
}

function ctStop() {
    _ct.running = false;
    _ct.counter = 0;
    if (_schedTimer)   { clearTimeout(_schedTimer);   _schedTimer   = null; }
    if (_ct.autoTimer) { clearTimeout(_ct.autoTimer); _ct.autoTimer = null; }
    ctResetDots();
    var el = document.getElementById('ct-bpm');
    if (el) el.textContent = '-- BPM';
}

function _ctSchedule() {
    if (!_ct.running || !_audioCtx) return;

    var now      = _audioCtx.currentTime;
    var beatSecs = 60.0 / _ct.bpm;

    // Veiligheid: als de AudioContext lang gesuspend was (bijv. na
    // achtergrondtabblad of schermvergrendeling), reset naar nu.
    if (_nextBeatTime < now - 1.0) {
        _nextBeatTime = now;
        _nextBeatIdx  = 0;
    }

    while (_nextBeatTime < now + CT_LOOKAHEAD) {
        var beatIdx  = _nextBeatIdx;
        var beatTime = _nextBeatTime;

        /* Audio: gepland op de exacte AudioContext-tijd (sample-accuraat) */
        if (_ct.soundEnabled) {
            _ctScheduleClick(beatIdx, beatTime);
        }

        /* Visueel: setTimeout afgestemd op de AudioContext-klok */
        var msUntil = Math.max(0, (beatTime - now) * 1000);
        (function(idx) {
            setTimeout(function() {
                if (!_ct.running) return;
                ctResetDots();
                ctLightDot(idx);
                _ct.counter = idx;
            }, msUntil);
        })(beatIdx);

        _nextBeatTime += beatSecs;
        _nextBeatIdx   = (_nextBeatIdx + 1) % _ct.numBeats;
    }

    _schedTimer = setTimeout(_ctSchedule, CT_SCHED_INT);
}

/* Plant één klik op een exact tijdstip in de AudioContext-klok */
function _ctScheduleClick(beatIdx, time) {
    var freq = (beatIdx === 0) ? 1000 : 700; // beat 1 = accent
    var dur  = 0.055;
    var osc  = _audioCtx.createOscillator();
    var gain = _audioCtx.createGain();
    osc.connect(gain);
    gain.connect(_audioCtx.destination);
    osc.type = 'sine';
    osc.frequency.setValueAtTime(freq, time);
    gain.gain.setValueAtTime(1.0,   time);
    gain.gain.exponentialRampToValueAtTime(0.001, time + dur);
    osc.start(time);
    osc.stop(time + dur);
}

function ctResetDots() {
    for (var i = 1; i <= 4; i++) {
        var d = document.getElementById('beat_' + i);
        if (d) d.classList.remove('lit');
    }
}

function ctLightDot(idx) {
    var d = document.getElementById('beat_' + (idx + 1));
    if (d) d.classList.add('lit');
}

function ctUpdateBpmDisplay() {
    var el = document.getElementById('ct-bpm');
    if (el) el.textContent = _ct.bpm + ' BPM';
}

function ctToggleAuto() {
    _ct.autoEnabled = document.getElementById('ct-automode').checked;
}

function ctToggleSound() {
    _ct.soundEnabled = document.getElementById('ct-soundmode').checked;
    if (_ct.soundEnabled) ctInitAudio();
}

/* ── Pas de drum-SVG passend in het drum-tabblad ──
 * Wordt aangeroepen na het injecteren van de SVG, bij het openen van het
 * drum-tabblad (shown.bs.tab) en bij resize. Meet de beschikbare hoogte van
 * het tabpaneel; als het tabblad nog verborgen is (clientHeight 0) blijft de
 * natuurlijke hoogte staan en sizen we opnieuw zodra het zichtbaar wordt. */
function fitDrumSvg() {
    var pane = document.getElementById('tab-drum');
    var svg  = pane ? pane.querySelector('svg') : null;
    if (!pane || !svg) return;

    var natH   = parseInt(svg.getAttribute('data-natural-h') || '0', 10);
    var cs     = getComputedStyle(pane);
    var avail  = pane.clientHeight
               - (parseFloat(cs.paddingTop)    || 0)
               - (parseFloat(cs.paddingBottom) || 0);

    if (natH > 0) {
        svg.style.height = (avail > 40 && natH > avail)
            ? Math.max(60, Math.floor(avail)) + 'px'
            : natH + 'px';
    }
    svg.style.width = 'auto';
}

/* Id van het momenteel geselecteerde nummer (gebruikt door fetchLyrics). */
var _currentSongId = null;
/* Id van het nummer waarvan een PDF beschikbaar is (null = geen). */
var _currentPdf = null;

/* ── PDF-tab: laat de placeholder/iframe zien voor dit nummer ── */
function _renderPdf(song) {
    var frame = document.getElementById('detail-pdf-frame');
    var empty = document.getElementById('detail-pdf-empty');
    if (!frame) return;

    frame.removeAttribute('src');
    frame.style.display = 'none';

    if (song && song.pdf_path) {
        _currentPdf = song.id;
        if (empty) empty.style.display = 'none';
        // Direct laden als het PDF-tabblad al open staat, anders lui via shown.bs.tab
        var btn = document.getElementById('tab-pdf-btn');
        if (btn && btn.classList.contains('active')) loadPdfFrame();
    } else {
        _currentPdf = null;
        if (empty) {
            var lbl = empty.querySelector('span');
            if (lbl) lbl.textContent = song ? 'Geen PDF voor dit nummer' : 'Selecteer een nummer';
            empty.style.display = '';
        }
    }
}

/* Laadt de PDF in de iframe (1x, lui). Aangeroepen bij openen PDF-tab. */
function loadPdfFrame() {
    var frame = document.getElementById('detail-pdf-frame');
    var empty = document.getElementById('detail-pdf-empty');
    if (!frame || !_currentPdf) return;
    if (!frame.getAttribute('src')) {
        frame.src = 'api/pdf.php?song_id=' + _currentPdf;
        frame.style.display = '';
        if (empty) empty.style.display = 'none';
    }
}

/* ── Songtekst-tab vullen / lege staat tonen ── */
function _renderLyrics(song) {
    var el    = document.getElementById('detail-lyrics');
    var empty = document.getElementById('detail-lyrics-empty');
    var msg   = document.getElementById('detail-lyrics-msg');
    var btn   = document.getElementById('detail-lyrics-fetch');
    if (!el) return;

    if (song && song.lyrics && song.lyrics.trim()) {
        el.textContent   = song.lyrics;
        el.style.display = '';
        if (empty) empty.style.display = 'none';
    } else {
        el.style.display = 'none';
        if (empty) empty.style.display = '';
        if (msg) msg.textContent = song ? 'Nog geen songtekst voor dit nummer' : 'Selecteer een nummer';
        if (btn) { btn.style.display = song ? '' : 'none'; btn.disabled = false; }
    }
}

/* ── Selecteer een nummer (vanuit setlist of "Alle Nummers") ── */
function selectSong(song) {
    _ct.bpm = parseInt(song.bpm, 10) || _ct.bpm;
    _currentSongId = song.id;

    // Navigatiebalk (header): titel — artiest. BPM volgt via de click track.
    var songEl = document.getElementById('ct-song');
    if (songEl) songEl.textContent = song.title + (song.artist ? ' — ' + song.artist : '');

    // Songtekst-tab
    _renderLyrics(song);

    // Akkoorden-tab
    var chordsEl    = document.getElementById('detail-chords');
    var chordsEmpty = document.getElementById('detail-chords-empty');
    if (chordsEl) {
        if (song.chords && song.chords.trim()) {
            chordsEl.textContent   = song.chords;
            chordsEl.style.display = '';
            if (chordsEmpty) chordsEmpty.style.display = 'none';
        } else {
            chordsEl.style.display = 'none';
            if (chordsEmpty) chordsEmpty.style.display = '';
        }
    }

    // PDF-tab (bladmuziek)
    _renderPdf(song);

    // Notities-tab
    var descEl    = document.getElementById('detail-desc');
    var descEmpty = document.getElementById('detail-notes-empty');
    if (descEl) {
        if (song.description && song.description.trim()) {
            descEl.textContent   = song.description;
            descEl.style.display = '';
            if (descEmpty) descEmpty.style.display = 'none';
        } else {
            descEl.style.display = 'none';
            if (descEmpty) {
                var lbl = descEmpty.querySelector('span');
                if (lbl) lbl.textContent = 'Geen notities';
                descEmpty.style.display = '';
            }
        }
    }

    // Drumstructuur-tab
    var drumDiv   = document.getElementById('detail-drum');
    var drumEmpty = document.getElementById('detail-drum-empty');
    if (drumDiv) {
        drumDiv.innerHTML     = '';
        drumDiv.style.display = 'none';
        if (drumEmpty) drumEmpty.style.display = '';

        if (song.drum_svg) {
            // SVG zit in de cache — geen netwerk nodig.
            drumDiv.innerHTML     = song.drum_svg;
            drumDiv.style.display = '';
            if (drumEmpty) drumEmpty.style.display = 'none';
            fitDrumSvg();
        } else if (song.drum_notation) {
            // Fallback: genereer via server (vereist internetverbinding).
            $.ajax({
                url: 'api/drum_preview.php', type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ notation: song.drum_notation }),
                dataType: 'json',
                success: function(r) {
                    if (!r.ok || !r.svg) return;
                    drumDiv.innerHTML     = r.svg;
                    drumDiv.style.display = '';
                    if (drumEmpty) drumEmpty.style.display = 'none';
                    fitDrumSvg();
                }
            });
        }
    }

    // Click track starten op het BPM van dit nummer
    ctStop();
    setTimeout(function() { ctStart(song.bpm); }, 50);

    // Markeer actief nummer in de lijst
    document.querySelectorAll('#setlist-songs .list-group-item').forEach(function(el) {
        el.classList.remove('selected');
    });
    var active = document.querySelector('#setlist-songs [data-id="' + song.id + '"]');
    if (active) active.classList.add('selected');
}
