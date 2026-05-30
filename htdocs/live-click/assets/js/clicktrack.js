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

/* ── Hulpfunctie: pas de SVG-grootte aan na het injecteren ── */
function _applySvgSizing(drumDiv, toggleBtn, drumWrap, drumChevron) {
    var svg = drumDiv ? drumDiv.querySelector('svg') : null;
    if (!svg) return;

    var natH     = parseInt(svg.getAttribute('data-natural-h') || '0', 10);
    var isTablet = window.matchMedia('(max-width: 991.98px)').matches;

    if (isTablet) {
        // Tablet: kolom scrollt mee — gebruik de natuurlijke hoogte, nooit afknippen.
        if (natH > 0) svg.style.height = natH + 'px';
        svg.style.width = 'auto';
    } else {
        // Desktop: rechterkolom heeft vaste hoogte — bereken beschikbare ruimte.
        // Meet VOORDAT drumWrap zichtbaar is, zodat de kaart nog niet vergroot is.
        var navH      = parseInt(getComputedStyle(document.documentElement)
                            .getPropertyValue('--nav-h')) || 0;
        var rightCol  = document.querySelector('.db-col-right');
        var detCard   = document.getElementById('song-detail-card');
        var colH      = rightCol ? rightCol.clientHeight : (window.innerHeight - navH);
        var fixedDetH = detCard  ? detCard.offsetHeight  : 0;
        var togH      = toggleBtn ? toggleBtn.offsetHeight : 28;
        var minSongs  = 120; // minimaal zichtbaar gedeelte van de songlijst
        var padding   = 40;  // gaps + drumDiv-padding + kaartborders

        var available = colH - fixedDetH - togH - minSongs - padding;

        if (natH > 0 && available > 0 && natH > available) {
            svg.style.height = Math.max(60, available) + 'px';
        } else if (natH > 0) {
            svg.style.height = natH + 'px';
        }
        svg.style.width = 'auto';
    }

    // Toon sectie geopend
    drumDiv.style.display = '';
    if (drumWrap)    drumWrap.style.display  = '';
    if (drumChevron) drumChevron.className   = 'bi bi-chevron-down';
    if (toggleBtn)   toggleBtn.classList.add('open');
}

/* ── Selecteer een nummer (vanuit setlist of "alle nummers") ── */
function selectSong(song) {
    _ct.bpm = parseInt(song.bpm, 10) || _ct.bpm;

    // Navigatiebalk: naam bijwerken
    var songEl = document.getElementById('ct-song');
    if (songEl) songEl.textContent = song.title + ' — ' + song.artist;

    // Detailkaart invullen
    var card = document.getElementById('song-detail-card');
    if (card) {
        card.style.display = '';

        document.getElementById('detail-title').textContent  = song.title;
        document.getElementById('detail-artist').textContent = song.artist;
        document.getElementById('detail-bpm').textContent    = song.bpm || '--';

        // Start + duur
        var startsWrap = document.getElementById('detail-starts-wrap');
        var startsEl   = document.getElementById('detail-starts');
        var durEl      = document.getElementById('detail-duration');
        if (song.starts || song.duration) {
            startsEl.textContent     = song.starts || '';
            durEl.textContent        = song.duration || '';
            startsWrap.style.display = '';
        } else {
            startsWrap.style.display = 'none';
        }

        // Notities / beschrijving
        var descEl = document.getElementById('detail-desc');
        if (song.description && song.description.trim()) {
            descEl.textContent   = song.description;
            descEl.style.display = '';
        } else {
            descEl.style.display = 'none';
        }

        // Drumstructuur SVG (inklapbaar, standaard geopend)
        var drumWrap    = document.getElementById('detail-drum-wrap');
        var drumDiv     = document.getElementById('detail-drum');
        var drumChevron = document.getElementById('detail-drum-chevron');
        var toggleBtn   = document.getElementById('detail-drum-toggle');

        if (drumDiv) {
            // Reset
            drumDiv.innerHTML     = '';
            drumDiv.style.display = 'none';
            if (drumWrap)    drumWrap.style.display  = 'none';
            if (drumChevron) drumChevron.className   = 'bi bi-chevron-right';
            if (toggleBtn)   toggleBtn.classList.remove('open');

            if (song.drum_svg) {
                // SVG is beschikbaar in de cache — geen netwerk nodig.
                drumDiv.innerHTML = song.drum_svg;
                _applySvgSizing(drumDiv, toggleBtn, drumWrap, drumChevron);

            } else if (song.drum_notation) {
                // Fallback: genereer via server (vereist internetverbinding).
                $.ajax({
                    url: 'api/drum_preview.php', type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ notation: song.drum_notation }),
                    dataType: 'json',
                    success: function(r) {
                        if (!r.ok || !r.svg) return;
                        drumDiv.innerHTML = r.svg;
                        _applySvgSizing(drumDiv, toggleBtn, drumWrap, drumChevron);
                    }
                });
            }
        }
    }

    // Click track starten op het BPM van dit nummer
    ctStop();
    setTimeout(function() { ctStart(song.bpm); }, 50);

    // Markeer actief nummer in de setlist
    document.querySelectorAll('#setlist-songs .list-group-item').forEach(function(el) {
        el.classList.remove('selected');
    });
    var active = document.querySelector('#setlist-songs [data-id="' + song.id + '"]');
    if (active) active.classList.add('selected');
}

/* ── Toggle inklapbare drumstructuur ── */
function toggleDrumSection() {
    var drumDiv = document.getElementById('detail-drum');
    var chevron = document.getElementById('detail-drum-chevron');
    var btn     = document.getElementById('detail-drum-toggle');
    if (!drumDiv) return;

    var open = drumDiv.style.display !== 'none';
    drumDiv.style.display = open ? 'none' : '';
    if (chevron) chevron.className = open ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
    if (btn)     btn.classList.toggle('open', !open);
}
