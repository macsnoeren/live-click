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

https://anthony-dandrea.medium.com/low-latency-web-audio-doesnt-have-to-be-hard-7e602a772319
*/

const audioContext = new (window.AudioContext || window.webkitAudioContext)();
const audioSrc = 'https://storage.googleapis.com/absolute-bot-264323/audio/DB90_Samples/QuarterNote-loudest.mp3'
const NUM_BEEPS = 20

let beepCount = 0
let bpm = 120

let latency = 0
let latencyTotal = 0

let startTime = null

let buffer = null
let source = null
let startOffset = null

fetch(audioSrc, onSuccess)

// GET request audio source
function fetch(url, resolve) {
    var request = new XMLHttpRequest();
    request.open('GET', url, true);
    request.responseType = 'arraybuffer';
    request.onload = function () { resolve(request) }
    request.send()
}

// Decode audio file into a data buffer
function onSuccess(request) {
    var audioData = request.response;
    audioContext.decodeAudioData(audioData,
        onBuffer = (buf) => {
            buffer = buf;
            runMetronome(bpm);
        },
        onDecodeBufferError = (e) => {
            console.error('Error decoding buffer: ' + e.message);
            console.error(e);
        }
    )
}

// Create source node from AudioContext, attaches buffer, and connects it to output node
//
// A node can only be played once, so it's beneficial to create a separate function for
// creating nodes that may be needed often.
function preloadBufferNode() {
    source = audioContext.createBufferSource();
    source.buffer = buffer
    source.connect(audioContext.destination);
}

// Runs node preload, then attaches callback and delays the start of playback to match
// the tempo
function createSource(callback) {
    preloadBufferNode()
    source.onended = callback
    startOffset = startOffset || audioContext.currentTime + 60 / bpm;
    source.start(startOffset)
}

// Main metronome loop function
function runMetronome() {
    if (beepCount < NUM_BEEPS) {
        beepCount += 1

        startTime = new Date();
        createSource(() => {
            let latency = new Date() - startTime

            // We ignore the first latency as there is some small delay in the initial reference to the 
            // audioContext.currentTime property. After this, time reference is consistent to the previous
            // time used, and will not have this extra latency.
            if (beepCount > 1) {
                console.log("latency:", latency)
                latencyTotal += latency
            }

            // Schedule next playback to occur exactly (60 / bpm) seconds after the one that just played
            startOffset = startOffset + 60 / bpm
            runMetronome();
        });

    } else {
        console.log("Average time between beeps (ms):", latencyTotal / (NUM_BEEPS - 1))
    }
}
