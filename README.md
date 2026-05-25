# LiveGig

Webapplicatie voor coverband-drummers. Beheert het repertoire, bouwt setlists en geeft een kliktrack direct op het scherm — ook volledig offline, zónder internetverbinding op het podium.

---

## Functies

| Module | Beschrijving |
|---|---|
| **Kliktrack** | Gesynthetiseerde klik via Web Audio API (geen extern audiobestand). Slag 1 als accent (1000 Hz), slagen 2–4 op 700 Hz. Automatisch stopzetten na instelbaar aantal seconden. |
| **Dashboard** | Setlist links, alle nummers rechts. Nummer aanklikken start direct de kliktrack op het juiste BPM. Drumstructuur inklapbaar weergegeven als SVG. |
| **Repertoire** | Volledige nummerlijst per band: titel, artiest, BPM, toonsoort (Camelot-notatie), duur, wie er begint, notities en drumstructuur. |
| **Nummers zoeken** | Automatisch opzoeken via Tunebat → GetSongBPM → Spotify → MusicBrainz (waterval). Geeft BPM, toonsoort, energie, dansbaarheid en Spotify-preview. |
| **Drumstructuur** | Eigen teksnotatie (zie [Drumnotatie](#drumnotatie)). Wordt server-side omgezet naar een SVG-diagram en gecached in de database. |
| **Setlists** | Maak setlists aan via drag-and-drop. Geschatte totaalduur op basis van de duur van de nummers. |
| **Bands** | Meerdere bands per account. Uitnodigingslinks voor nieuwe leden, bandleiders beheren het lidmaatschap. |
| **Deellink** | Publieke URL voor gasten om setlists te bekijken (alleen-lezen, geen login). PDF/printen via `window.print()`. |
| **Offline** | Nummers, setlists en drumstructuren worden gecached in `localStorage`. Bootstrap, jQuery en Bootstrap Icons staan lokaal opgeslagen. Na de eerste sync werkt het dashboard volledig zonder internet. |
| **Beveiliging** | Wachtwoord-hashing via `password_hash()`. TOTP-tweefactorauthenticatie (Google Authenticator e.d.). "Onthoud mij"-cookie met server-side token. Wachtwoordwijziging afdwingen per gebruiker. |
| **Admin** | Gebruikers en bands aanmaken/bewerken, rollen toewijzen (`user` / `admin`). |

---

## Vereisten

| | Versie |
|---|---|
| PHP | 8.1 of hoger |
| PHP-extensies | `pdo_sqlite`, `curl` (aanbevolen), `openssl` |
| Webserver | Apache of Nginx |
| Database | SQLite 3 (wordt automatisch aangemaakt) |

Geen Composer, geen Node.js, geen buildstap.

---

## Mappenstructuur

```
live-click/
├── data/
│   ├── livegig.db              ← SQLite-database (automatisch aangemaakt)
│   └── .spotify_token          ← Spotify-tokencache (automatisch)
│
├── includes/                   ← PHP-includes (BUITEN de webroot)
│   ├── auth.php                  Sessie, login, remember-me, TOTP
│   ├── config.php                API-sleutels (Spotify, GetSongBPM)
│   ├── db.php                    PDO-verbinding + schema-migraties
│   ├── DrumParser.php            Parser voor drumnotatietekst
│   ├── DrumSvg.php               SVG-renderer voor drumstructuren
│   ├── totp.php                  TOTP-generatie en -verificatie
│   ├── header.php                Navigatiebalk (kliktrack + menu)
│   └── footer.php                Vendor-JS laden
│
└── htdocs/
    └── live-click/             ← Webroot (DocumentRoot)
        ├── bootstrap.php         APP_ROOT + APP_DEPTH definiëren
        ├── dashboard.php         Hoofdscherm: kliktrack + setlist + nummers
        ├── songs.php             Repertoirebeheer
        ├── setlists.php          Setlistbeheer
        ├── bands.php             Bandbeheer + uitnodigings- en deellinks
        ├── admin.php             Gebruikers- en bandbeheer (admin)
        ├── profile.php           Wachtwoord wijzigen, 2FA in-/uitschakelen
        ├── login.php             Inloggen (+ 2FA-stap)
        ├── register.php          Registreren
        ├── logout.php            Uitloggen
        ├── join.php              Uitnodigingslink inwisselen
        ├── public.php            Publieke setlist-deellink (geen login)
        ├── import.php            Eenmalige import vanuit data.js (admin/CLI)
        │
        ├── assets/
        │   ├── css/app.css       Donker thema, dashboard-layout, drumstijlen
        │   ├── js/
        │   │   ├── app.js        Dataleden, localStorage-cache, rendering
        │   │   └── clicktrack.js Kliktrack-engine, drumstructuur-weergave
        │   └── vendor/           Lokale kopieën (geen CDN vereist)
        │       ├── bootstrap/
        │       ├── bootstrap-icons/
        │       └── jquery/
        │
        └── api/                  JSON-endpoints (vereisen login)
            ├── songs.php
            ├── setlists.php
            ├── bands.php
            ├── users.php
            ├── share.php         Deeltokens beheren
            ├── invite.php        Uitnodigingstokens beheren
            ├── search.php        Nummers opzoeken (Tunebat/Spotify/…)
            ├── drum_preview.php  Drumnotatie → SVG
            ├── profile.php       Wachtwoord en 2FA
            └── switch-band.php   Actieve band wisselen
```

---

## Installatie

### 1. Serverconfiguratie

Stel de webserver-root in op `htdocs/live-click/`.  
De mappen `includes/` en `data/` liggen bewust **buiten** de webroot.

Voorbeeld Apache virtual host:

```apache
<VirtualHost *:443>
    ServerName app.blastcoverband.nl
    DocumentRoot /projects/app.blastcoverband.nl/htdocs/live-click
    <Directory /projects/app.blastcoverband.nl/htdocs/live-click>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Verwachte mappenstructuur op de server:

```
/projects/app.blastcoverband.nl/
├── data/
├── includes/
└── htdocs/
    └── live-click/   ← DocumentRoot
```

### 2. Schrijfrechten

Geef de webserver-gebruiker schrijfrechten op de `data/`-map:

```bash
chown -R www-data:www-data /projects/app.blastcoverband.nl/data
chmod 750 /projects/app.blastcoverband.nl/data
```

### 3. Eerste keer opstarten

De database wordt automatisch aangemaakt bij het eerste HTTP-verzoek.  
Er wordt een standaard adminaccount aangemaakt als er nog geen gebruikers bestaan:

| Gebruikersnaam | Wachtwoord |
|---|---|
| `admin` | `admin` |

**Wijzig dit wachtwoord direct na de eerste login** via *Profiel → Wachtwoord wijzigen*.

---

## Configuratie

### API-sleutels (`includes/config.php`)

```php
// Spotify (voor nummerzoeken met BPM + preview)
// Maak een app aan op https://developer.spotify.com/dashboard
define('SPOTIFY_CLIENT_ID',     'jouw-client-id');
define('SPOTIFY_CLIENT_SECRET', 'jouw-client-secret');

// GetSongBPM (gratis na registratie op https://getsongbpm.com/api)
define('GETSONGBPM_API_KEY', 'jouw-api-sleutel');
```

Beide sleutels zijn optioneel. Zonder Spotify-credentials valt de nummerzoeker terug op Tunebat (geen credentials nodig) en MusicBrainz. Tunebat levert ook BPM en toonsoort.

> **Let op**: het Spotify audio-features-endpoint (BPM) is verouderd voor apps aangemaakt ná 27 november 2024. Gebruik voor BPM bij voorkeur Tunebat of GetSongBPM.

### Vendor-bestanden (Bootstrap / jQuery)

Lokale kopieën staan in `assets/vendor/`. Ze worden niet automatisch bijgewerkt. Vernieuw ze handmatig wanneer nodig:

```bash
# Bootstrap 5.3.x
curl -o assets/vendor/bootstrap/css/bootstrap.min.css \
     https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css

curl -o assets/vendor/bootstrap/js/bootstrap.bundle.min.js \
     https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js

# jQuery
curl -o assets/vendor/jquery/jquery.min.js \
     https://code.jquery.com/jquery-3.7.1.min.js

# Bootstrap Icons (+ fonts)
curl -o assets/vendor/bootstrap-icons/bootstrap-icons.min.css \
     https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css

curl -o assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2 \
     https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2

curl -o assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff \
     https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff
```

---

## Drumnotatie

Drummers beschrijven de structuur van een nummer in een eigen tekstformaat. Elke regel is één sectie. Groepen maatstreepjes worden gescheiden door twee of meer spaties — dat vormt een zichtbare frasegrens in de SVG.

### Syntax

```
SectieLabel: symbolen
```

| Symbool | Betekenis | Kleur in SVG |
|---|---|---|
| `\|` | Normale maat | Wit |
| `-` | Rust / stil | Rood |
| `^` | Crash / cymbal | Goud |
| `*` | Break / brake | Oranje |

Tekst na het laatste geldige symbool op een regel wordt als **commentaar** weergegeven (grijs, cursief).

### Voorbeeld

```
Intro:   ^ | | |   | | | |
Couplet: | | | |   | | | |   | | | |   | | | |
Refrein: ^ | | |   ^ | | |   ^ | | *   | - - -  rustig uitlopen
Solo:    ^ | | |   | | | |
Outro:   | | | |   | | | -   ^ stop
```

Dit genereert een SVG-diagram dat op het dashboard ingeklapt/uitgeklapt kan worden.

---

## Offline werking

Het dashboard werkt na de eerste laadbeurt volledig zonder internet:

1. **Nummers en setlists** worden bij elk bezoek aan het dashboard op de achtergrond ververst en opgeslagen in `localStorage` (inclusief drum-SVG's).  
   Bij geen verbinding worden de gecachede gegevens direct getoond.

2. **Kliktrack-geluid** wordt realtime gegenereerd via de Web Audio API — geen extern audiobestand.

3. **Bootstrap, jQuery en Bootstrap Icons** staan als lokale bestanden in `assets/vendor/`.

Een offline-melding (rode balk boven de setlist) verschijnt met de datum van de laatste synchronisatie wanneer de server niet bereikbaar is.

---

## Eenmalige import vanuit `data.js`

Bestaand repertoire in een `_songs`-array (JavaScript) kan geïmporteerd worden:

**Via browser** (vereist adminrol):
```
https://jouwdomein.nl/live-click/import.php
```

**Via CLI**:
```bash
php htdocs/live-click/import.php [band_id]
```

Het script leest `data.js` in dezelfde map, verwijdert dubbele nummers en schrijft nieuwe nummers naar de database. Bestaande nummers worden overgeslagen.

---

## API-overzicht

Alle endpoints vereisen een ingelogde sessie en retourneren JSON.

| Methode | Endpoint | Beschrijving |
|---|---|---|
| GET/POST/DELETE | `api/songs.php` | Nummers ophalen, opslaan, verwijderen |
| GET/POST/DELETE | `api/setlists.php` | Setlists + nummervolgorde |
| GET/POST/DELETE | `api/bands.php` | Bands en leden beheren |
| GET/POST/DELETE | `api/users.php` | Gebruikersbeheer (admin) |
| GET/POST/DELETE | `api/share.php` | Publiek deeltoken per band |
| GET/POST/DELETE | `api/invite.php` | Uitnodigingstoken per band |
| GET | `api/search.php?q=` | Nummers zoeken (Tunebat/GetSongBPM/Spotify/MusicBrainz) |
| POST | `api/drum_preview.php` | Drumnotatie → SVG (live preview bij bewerken) |
| POST | `api/profile.php` | Wachtwoord wijzigen, 2FA beheren |
| GET | `api/switch-band.php` | Actieve band wisselen |

---

## Databaseschema

SQLite-bestand: `data/livegig.db`  
Schema wordt automatisch aangemaakt en gemigreerd via `includes/db.php`.

```
users            id, username, email, password_hash, role, totp_secret,
                 totp_enabled, must_change_password
bands            id, name, description, share_token
band_members     user_id, band_id, role (member|leader)
songs            id, title, artist, bpm, song_key, duration, starts,
                 description, drum_notation, drum_svg, preview_url,
                 spotify_id, band_id, created_by
setlists         id, name, band_id, created_by
setlist_songs    setlist_id, song_id, position
band_invites     band_id, token, created_by
remember_tokens  user_id, token_hash, expires_at
```

---

## Beveiliging

- Wachtwoorden gehashed met `PASSWORD_DEFAULT` (`bcrypt`).
- TOTP-tweefactorauthenticatie conform RFC 6238 (30 s venster, ±1 stap tolerantie).
- "Onthoud mij"-tokens worden als SHA-256-hash opgeslagen; het plaintext-token staat alleen in de cookie.
- `includes/` en `data/` liggen buiten de webroot — geen directe HTTP-toegang mogelijk.
- Alle gebruikersinvoer in HTML-context wordt geëscaped via `htmlspecialchars()` (PHP) of `escHtml()` (JavaScript). Gebruikersnamen en songnamen worden nooit inline in JavaScript-attribuutwaarden gezet.
- Publieke deellinks gebruiken een 32-teken hex-token (`bin2hex(random_bytes(16))`). Intrekken via *Bands → Setlijst deellink → Verwijderen*.

---

## Lokale ontwikkeling

PHP's ingebouwde server werkt direct vanuit de webroot:

```bash
cd htdocs/live-click
php -S localhost:8080
```

Open `http://localhost:8080` in de browser.  
Inloggen met `admin` / `admin` en direct het wachtwoord wijzigen.
