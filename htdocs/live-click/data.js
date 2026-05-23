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
Setlist 1 "songs": [82, 107, 95, 116, 101, 103, 64, 45, 7, 20, 62, 122, 48, 44, 60, 99],
Setlist 2 "songs": [97, 120, 104, 113, 112, 92, 81, 123, 86, 58, 75, 111, 28, 10, 15, 24, 84, 42],  
*/

function getLists () {
    return [
        {
            "title": "All songs",
            "songs": [],
        },
        {
            "title": "Set 1",
            "songs": [82, 107, 95, 116, 101, 103, 64, 45, 7, 20, 62, 29, 48, 44, 60],
            
        },
        {
            "title": "Set 2",
            "songs": [97, 120, 104, 113, 112, 81, 123, 86, 53, 75, 111, 10, 15, 24, 84, 42],
            
        },
    ];
}

function getListSongs (list) {
    if ( list == 0 ) {
        return getSongs(true);
    }

    songs = [];

    if ( list < getLists().length ) {
        for ( var i of getLists()[list].songs ) {
            songs.push( getSongs()[i] );
        }
    }

    return songs;
}

function getSongs(sorted = false) {
    if ( !sorted ) {
        return _songs;
    }

    songs = JSON.parse(JSON.stringify(_songs)); // deep copy, so underlying structure _songs not changed!

    songs.sort( function(a, b) {
        var keyA = a.title.toUpperCase();
        var keyB = b.title.toUpperCase();

        if (keyA < keyB) return -1;
        if (keyA > keyB) return 1;
        return 0;
    });

    return songs;
}

