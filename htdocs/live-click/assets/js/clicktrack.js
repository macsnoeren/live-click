/* LiveGig Click Track Engine */
var _ct = {
    bpm: 80,
    running: false,
    counter: 0,
    tsPrev: 0,
    autoStop: 25,
    autoEnabled: true,
    soundEnabled: false,
    autoTimer: null,
    numBeats: 4,
};

/* ── Audio — volledig gesynthetiseerd, geen extern bestand nodig ── */
var _audioCtx = null;

function ctInitAudio() {
    if (_audioCtx) return;
    _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
}

/**
 * Speelt een kort gesynthetiseerd "klik"-geluid.
 * Beat 1 (counter === 0) krijgt een hogere toon als accent.
 * Geen netwerk vereist.
 */
function ctPlayClick() {
    if (!_audioCtx) return;
    var now  = _audioCtx.currentTime;
    var freq = (_ct.counter === 0) ? 1000 : 700; // accent op slag 1
    var dur  = 0.055; // seconden

    var osc  = _audioCtx.createOscillator();
    var gain = _audioCtx.createGain();
    osc.connect(gain);
    gain.connect(_audioCtx.destination);

    osc.type = 'sine';
    osc.frequency.setValueAtTime(freq, now);
    gain.gain.setValueAtTime(1.0, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + dur);

    osc.start(now);
    osc.stop(now + dur);
}

function ctStart(bpm) {
    ctInitAudio();
    if (_audioCtx && _audioCtx.state === 'suspended') _audioCtx.resume();
    if (bpm) _ct.bpm = parseInt(bpm, 10);
    if (_ct.running) return;
    _ct.running = true;
    _ct.counter = 0;
    _ct.tsPrev = 0;
    ctUpdateBpmDisplay();

    if (_ct.autoEnabled) {
        _ct.autoTimer = setTimeout(ctStop, _ct.autoStop * 1000);
    }
    ctTick();
}

function ctStop() {
    _ct.running = false;
    _ct.counter = 0;
    _ct.tsPrev = 0;
    if (_ct.autoTimer) { clearTimeout(_ct.autoTimer); _ct.autoTimer = null; }
    ctResetDots();
    document.getElementById('ct-bpm').textContent = '-- BPM';
}

function ctTick() {
    if (!_ct.running) return;
    var ts = performance.now();
    var interval = 60000 / _ct.bpm;

    if (_ct.tsPrev === 0 || (ts - _ct.tsPrev) > interval - 20) {
        while (performance.now() - _ct.tsPrev < interval) { /* spin */ }
        ts = performance.now();

        ctResetDots();
        ctLightDot(_ct.counter);
        if (_ct.soundEnabled) ctPlayClick();
        _ct.counter = (_ct.counter + 1) % _ct.numBeats;
        _ct.tsPrev = ts;
    }
    setTimeout(ctTick, 0);
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
