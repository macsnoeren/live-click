/*
This file is part of Blast! software for live gig performances, called BlastLive.

BlastLive is free software: you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version.

BlastLive is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with Foobar. 
If not, see <https://www.gnu.org/licenses/>.

Author Maurice Snoeren (drummer of Blast!)
*/

var _bpm = 80;
var _ctStarted = false;
var _counter = 0;
var _autoStopTime = 25;         // Seconds
var _autoStop = _autoStopTime;  // Enable auto stop at the start, automode is on by default #bugfix
var _soundOn = false;
var _tsPrevious = 0;
var _createSetListList = [];
var _setlists = [];

var clap = new Audio()
clap.src = 'sounds/clap-2-95736.mp3'
clap.preload = 'auto'
clap.load()

function startClickTrack(b) {
    _bpm = b;
    setClickTrackBPMDisplay(b);
    if ( !_ctStarted ) {
        _counter = 0;
        _ctStarted = true;
        executeClickTrack();

        if ( _autoStop != 0 ) {
            setTimeout(stopClickTrack, _autoStop*1000);
        }
    }
}

function executeClickTrack () {
    var ts = performance.now();

    if ( _ctStarted ) {
        // Improve precision by waiting when the timing is close enough, in this case 20ms before the actual
        // timing is required. Then wait inside the method until the actual timing is there.
        // Further improvement due to the use of performance.now(). It is 0.1ms accuracy. 
        if ( _tsPrevious == 0 || (ts - _tsPrevious) > (1000*60/_bpm) - 20 ) { // precision on 20 ms
            while ( (ts - _tsPrevious) < (1000*60/_bpm)) { // Not <=, so the timing is more accurate.
                ts = performance.now();
            }

            resetColorClickTrackDisplay();
            if ( _soundOn ) {
                clap.currentTime = 0;
                //clap.play();
                createSource(() => {});
            }
            setColorClickTrackDisplay(_counter);
            _counter = (_counter + 1) % 4;

            $("#debug").text("Timing: " + (ts - _tsPrevious).toFixed(1) + " ms");
            _tsPrevious = ts;
        }

        setTimeout(executeClickTrack, 0); // Schedule as soon as possible to create high precision timing!
    }
}

function stopClickTrack() {
    _ctStarted = false;
    _counter = 0;
    resetColorClickTrackDisplay();
    setClickTrackBPMDisplay("--");
}

function resetColorClickTrackDisplay() {
    for ( var i=0; i < 8; i++ ) {
        $("#clicktrack_" + (i+1)).css("background-color", "#BBB");
    }
}

function setColorClickTrackDisplay(i) {
    $("#clicktrack_" + (i+1)).css("background-color", "#F00");
}

function setClickTrackBPMDisplay(b) {
    $("#clicktrack_bpm").text(b + " BPM");
}

function populateSongs(songs) {
    $("#songs").html("");
    for ( var i=0; i < songs.length; i++ ) {
        s = songs[i];
        $("#songs").append('<a id="songs_' + (i+1) + '" href="javascript:startClickTrack(' + s.bpm + ')" class="list-group-item list-group-item-action list-group-item-dark"><p class="h1">' + 
        (i+1) + '. ' + s.title + '</p><p>' + s.artist + ' (' + s.bpm + ' BPM), who starts: <b>' + s.starts + '</b> - (id: ' + s.id  + ')</p><p>' + s.description + '</p></a>');
    }
}

function populateSongsForSetlist(songs) {
    $("#songs").html("");
    for ( var i=0; i < songs.length; i++ ) {
        s = songs[i];
        $("#songs").append('<table><tr><td><a id="songs_' + (i+1) + '" href="javascript:startClickTrack(' + s.bpm + ')" class="list-group-item list-group-item-action list-group-item-dark"><p class="h1">' + 
        (i+1) + '. ' + s.title + '</p><p>' + s.artist + ' (' + s.bpm + ' BPM), who starts: <b>' + s.starts + '</b> - (id: ' + s.id  + ')</p><p>' + s.description + '</p></a>' +
        '</td><td><button type="button" class="btn btn-primary btn-lg" onclick="addToSetlist(' + s.id + ')">+</button></td></tr>');
    }
}

function populateSongsForTotalList(songs) {
    $("#songs").html("");
    for ( var i=0; i < songs.length; i++ ) {
        s = songs[i];
        $("#songs").append('<table><tr><td>' + 
        (i+1) + '. ' + s.title + ' (' + s.artist + ')' +
        '</td></tr></table>');
    }
}

function populateSongsForPrintSetlist(songs) {
    $("#songs").html("");
    for ( var i=0; i < songs.length; i++ ) {
        s = songs[i];
        $("#songs").append('<table><tr><td>' + 
        (i+1) + '. ' + s.title + ' (' + s.artist + ') - start: ' + s.starts + '</td></tr></table>');
    }
}

function populateAvailableLists () {
    $("#availablelists").html("");

    for ( var i=0; i < getLists().length; i++ ) {
        s = getLists()[i];
        $("#availablelists").append('<li><a class="dropdown-item" href="javascript:populateList(' + i + ')">' + s.title + '</a></li>');
    }    
}

function populateList(l) {
    populateSongs(getListSongs(l));
}

function toggleAutoMode () {
    if ( $("#automode").is(":checked") ) {
        _autoStop = _autoStopTime;
    } else {
        _autoStop = 0;
    }
}

function toggleSoundMode () {
    if ( $("#soundmode").is(":checked") ) {
        _soundOn = true;
    } else {
        _soundOn = false;
    }
}

// Function that stores the value to a belonging key in the local storage.
function store(key, value) {
    localStorage.setItem(key, value);
}

// Function that retrieves the value to a belonging key from the local storage.
function retrieve(key) {
    return localStorage.getItem(key);
}

function getSetlists () {
    setlists = retrieve("setlists");
    if ( setlists == null ) {
        return {};
    }
    return JSON.parse(setlists);
}

function storeSetlists (setlists) {
    store("setlists", JSON.stringify(setlists));
}

function addToSetlist(id) {
    _createSetListList.push(id);
    updateSetListList();
    console.log("add to setlist: " + id);
}

function createNewSetlist () {
    setlists = getSetlists();
    nameSetlist = $('#setlist-name')[0].value;
    setlists[nameSetlist] = _createSetListList;
    console.log("SETLISTS" + JSON.stringify(setlists));
    _setlists = setlists;
    storeSetlists(setlists);
    console.log("Create new setlist with name: " + nameSetlist);
    console.log("With the songs: " + _createSetListList.join(", "));
    
}

function clearNewSetlist () {
    _createSetListList = [];
}

function updateSetListList () {
    $('#setlist-content').html(_createSetListList.join(", "));
}

function getQueryParams(qs) {
    qs = qs.split('+').join(' ');

    var params = {},
        tokens,
        re = /[?&]?([^=]+)=([^&]*)/g;

    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }

    return params;
}

function getURLParams () {
    return getQueryParams( window.location.search.substring(1) );

}