// Add who starts!
var _songs = [
    {
        "id": 0,
        "title": "Take a chance on me",
        "artist": "Abba",
        "bpm": "107",
        "starts": "Zang",
        "description": "Abba_Take a chance on me (04:04)"
    },
    {
        "id": 1,
        "title": "Waterloo",
        "artist": "Abba",
        "bpm": "148",
        "starts": "",
        "description": "Abba_Waterloo (02:49)"
    },
    {
        "id": 2,
        "title": "Mama mia",
        "artist": "Abba",
        "bpm": "140",
        "starts": "",
        "description": "Abba_Mama mia (03:45)"
    },
    {
        "id": 3,
        "title": "Highway to hell",
        "artist": "ACDC",
        "bpm": "116",
        "starts": "Bob",
        "description": "ACDC_Highway to hell (03:28)"
    },
    {
        "id": 4,
        "title": "Whole lotta Rosie",
        "artist": "ACDC",
        "bpm": "159",
        "starts": "Maurice",
        "description": "ACDC_Whole lotta Rosie (05:34)"
    },
    {
        "id": 5,
        "title": "Rolling in the deep",
        "artist": "Adele",
        "bpm": "105",
        "starts": "Bob",
        "description": "Adele_Rolling in the deep (03:48)"
    },
    {
        "id": 6,
        "title": "You oughta know",
        "artist": "Alanis Morissette",
        "bpm": "105",
        "starts": "Maurice",
        "description": "Alanis Morissette_You oughta know (04:10)"
    },
    {
        "id": 7,
        "title": "This is the life",
        "artist": "Amy McDonalds",
        "bpm": "95",
        "starts": "Bob",
        "description": "Amy McDonalds_This is the life (03:54)"
    },
    {
        "id": 8,
        "title": "Valerie",
        "artist": "Amy Winehouse ",
        "bpm": "106",
        "starts": "Maurice",
        "description": "Amy Winehouse _Valerie (03:45)"
    },
    {
        "id": 9,
        "title": "Leef (met I will survive)",
        "artist": "Andre Hazes Jr ",
        "bpm": "121",
        "starts": "Harold",
        "description": "Andre Hazes Jr _Leef (met I will survive) (03:38)"
    },
    {
        "id": 10,
        "title": "Why tell me why",
        "artist": "Anita Meyer ",
        "bpm": "113",
        "starts": "Harold",
        "description": "Anita Meyer _Why tell me why (03:32)"
    },
    {
        "id": 11,
        "title": "Nobody's wife",
        "artist": "Anouk",
        "bpm": "98",
        "starts": "Bob",
        "description": "Anouk_Nobody's wife (03:26)"
    },
    {
        "id": 12,
        "title": "So hard",
        "artist": "Anouk",
        "bpm": "129",
        "starts": "Bob",
        "description": "Anouk_So hard (04:27)"
    },
    {
        "id": 13,
        "title": "RU kidding me",
        "artist": "Anouk ",
        "bpm": "89",
        "starts": "Bob",
        "description": "Anouk _RU kidding me (03:20)"
    },
    {
        "id": 14,
        "title": "Good god",
        "artist": "Anouk ",
        "bpm": "164",
        "starts": "Maurice",
        "description": "Anouk _Good god (02:36)"
    },
    {
        "id": 15,
        "title": "Think",
        "artist": "Aretha Ericlin ",
        "bpm": "129",
        "starts": "Harold",
        "description": "Aretha Ericlin _Think (03:21)"
    },
    {
        "id": 16,
        "title": "Wake me up",
        "artist": "Avici",
        "bpm": "124",
        "starts": "Bob",
        "description": "Avici_Wake me up (03:30)"
    },
    {
        "id": 17,
        "title": "I Gotta Feeling",
        "artist": "Black Eyed Peas",
        "bpm": "128",
        "starts": "",
        "description": "Black Eyed Peas_I Gotta Feeling (04:05)"
    },
    {
        "id": 18,
        "title": "Living on a prayer",
        "artist": "Bon jovi",
        "bpm": "123",
        "starts": "Harold",
        "description": "Bon jovi_Living on a prayer (04:09)"
    },
    {
        "id": 19,
        "title": "Love a bad name",
        "artist": "Bon jovi",
        "bpm": "123",
        "starts": "Harold",
        "description": "Bon jovi_Love a bad name (03:43)"
    },
    {
        "id": 20,
        "title": "Uptown funk",
        "artist": "Bruno Mars",
        "bpm": "115",
        "starts": "Harold",
        "description": "Bruno Mars_Uptown funk (04:30)"
    },
    {
        "id": 21,
        "title": "Ain't nobody",
        "artist": "Chaka Khan",
        "bpm": "100",
        "starts": "",
        "description": "Chaka Khan_Ain't nobody (04:41)"
    },
    {
        "id": 22,
        "title": "Afraid of the dark",
        "artist": "Chef'Special",
        "bpm": "167",
        "starts": "",
        "description": "Chef'Special_Afraid of the dark (03:07)"
    },
    {
        "id": 23,
        "title": "Mag ik dan bij jou",
        "artist": "Claudia de Brey",
        "bpm": "133",
        "starts": "Harold",
        "description": "Claudia de Brey_Mag ik dan bij jou (03:24)"
    },
    {
        "id": 24,
        "title": "Viva la vida",
        "artist": "Coldplay",
        "bpm": "138",
        "starts": "Harold",
        "description": "Coldplay_Viva la vida (04:02)"
    },
    {
        "id": 25,
        "title": "Dansen op de vulkaan",
        "artist": "Dijk de",
        "bpm": "150",
        "starts": "Maurice",
        "description": "Dijk de_Dansen op de vulkaan (04:27)"
    },
    {
        "id": 26,
        "title": "ik kan het niet alleen",
        "artist": "Dijk de",
        "bpm": "142",
        "starts": "Maurice",
        "description": "Dijk de_ik kan het niet alleen (03:59)"
    },
    {
        "id": 27,
        "title": "Als ze er niet is",
        "artist": "Dijk de",
        "bpm": "84",
        "starts": "",
        "description": "Dijk de_Als ze er niet is (03:32)"
    },
    {
        "id": 28,
        "title": "Love me just a little bit more",
        "artist": "Dolly Dots",
        "bpm": "96",
        "starts": "Bob",
        "description": "Dolly Dots_Love me just a little bit more (03:33)"
    },
    {
        "id": 29,
        "title": "Jolene",
        "artist": "Dolly Parton",
        "bpm": "110",
        "starts": "Bob",
        "description": "Dolly Parton_Jolene (02:42)"
    },
    {
        "id": 30,
        "title": "Working 9 to 5",
        "artist": "Dolly Parton",
        "bpm": "105",
        "starts": "",
        "description": "Dolly Parton_Working 9 to 5 (02:42)"
    },
    {
        "id": 31,
        "title": "Bad Girls",
        "artist": "Donna Summer",
        "bpm": "121",
        "starts": "",
        "description": "Donna Summer_Bad Girls (04:55)"
    },
    {
        "id": 32,
        "title": "Long train running",
        "artist": "Dooby Brothers ",
        "bpm": "117",
        "starts": "Bob",
        "description": "Dooby Brothers _Long train running (03:05)"
    },
    {
        "id": 33,
        "title": "Listen to the music",
        "artist": "Dooby Brothers ",
        "bpm": "106",
        "starts": "Bob",
        "description": "Dooby Brothers _Listen to the music (03:21)"
    },
    {
        "id": 34,
        "title": "September",
        "artist": "Earth Wind & Fire",
        "bpm": "Onderdeel medley Disco",
        "starts": "126",
        "description": ""
    },
    {
        "id": 35,
        "title": "Boogie Wonderland",
        "artist": "Earth Wind & Fire",
        "bpm": "Onderdeel medley Disco",
        "starts": "132",
        "description": ""
    },
    {
        "id": 36,
        "title": "Perfect",
        "artist": "Ed Sheeran ",
        "bpm": "95",
        "starts": "Bob",
        "description": "Ed Sheeran _Perfect (04:23)"
    },
    {
        "id": 37,
        "title": "Sisters are doin' it for themselves",
        "artist": "Eurithmics ",
        "bpm": "136",
        "starts": "Harold",
        "description": "Eurithmics _Sisters are doin' it for themselves (05:57)"
    },
    {
        "id": 38,
        "title": "The final count down",
        "artist": "Europe",
        "bpm": "118",
        "starts": "",
        "description": "Europe_The final count down (05:09)"
    },
    {
        "id": 39,
        "title": "Jesus he knows me",
        "artist": "Genesis ",
        "bpm": "95",
        "starts": "Harold",
        "description": "Genesis _Jesus he knows me (04:17)"
    },
    {
        "id": 40,
        "title": "Una paloma blanca",
        "artist": "George Baker",
        "bpm": "133",
        "starts": "Maurice",
        "description": "George Baker_Una paloma blanca (03:32)"
    },
    {
        "id": 41,
        "title": "Heavy cross",
        "artist": "Gossip ",
        "bpm": "120",
        "starts": "Bob",
        "description": "Gossip _Heavy cross (04:03)"
    },
    {
        "id": 42,
        "title": "Brabant",
        "artist": "Guus Meeuwis",
        "bpm": "82",
        "starts": "Bob",
        "description": "Guus Meeuwis_Brabant (03:30)"
    },
    {
        "id": 43,
        "title": "Jump",
        "artist": "Halen van ",
        "bpm": "130",
        "starts": "Harold",
        "description": "Halen van _Jump (04:02)"
    },
    {
        "id": 44,
        "title": "Atemlos durch die Nacht",
        "artist": "Helene Fischer",
        "bpm": "128",
        "starts": "Bob",
        "description": "Helene Fischer_Atemlos durch die Nacht (04:30)"
    },
    {
        "id": 45,
        "title": "I love it",
        "artist": "Icona pop ",
        "bpm": "126",
        "starts": "Harold",
        "description": "Icona pop _I love it (02:37)"
    },
    {
        "id": 46,
        "title": "The Trooper",
        "artist": "Iron Maiden",
        "bpm": "160",
        "starts": "Maurice",
        "description": "Iron Maiden_The Trooper (04:13)"
    },
    {
        "id": 47,
        "title": "Hold back the river",
        "artist": "James Bay",
        "bpm": "135",
        "starts": "Bob",
        "description": "James Bay_Hold back the river (03:59)"
    },
    {
        "id": 48,
        "title": "I feel good",
        "artist": "James Brown",
        "bpm": "143",
        "starts": "Zang",
        "description": "James Brown_I feel good (02:46)<br>6x - 3x - pauze!"
    },
    {
        "id": 49,
        "title": "Dance Across The Floor",
        "artist": "Jimmy Bo Horne",
        "bpm": "112",
        "starts": "",
        "description": "Jimmy Bo Horne_Dance Across The Floor (02:41)"
    },
    {
        "id": 50,
        "title": "Een geit in het caf\u00e9",
        "artist": "Johnny Purple",
        "bpm": "136",
        "starts": "Harold",
        "description": "Johnny Purple_Een geit in het caf\u00e9 (03:58)"
    },
    {
        "id": 51,
        "title": "Son of a preacher man",
        "artist": "Joss Stone",
        "bpm": "85",
        "starts": "Maurice",
        "description": "Joss Stone_Son of a preacher man (02:29)"
    },
    {
        "id": 52,
        "title": "Super duper love",
        "artist": "Joss Stone ",
        "bpm": "95",
        "starts": "Maurice",
        "description": "Joss Stone _Super duper love (04:20)"
    },
    {
        "id": 53,
        "title": "Oya L\u00e9l\u00e9",
        "artist": "K3",
        "bpm": "135",
        "starts": "",
        "description": "K3_Oya L\u00e9l\u00e9 (03:45)"
    },
    {
        "id": 54,
        "title": "Walking on sunshine",
        "artist": "Katrina & The Waves",
        "bpm": "110",
        "starts": "Maurice",
        "description": "Katrina & The Waves_Walking on sunshine (03:59)"
    },
    {
        "id": 55,
        "title": "Underneath the tree",
        "artist": "Kelly Klarkson",
        "bpm": "160",
        "starts": "Harold",
        "description": "Kelly Klarkson_Underneath the tree (03:50)"
    },
    {
        "id": 56,
        "title": "War",
        "artist": "Kensington ",
        "bpm": "126",
        "starts": "Maurice",
        "description": "Kensington _War (02:57)"
    },
    {
        "id": 57,
        "title": "I've got the music in me",
        "artist": "Kiki Dee",
        "bpm": "119",
        "starts": "Eric",
        "description": "Kiki Dee_I've got the music in me (05:02)"
    },
    {
        "id": 58,
        "title": "Op een onbewoond eiland",
        "artist": "Kinderen voor kinderen",
        "bpm": "72",
        "starts": "",
        "description": "Kinderen voor kinderen_Op een onbewoond eiland (03:01)"
    },
    {
        "id": 59,
        "title": "Sex on fire",
        "artist": "Kings of Leon",
        "bpm": "153",
        "starts": "Bob",
        "description": "Kings of Leon_Sex on fire (03:23)"
    },
    {
        "id": 60,
        "title": "Always Remember Us This Way",
        "artist": "Lady Gaga",
        "bpm": "130",
        "starts": "Harold",
        "description": "Lady Gaga_Always Remember Us This Way (03:30)"
    },
    {
        "id": 61,
        "title": "Narcotic",
        "artist": "Liquido",
        "bpm": "102",
        "starts": "Maurice",
        "description": "Liquido_Narcotic (03:56)"
    },
    {
        "id": 62,
        "title": "Like a prayer",
        "artist": "Madonna",
        "bpm": "111",
        "starts": "Harold",
        "description": "Madonna_Like a prayer (05:43)"
    },
    {
        "id": 63,
        "title": "Dance with somebody",
        "artist": "Mando Diao",
        "bpm": "150",
        "starts": "Maurice",
        "description": "Mando Diao_Dance with somebody (04:02)"
    },
    {
        "id": 64,
        "title": "I was made for lovin you",
        "artist": "Maria mena",
        "bpm": "128",
        "starts": "Bob",
        "description": "Maria mena_I was made for lovin you (04:31)"
    },
    {
        "id": 65,
        "title": "All I want for Christmas",
        "artist": "Mariah Carey",
        "bpm": "150",
        "starts": "Harold",
        "description": "Mariah Carey_All I want for Christmas (04:01)"
    },
    {
        "id": 66,
        "title": "Shackles",
        "artist": "Mary Mary",
        "bpm": "100",
        "starts": "Harold",
        "description": "Mary Mary_Shackles (03:18)"
    },
    {
        "id": 67,
        "title": "Billy Jean",
        "artist": "Michael Jackson",
        "bpm": "120",
        "starts": "Bob",
        "description": "Michael Jackson_Billie Jean (04:54), Into | C1 | B1 | R1 | C2 | B2 | R2 | OUTRO"
    },
    {
        "id": 68,
        "title": "Beat it",
        "artist": "Micheal Jackson",
        "bpm": "139",
        "starts": "",
        "description": "Micheal Jackson_Beat it (04:18)"
    },
    {
        "id": 69,
        "title": "Lady marmelade",
        "artist": "Patty labelle",
        "bpm": "115",
        "starts": "Maurice",
        "description": "Patty labelle_Lady marmelade (03:41)"
    },
    {
        "id": 70,
        "title": "Raise your glass",
        "artist": "Pink",
        "bpm": "122",
        "starts": "Bob",
        "description": "Pink_Raise your glass (03:00)"
    },
    {
        "id": 71,
        "title": "Jump",
        "artist": "Pointer sisters",
        "bpm": "130",
        "starts": "Harold",
        "description": "Pointer sisters_Jump (04:23)"
    },
    {
        "id": 72,
        "title": "Non non rien change",
        "artist": "Poppy's les",
        "bpm": "95",
        "starts": "Bob",
        "description": "Poppy's les_Non non rien change (03:10)"
    },
    {
        "id": 73,
        "title": "Purple Rain",
        "artist": "Prince",
        "bpm": "113",
        "starts": "",
        "description": "Prince_Purple Rain (08:14)"
    },
    {
        "id": 74,
        "title": "Don't stop me now",
        "artist": "Queen ",
        "bpm": "156",
        "starts": "Harold",
        "description": "Queen _Don't stop me now (03:29)"
    },
    {
        "id": 75,
        "title": "Du Hast",
        "artist": "Rammstein",
        "bpm": "125",
        "starts": "Harold",
        "description": "Rammstein_Du Hast (03:54)"
    },
    {
        "id": 76,
        "title": "Angels",
        "artist": "Robbie Williams ",
        "bpm": "150",
        "starts": "Harold",
        "description": "Robbie Williams _Angels (04:25)"
    },
    {
        "id": 77,
        "title": "Coming home",
        "artist": "Sheppard",
        "bpm": "145",
        "starts": "iedereen",
        "description": "Sheppard_Coming home (03:38)"
    },
    {
        "id": 78,
        "title": "Don't you (forget about me)",
        "artist": "Simple minds",
        "bpm": "111",
        "starts": "",
        "description": "Simple minds_Don't you (forget about me) (04:23)"
    },
    {
        "id": 79,
        "title": "We are family",
        "artist": "Sister Sledge",
        "bpm": "119",
        "starts": "",
        "description": "Sister Sledge_We are family (03:37)"
    },
    {
        "id": 80,
        "title": "Boys don't cry",
        "artist": "The Cure",
        "bpm": "169",
        "starts": "Bob",
        "description": "The Cure_Boys don't cry (02:38)"
    },
    {
        "id": 81,
        "title": "Iedereen is van de wereld",
        "artist": "The scene",
        "bpm": "118",
        "starts": "Bob",
        "description": "The scene_Iedereen is van de wereld (03:48)"
    },
    {
        "id": 82,
        "title": "Disco inferno",
        "artist": "The Trammps",
        "bpm": "131",
        "starts": "Eric",
        "description": "The Trammps_Disco inferno (04:20)"
    },
    {
        "id": 83,
        "title": "River Deep",
        "artist": "Tina Turner",
        "bpm": "83",
        "starts": "Harold",
        "description": "Tina Turner_River Deep (03:53)"
    },
    {
        "id": 84,
        "title": "Proud mary",
        "artist": "Tina Turner",
        "bpm": "171",
        "starts": "Eric",
        "description": "Tina Turner_Proud mary (05:27)"
    },
    {
        "id": 85,
        "title": "Nutbush City Limits",
        "artist": "Tina Turner",
        "bpm": "150",
        "starts": "Bob",
        "description": "Tina Turner_Nutbush City Limits (03:20)"
    },
    {
        "id": 86,
        "title": "Lola Montez",
        "artist": "Volbeat",
        "bpm": "152",
        "starts": "Bob",
        "description": "Volbeat_Lola Montez (04:28)"
    },
    {
        "id": 87,
        "title": "Shut up and dance",
        "artist": "Walk the Moon",
        "bpm": "128",
        "starts": "Bob",
        "description": "Walk the Moon_Shut up and dance (03:19)"
    },
    {
        "id": 88,
        "title": "Play that funky music",
        "artist": "Wild Cherry",
        "bpm": "110",
        "starts": "Eric",
        "description": "Wild Cherry_Play that funky music (04:55)"
    },
    {
        "id": 89,
        "title": "Met jou kan ik het aan",
        "artist": "Emma en Anouk",
        "bpm": "",
        "starts": "Maurice",
        "description": "Emma en Anouk_Met jou kan ik het aan (00:00)"
    },
    {
        "id": 90,
        "title": "Be more kind",
        "artist": "Frank Turner",
        "bpm": "136",
        "starts": "Harold",
        "description": "Frank Turner_Be more kind (04:07)"
    },
    {
        "id": 91,
        "title": "Black Velvet",
        "artist": "Alannah Myles",
        "bpm": "91",
        "starts": "Eric",
        "description": "Alannah Myles_Black Velvet (04:47)"
    },
    {
        "id": 92,
        "title": "Sexy als ik dans",
        "artist": "Nielson",
        "bpm": "94",
        "starts": "Bob",
        "description": "Nielson_Sexy als ik dans (03:35)"
    },
    {
        "id": 93,
        "title": "Lang zal ze leven",
        "artist": "Lang zal ze leven",
        "bpm": "102",
        "starts": "Zang",
        "description": "Lang zal ze leven_Lang zal ze leven (00:30)"
    },
    {
        "id": 94,
        "title": "Seven Nation Army",
        "artist": "The White Stripes",
        "bpm": "124",
        "starts": "Eric",
        "description": "The White Stripes_Seven Nation Army (03:51)"
    },
    {
        "id": 95,
        "title": "Bright Eyes",
        "artist": "Ronde",
        "bpm": "124",
        "starts": "Maurice",
        "description": "Ronde_Bright Eyes (02:51)"
    },
    {
        "id": 96,
        "title": "Noodgeval",
        "artist": "Goldband",
        "bpm": "139",
        "starts": "Maurice",
        "description": "Goldband_Noodgeval (03:34)"
    },
    {
        "id": 97,
        "title": "Music was my first love",
        "artist": "John Miles",
        "bpm": "91",
        "starts": "Harold",
        "description": "John_Miles_Music_was_my_first_love"
    },
    {
        "id": 98,
        "title": "Disco medley",
        "artist": "-",
        "bpm": "112",
        "starts": "Maurice",
        "description": "Let's dance"
    },
    {
        "id": 99,
        "title": "Stop",
        "artist": "Sam Brown",
        "bpm": "186",
        "starts": "Harold 6/8",
        "description": ""
    },
    {
        "id": 100,
        "title": "Tina Turner Medley",
        "artist": "Tina Turner",
        "bpm": "100",
        "starts": "Harold",
        "description": "Tempo onbekend"
    },
    {
        "id": 101,
        "title": "Abba Medley",
        "artist": "Abba",
        "bpm": "100",
        "starts": "Zang",
        "description": "Tempo onbekend"
    },
    {
        "id": 102,
        "title": "Anouk Medley",
        "artist": "Anouk",
        "bpm": "98",
        "starts": "Bob",
        "description": "Nobody's wife"
    },
    {
        "id": 103,
        "title": "Queen Medley",
        "artist": "Queen",
        "bpm": "100",
        "starts": "Harold",
        "description": "Tempo onbekend"
    },
    {
        "id": 104,
        "title": "Bon Jovi Medley",
        "artist": "Bon Jovi",
        "bpm": "100",
        "starts": "Harold",
        "description": "Tempo onbekend"
    },
    {
        "id": 105,
        "title": "ACDC Medley",
        "artist": "ACDC",
        "bpm": "100",
        "starts": "Bob",
        "description": "Tempo onbekend"
    },
    {
        "id": 106,
        "title": "Hardcore Medley",
        "artist": "Hardcore",
        "bpm": "100",
        "starts": "Harold",
        "description": "Tempo onbekend"
    },
    {
        "id": 107,
        "title": "Goud",
        "artist": "Suzan en Freek",
        "bpm": "110",
        "starts": "Bob",
        "description": "Intro | C1 | R1 | C2 | R2 | B | R3 <br>Starten C1 tweede rondje<br>C2 laatste rondje stil en eerste rondje B stil<br>R3 anders spelen."
    },
    {
        "id": 108,
        "title": "Let me entertain you",
        "artist": "Robby Williams",
        "bpm": "125",
        "starts": "Harold",
        "description": "Intro | Zang (hihat) | R1 (drum) | C1 | R2 | C2 | B1 (rustig) | R3 | C3 | I (stil) | R4 | (solo) | outro"
    },
    {
        "id": 109,
        "title": "Engelbewaarder",
        "artist": "Marco Schuitmaker",
        "bpm": "130",
        "starts": "Maurice",
        "description": "Intro | C1 | C2 | R1 | C3 | C4 | R2 | B1 | R3 |"
    },
    {
        "id": 110,
        "title": "Genau dieses gefuhl",
        "artist": "Helene Fischer",
        "bpm": "103",
        "starts": "Maurice",
        "description": "Intro | C1 (stop) | R1 | C2 | R2 | OUTRO"
    },
    {
        "id": 111,
        "title": "You're the one that I want",
        "artist": "Grease",
        "bpm": "120",
        "starts": "Maurice",
        "description": "Intro | C1 | C2 | R1 | C3 | C4 | R2 (2x)"
    },
    {
        "id": 112,
        "title": "Terug in de tijd",
        "artist": "Yves Berendse",
        "bpm": "123",
        "starts": "Harold",
        "description": "Intro | R | C (bas start) | R | B (toms) | R"
    },
    {
        "id": 113,
        "title": "Bloed zweet en tranen",
        "artist": "Andre Hazes",
        "bpm": "68",
        "starts": "Maurice",
        "description": "6/8 => Intro | C1 (rand) | R1 | C2 | R2 | B | R | R (solo)"
    },
    {
        "id": 114,
        "title": "De laatste",
        "artist": "Amer en Snelle",
        "bpm": "122",
        "starts": "Harold",
        "description": "Rekening nog niet betaald een stop"
    },
    {
        "id": 115,
        "title": "Die with a smile",
        "artist": "Lady Gaga",
        "bpm": "158",
        "starts": "Bob",
        "description": "Intro | C1 | R | I | C2 | R | B | Outro"
    },
    {
        "id": 116,
        "title": "Ik wil dat je liegt",
        "artist": "Hannah Mae",
        "bpm": "107",
        "starts": "Bob",
        "description": ""
    },
    {
        "id": 117,
        "title": "The Time of my life",
        "artist": "Bill Medley",
        "bpm": "109",
        "starts": "Zang",
        "description": ""
    },
    {
        "id": 118,
        "title": "Jaren 80 Medley",
        "artist": "?",
        "bpm": "",
        "starts": "Harold",
        "description": ""
    },
    {
        "id": 119,
        "title": "Sinds 1 dag of 2 (32 jaar)",
        "artist": "Doe Maar",
        "bpm": "114",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 120,
        "title": "Your love",
        "artist": "The Outfield",
        "bpm": "130",
        "starts": "Bob",
        "description": ""
    },
    {
        "id": 121,
        "title": "Rain down on me",
        "artist": "Kane",
        "bpm": "133",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 122,
        "title": "Verdammt ich lieb dich",
        "artist": "Matthias Reim",
        "bpm": "100",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 123,
        "title": "Echte Liefde Is Te Koop",
        "artist": "Samuel Welten",
        "bpm": "131",
        "starts": "Toetsen / Zang",
        "description": ""
    },
];

