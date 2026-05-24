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

// Audio
var _audioCtx = null;
var _audioBuffer = null;

function ctInitAudio() {
    if (_audioCtx) return;
    _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    var src = 'https://storage.googleapis.com/absolute-bot-264323/audio/DB90_Samples/QuarterNote-loudest.mp3';
    fetch(src)
        .then(function(r){ return r.arrayBuffer(); })
        .then(function(buf){ return _audioCtx.decodeAudioData(buf); })
        .then(function(decoded){ _audioBuffer = decoded; })
        .catch(function(){ console.warn('Click sound load failed'); });
}

function ctPlayClick() {
    if (!_audioCtx || !_audioBuffer) return;
    var src = _audioCtx.createBufferSource();
    src.buffer = _audioBuffer;
    src.connect(_audioCtx.destination);
    src.start(0);
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

// Called from song list clicks
function selectSong(song) {
    _ct.bpm = parseInt(song.bpm, 10) || _ct.bpm;

    // Update header song name
    var songEl = document.getElementById('ct-song');
    if (songEl) songEl.textContent = song.title + ' — ' + song.artist;

    // Update detail card
    var card = document.getElementById('song-detail-card');
    if (card) {
        card.style.display = '';

        document.getElementById('detail-title').textContent  = song.title;
        document.getElementById('detail-artist').textContent = song.artist;
        document.getElementById('detail-bpm').textContent    = song.bpm || '--';

        // Starts + duration row
        var startsWrap = document.getElementById('detail-starts-wrap');
        var startsEl   = document.getElementById('detail-starts');
        var durEl      = document.getElementById('detail-duration');
        if (song.starts || song.duration) {
            startsEl.textContent = song.starts || '';
            durEl.textContent    = song.duration || '';
            startsWrap.style.display = '';
        } else {
            startsWrap.style.display = 'none';
        }

        // Description / notes
        var descEl = document.getElementById('detail-desc');
        if (song.description && song.description.trim()) {
            descEl.textContent    = song.description; // textContent keeps it safe
            descEl.style.display  = '';
        } else {
            descEl.style.display  = 'none';
        }

        // Drum structure SVG (inklapbaar, standaard geopend)
        var drumWrap    = document.getElementById('detail-drum-wrap');
        var drumDiv     = document.getElementById('detail-drum');
        var drumChevron = document.getElementById('detail-drum-chevron');
        var toggleBtn   = document.getElementById('detail-drum-toggle');
        if (drumDiv) {
            // Reset naar verborgen zodat meting van de kaart klopt
            drumDiv.innerHTML     = '';
            drumDiv.style.display = 'none';
            if (drumWrap)    drumWrap.style.display  = 'none';
            if (drumChevron) drumChevron.className   = 'bi bi-chevron-right';
            if (toggleBtn)   toggleBtn.classList.remove('open');

            if (song.drum_notation) {
                $.ajax({
                    url: 'api/drum_preview.php', type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({notation: song.drum_notation}),
                    dataType: 'json',
                    success: function(r) {
                        if (!r.ok || !r.svg) return;
                        drumDiv.innerHTML = r.svg;
                        var svg = drumDiv.querySelector('svg');

                        if (svg) {
                            // Meet beschikbare hoogte VOORDAT drumWrap zichtbaar is,
                            // zodat de kaart nog niet door de SVG vergroot is.
                            var rightCol  = document.querySelector('.db-col-right');
                            var detCard   = document.getElementById('song-detail-card');
                            var colH      = rightCol ? rightCol.clientHeight : 0;
                            var fixedDetH = detCard  ? detCard.offsetHeight  : 0;
                            var togH      = toggleBtn ? toggleBtn.offsetHeight : 28;
                            var minSongs  = 80;   // garantie voor zichtbaarheid songlijst
                            var padding   = 24;   // drumDiv-padding + gaps

                            var available = colH - fixedDetH - togH - minSongs - padding;

                            // Natuurlijke hoogte uit data-attribuut (betrouwbaarder dan viewBox-parsing)
                            var natH = parseInt(svg.getAttribute('data-natural-h') || '0', 10);

                            if (natH > 0 && available > 0 && natH > available) {
                                // Schaal de SVG zodat hij precies past — breedte volgt automatisch
                                svg.style.height = Math.max(60, available) + 'px';
                            }
                            // Breedte altijd op auto: past zich aan aan de (eventueel geschaalde) hoogte
                            svg.style.width = 'auto';
                        }

                        // Toon geopend
                        drumDiv.style.display = '';
                        if (drumWrap)    drumWrap.style.display  = '';
                        if (drumChevron) drumChevron.className   = 'bi bi-chevron-down';
                        if (toggleBtn)   toggleBtn.classList.add('open');
                    }
                });
            }
        }
    }

    // Start (of herstart) click track op het BPM van het geselecteerde nummer
    ctStop();
    setTimeout(function(){ ctStart(song.bpm); }, 50);

    // Highlight in setlist
    document.querySelectorAll('#setlist-songs .list-group-item').forEach(function(el) {
        el.classList.remove('selected');
    });
    var active = document.querySelector('#setlist-songs [data-id="' + song.id + '"]');
    if (active) active.classList.add('selected');
}

// Toggle inklapbare drumstructuur
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