/*

    {
        "id": 120,
        "title": "Islands in the Stream (ATIx)",
        "artist": "Dolly Parton",
        "bpm": "102",
        "starts": "Drum",
        "description": ""
    },
    {
        "id": 121,
        "title": "Is dit alles",
        "artist": "Doe Maar",
        "bpm": "121",
        "starts": "Drum",
        "description": ""
    },
    {
        "id": 122,
        "title": "Locked out of Heaven",
        "artist": "Bruno Mars",
        "bpm": "144",
        "starts": "Drum",
        "description": "Dubbel aftikken - na 3de tel tweede keer start"
    },
    {
        "id": 123,
        "title": "Stil in mij",
        "artist": "Van Dik Hout",
        "bpm": "99",
        "starts": "Gitaar",
        "description": ""
    },

    {
        "id": 125,
        "title": "I want you to want me",
        "artist": "Cheap Trick",
        "bpm": "93",
        "starts": "Drum",
        "description": "Drum intro"
    },
    {
        "id": 126,
        "title": "Hard to handle",
        "artist": "Black Crowes",
        "bpm": "104",
        "starts": "Drum",
        "description": "Drum intro"
    },
    {
        "id": 127,
        "title": "Bad case of loving you",
        "artist": "Robert Palmer",
        "bpm": "146",
        "starts": "Drum",
        "description": "Drum voorslag"
    },
    {
        "id": 128,
        "title": "I will follow",
        "artist": "U2",
        "bpm": "155",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 129,
        "title": "Murder on the dancefloor",
        "artist": "Sophie Ellis-Bextor",
        "bpm": "117",
        "starts": "Toetsen",
        "description": ""
    },
    {
        "id": 130,
        "title": "Wannabee",
        "artist": "Spice Girls",
        "bpm": "110",
        "starts": "Drum",
        "description": ""
    },
    {
        "id": 131,
        "title": "Rock this town",
        "artist": "Straycats",
        "bpm": "202",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 132,
        "title": "Smoorverliefd",
        "artist": "Doe Maar",
        "bpm": "167",
        "starts": "Bas",
        "description": ""
    },
    {
        "id": 133,
        "title": "Ik wil je",
        "artist": "De Kreuners",
        "bpm": "156",
        "starts": "Drum",
        "description": "Voorslag"
    },
    {
        "id": 134,
        "title": "Never be clever",
        "artist": "Herman Brood",
        "bpm": "138",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 135,
        "title": "Sweet Child of Mine",
        "artist": "Guns and Roses",
        "bpm": "123",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 136,
        "title": "Girl",
        "artist": "Anouk",
        "bpm": "135",
        "starts": "Drum",
        "description": ""
    },
    {
        "id": 137,
        "title": "I will follow",
        "artist": "U2",
        "bpm": "155",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 138,
        "title": "Born to be wild",
        "artist": "Steppenwolf",
        "bpm": "146",
        "starts": "Drum",
        "description": "Drum voorslag"
    },
    {
        "id": 139,
        "title": "Are you gonna go my way",
        "artist": "Lenny Kravitz",
        "bpm": "129",
        "starts": "Drum",
        "description": ""
    },
    {
        "id": 140,
        "title": "Zombie",
        "artist": "The Cranberries",
        "bpm": "84",
        "starts": "Gitaar",
        "description": ""
    },
    {
        "id": 141,
        "title": "Weak",
        "artist": "Skunk Anansie",
        "bpm": "95",
        "starts": "Drum",
        "description": "Drum intro"
    },
    {
        "id": 142,
        "title": "Vertigo",
        "artist": "U2",
        "bpm": "140",
        "starts": "Drum",
        "description": ""
    },
*/
