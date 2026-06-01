# Privacy & end-to-end-versleuteling (LiveGig)

Dit document beschrijft het ontwerp van de **end-to-end-versleuteling (E2EE)** per band:
hoe banddata versleuteld wordt opgeslagen, hoe sleutels worden beheerd, en hoe
functies als delen, importeren en wachtwoordherstel daarbinnen werken.

> **Status:** ontwerp / specificatie. Dit document is leidend bij de implementatie.
> Het beschrijft de doeltoestand; de uitrol gebeurt in fasen (zie onderaan).

---

## 1. Doel en dreigingsmodel

**Doel.** De inhoud die een band in de applicatie verwerkt (nummers, notities,
songteksten, akkoorden, drumstructuren, PDF's, setlijsten — inclusief titels en
artiesten) blijft privé voor de leden van die band. De data "blijft bij de
gebruiker": ontsleutelen gebeurt uitsluitend in de browser van een bandlid.

**Beschermt tegen:**
- Diefstal of uitlek van het databasebestand (`data/livegig.db`) en back-ups.
- Een nieuwsgierige of gecompromitteerde server/hoster: de server verwerkt
  uitsluitend *ciphertext* en ziet de sleutels nooit.
- Een platform-admin die meekijkt: een admin beheert accounts en lidmaatschappen,
  maar kan de **inhoud** van een band niet lezen.

**Beschermt NIET tegen:**
- Een gecompromitteerd *eindapparaat* van een bandlid (keylogger, malware).
- XSS in de applicatie zelf zolang de sessie ontgrendeld is (zie §9 mitigaties).
- Verlies van zowel wachtwoord als herstelcode → data is dan onherstelbaar (§8).
- Metadata die bewust leesbaar blijft voor de werking (zie §7).

**Reikwijdte:** versleuteling is **per band** en **opt-in**. Een leider zet de
kluis aan voor een band; vanaf dat moment wordt alle inhoud van die band
versleuteld opgeslagen. Niet-versleutelde bands blijven werken zoals voorheen.

---

## 2. Sleutelhiërarchie (envelope-encryptie)

```
   Wachtwoord van gebruiker
        │  PBKDF2-SHA-256 (salt, 310k iteraties)
        ▼
   KEK  (Key-Encryption-Key, AES-256-GCM)
        │  versleutelt/ontsleutelt
        ▼
   Privésleutel van gebruiker  (RSA-OAEP 2048, privé)      ◄── opgeslagen als enc_privkey
        │  pakt uit (unwrap)
        ▼
   BDK  (Band Data Key, AES-256-GCM, random, één per band)  ◄── per lid ingepakt opgeslagen
        │  versleutelt/ontsleutelt
        ▼
   Inhoud van nummers / setlijsten   (AES-256-GCM blobs in de DB)
```

Drie lagen, elk met een duidelijke rol:

1. **KEK** — afgeleid uit het wachtwoord. Verlaat de browser nooit. Wordt alleen
   gebruikt om de privésleutel te ont-/versleutelen.
2. **Sleutelpaar per gebruiker** (RSA-OAEP). De **publieke** sleutel staat in
   klare tekst in de DB (om naartoe te kunnen versleutelen). De **privé**sleutel
   staat versleuteld (onder de KEK) in de DB en wordt na ontgrendelen alleen in
   het browsergeheugen gehouden.
3. **BDK** — één willekeurige sleutel per band die de daadwerkelijke inhoud
   versleutelt. Voor elk bandlid wordt een **kopie van de BDK ingepakt** met de
   publieke sleutel van dat lid (`band_member_keys`).

**Waarom een sleutelpaar per gebruiker en niet één gedeeld kluiswachtwoord?**
- Een nieuw lid toegang geven = de BDK naar diens *publieke* sleutel inpakken —
  kan zonder het wachtwoord van de nieuweling te kennen.
- Wachtwoord wijzigen is goedkoop: alleen `enc_privkey` wordt opnieuw versleuteld;
  de BDK's blijven intact.
- Een lid intrekken = diens ingepakte BDK-kopie verwijderen (en optioneel de BDK
  roteren zodat ook toekomstige data buiten bereik blijft).

---

## 3. Cryptografische parameters

Alles via de **Web Crypto API** (`crypto.subtle`) — native in de browser, geen
externe libraries.

| Doel | Algoritme | Parameters |
|------|-----------|------------|
| Wachtwoord → KEK | PBKDF2-HMAC-SHA-256 | salt 16 byte (random/gebruiker), 310 000 iteraties, output AES-256 |
| Privésleutel beschermen | AES-256-GCM | 12-byte IV, KEK als sleutel |
| Sleutelpaar | RSA-OAEP | 2048-bit, SHA-256 |
| BDK inpakken per lid | RSA-OAEP | naar publieke sleutel van het lid |
| Inhoud (blobs) | AES-256-GCM | 12-byte random IV per blob, BDK als sleutel |
| Deelsleutel | AES-256-GCM | random per deellink, leeft in URL-fragment (§6) |
| Herstel | PBKDF2-SHA-256 | aparte salt; herstelcode → recovery-KEK (§8) |

Elke versleutelde waarde wordt opgeslagen als `{ v, iv, ct }` (versie, IV,
ciphertext), base64. Het `v`-veld maakt toekomstige algoritmewissels mogelijk.

---

## 4. Datamodel (wijzigingen)

Bestaande tabellen krijgen kolommen; gevoelige kolommen worden voor versleutelde
bands niet meer in klare tekst gevuld.

**`users`** — sleutelmateriaal per gebruiker:
- `kdf_salt`            — salt voor PBKDF2 (wachtwoord → KEK)
- `pubkey`             — publieke sleutel (klare tekst, SPKI/base64)
- `enc_privkey`        — privésleutel, versleuteld onder de KEK (`{v,iv,ct}`)
- `enc_privkey_recovery` — privésleutel, versleuteld onder de recovery-KEK (§8)
- `recovery_salt`      — salt voor de herstelcode

**`bands`**:
- `is_encrypted`  — vlag: is de kluis aan voor deze band?
- `key_version`   — volgnummer van de BDK (voor rotatie)

**`band_member_keys`** — de BDK, per lid ingepakt:
- `band_id`, `user_id`
- `wrapped_bdk`   — BDK ingepakt met de publieke sleutel van het lid
- `key_version`   — bij welke BDK-versie hoort deze kopie

**`songs`** (versleutelde band): inhoud verhuist naar één blob:
- behoudt in klare tekst: `id`, `band_id`, `created_by`, `created_at`
- `enc_blob` — `{v,iv,ct}` met **alle** velden als JSON: `title, artist, bpm,
  song_key, duration, starts, description, lyrics, chords, drum_notation,
  drum_svg, preview_url, spotify_id`
- legacy klare-tekst-kolommen blijven leeg voor versleutelde bands

**`setlists`** (versleutelde band):
- behoudt `id`, `band_id`, `created_by`, `created_at`
- `enc_blob` — `{v,iv,ct}` met `{ name }`
- `setlist_songs` blijft een relationele tabel met `song_id`-verwijzingen
  (zie §7 over de metadata die dit prijsgeeft)

**Deellink** (zie §6): `share_blob`, `share_iv`, `share_v` bij de band, naast het
bestaande gehashte `share_token`.

---

## 5. Flows

### 5.1 Account & sleutelpaar
Bij registratie (of bij de eerste login na invoering, lazy) genereert de browser
een RSA-sleutelpaar en een `kdf_salt`. De privésleutel wordt met de KEK
versleuteld; `pubkey` + `enc_privkey` + `kdf_salt` gaan naar de server. De server
ziet nooit de privésleutel in klare tekst.

### 5.2 Inloggen / kluis ontgrendelen
1. Normale login (server controleert de wachtwoord-hash zoals nu).
2. De browser heeft het wachtwoord (uit het loginformulier) → leidt de KEK af →
   haalt `enc_privkey` op → ontsleutelt de privésleutel **in het geheugen**.
3. Voor elke band waarvan je lid bent: `wrapped_bdk` uitpakken met de
   privésleutel → BDK in het geheugen.
4. Vanaf nu kan de app inhoud ontsleutelen.

De sleutels leven **alleen in het geheugen** (bv. een module-variabele of
`sessionStorage`), niet als klare tekst in `localStorage`.

### 5.3 Offline gebruik
`localStorage` blijft de **ciphertext** cachen (zoals de huidige `lgSave/lgLoad`).
Na ontgrendelen wordt die cache in het geheugen ontsleuteld. Zonder ontgrendelde
sleutel toont de app een "ontgrendel de kluis"-scherm in plaats van klare tekst.

### 5.4 Nummer opslaan (incl. drumstructuur)
1. De browser stelt het nummerobject samen (alle velden).
2. **Drumstructuur:** de notatie gaat naar `api/drum_preview.php`, dat de **SVG**
   server-side rendert en teruggeeft. Zowel `drum_notation` als `drum_svg` worden
   in het nummerobject opgenomen. *(Bewuste keuze: de server ziet de notatie heel
   even tijdens het renderen, over TLS, en slaat die niet op — identiek aan het
   ophalen van songteksten. Hierdoor hoeft de drumparser niet naar JS geport te
   worden.)*
3. Het hele object wordt als JSON met de BDK versleuteld → alleen `enc_blob` gaat
   naar de server.

### 5.5 Lid toevoegen (toegang verlenen)
Een uitgenodigd persoon komt binnen als **kijker** en heeft nog géén
`wrapped_bdk`. Zodra een lid/leider mét ontgrendelde BDK de band opent, detecteert
de app "lid X heeft een publieke sleutel maar nog geen BDK-kopie", pakt de BDK in
met de publieke sleutel van X en uploadt die naar `band_member_keys`. Toegang
verlenen vereist dus geen wachtwoord van de nieuweling.

### 5.6 Lid intrekken
Verwijder de `band_member_keys`-rij van het lid. Voor toekomstige zekerheid kan de
BDK worden **geroteerd**: nieuwe BDK genereren, alle inhoud her-versleutelen
(door een lid in de browser), `key_version` ophogen en nieuwe `wrapped_bdk`'s voor
de resterende leden plaatsen.

### 5.7 Importeren tussen bands
Je kunt nummers importeren uit een andere band waarvan je **lid of leider** bent
(niet als kijker). Omdat na ontgrendelen de BDK's van *al* je bands in het geheugen
zitten:
1. Ciphertext-nummer uit bron-band A ophalen.
2. In de browser ontsleutelen met BDK_A.
3. Opnieuw versleutelen met BDK_B (doelband).
4. De nieuwe ciphertext als nieuw nummer naar band B sturen.

De server controleert dat je in **beide** bands de rol lid of leider hebt, maar
ziet nooit klare tekst. Volledig client-side, zero-knowledge blijft behouden.

---

## 6. Delen (publieke setlijst-deellink)

De bestaande deellink ([public.php](htdocs/live-click/public.php)) toont
zónder login een setlijst (titel, artiest, BPM, duur, start). Onder E2EE kan de
server dat niet meer renderen. Oplossing: een **aparte deelsleutel in de
URL-fragment**.

Wanneer een leider de deellink aanmaakt/ververst:
1. De browser bouwt een **minimale projectie**: alleen de setlijsten en de velden
   die publiek getoond worden (géén notities, songteksten, akkoorden, PDF's).
2. Die projectie wordt versleuteld met een **nieuwe willekeurige deelsleutel (SK)**.
3. De ciphertext wordt als `share_blob` bij de band opgeslagen; **SK gaat in de
   URL-fragment**: `public.php?t=TOKEN#k=BASE64(SK)`.

Bekijken (gast):
- `t=TOKEN` → de server zoekt de band op en geeft `share_blob` terug (ciphertext).
- `#k=SK` → **wordt door de browser nooit naar de server gestuurd**; JavaScript
  leest `location.hash`, ontsleutelt de projectie en rendert de pagina
  (afdrukken/PDF blijft werken — de browser print de gerenderde DOM).

Eigenschappen:
- De server en de hoster zien nooit de inhoud van de deellink, noch de sleutel.
- De deellink toont **alleen** de bewust geprojecteerde velden — zelfs met SK kan
  een gast geen notities/teksten/PDF's zien (die staan niet in `share_blob`).
- **Intrekken** = `share_blob` + token verwijderen. Een oude link werkt dan niet
  meer, ook al kent iemand de SK nog.
- **Momentopname:** de projectie is een snapshot. Bij wijzigingen in setlijsten
  ververst de browser van een lid de projectie automatisch (zolang die de BDK
  in het geheugen heeft). Dit is meteen privacyvriendelijk: er staat nooit méér
  online dan nodig.

---

## 7. Wat blijft leesbaar (residuele metadata)

Volledige inhoud (incl. titels/artiesten) is versleuteld. Toch blijft, omwille
van de werking, een beperkte set **metadata** leesbaar voor de server:

- Welke gebruikers bestaan en in welke bands ze zitten, en hun rol.
- Het **bestaan** van bands, nummers en setlijsten, plus aantallen en
  tijdstempels (`created_at`), en de volgorde/relaties in `setlist_songs`
  (welke nummer-id's in welke volgorde in een setlijst staan — niet hun inhoud).
- Bandnamen: zie keuze hieronder.

> **Open punt — bandnaam.** De bandnaam wordt in de UI vaak buiten de kluis
> getoond (navigatiebalk, bandkeuze) nog vóór ontgrendelen. Voorstel: bandnaam
> voorlopig **leesbaar** laten; alle inhoud eronder versleuteld. Te heroverwegen.

Voor wie ook deze metadata wil minimaliseren is een latere stap mogelijk
(setlijst-structuur mee de blob in), maar dat kost relationele integriteit
(cascade-deletes) en valt buiten de eerste invoering.

---

## 8. Wachtwoordherstel

Bij echte zero-knowledge kan **niemand** — ook de admin niet — een vergeten
wachtwoord-kluis openen. Daarom genereert de app bij het opzetten van de kluis
**herstelcodes**:

- Een herstelcode is hoog-entropisch en wordt **één keer** getoond.
- Uit de herstelcode wordt (PBKDF2, eigen `recovery_salt`) een recovery-KEK
  afgeleid; daarmee wordt een **tweede versleutelde kopie van de privésleutel**
  bewaard (`enc_privkey_recovery`).
- Vergeten wachtwoord + herstelcode → privésleutel terug → nieuw wachtwoord zetten
  (en `enc_privkey` opnieuw versleutelen).

Zonder wachtwoord én zonder herstelcode is de data **onherstelbaar**. Dit is het
bewuste gevolg van zero-knowledge en wordt duidelijk aan de gebruiker getoond.

Een admin-wachtwoordreset (bestaande functie) zet wél een nieuw login-wachtwoord,
maar herstelt **niet** de kluis — de gebruiker moet dan zijn herstelcode gebruiken.
Dit onderscheid wordt expliciet in de UI gemaakt.

---

## 9. Aanvullende maatregelen & aandachtspunten

- **Sleutels niet plat op schijf.** BDK's en de privésleutel leven in het geheugen
  (of `sessionStorage`), niet als klare tekst in `localStorage`. `localStorage`
  bevat alleen ciphertext.
- **XSS blijft de belangrijkste rest-dreiging.** Zolang de kluis ontgrendeld is,
  kan kwaadaardige JS bij de sleutels. De bestaande CSP
  ([includes/security_headers.php](includes/security_headers.php)) en CSRF-
  bescherming blijven daarom essentieel; inline scripts verder afbouwen waar kan.
- **Drum-SVG / songtekst** passeren de server in klare tekst op het moment van
  renderen/ophalen (over TLS), maar worden alleen versleuteld opgeslagen. Bewuste,
  gedocumenteerde compromis.
- **Online zoeken** naar nieuwe nummers (Tunebat/Spotify/MusicBrainz) betreft
  externe data en blijft ongewijzigd werken.
- **Eigen bibliotheek doorzoeken** wordt volledig client-side (de nummers staan al
  ontsleuteld in het geheugen) — `api/search.php` doorzoekt geen banddata meer.

---

## 10. Uitrol in fasen

1. **Fundament (sleutels).** Schema-uitbreiding; WebCrypto-module in JS
   (PBKDF2/RSA/AES-helpers); sleutelpaar genereren bij registratie/eerste login;
   ontgrendel-flow + sleutels in geheugen. Nog geen inhoud versleuteld.
2. **Kluis per band aanzetten.** `is_encrypted`, BDK genereren, `band_member_keys`
   vullen voor bestaande leden, herstelcodes tonen. Migratie van bestaande inhoud
   naar `enc_blob` (client-side).
3. **Nummers versleutelen.** `songs.enc_blob`; lezen/schrijven via de cryptomodule;
   dashboard/nummers-beheer ontsleutelen in de browser; drum-SVG bij opslaan in de
   blob.
4. **Setlijsten versleutelen.** `setlists.enc_blob`; dashboard- en setlijst-pagina's.
5. **Delen.** Projectie + deelsleutel-in-fragment; `public.php` ombouwen naar
   client-side ontsleutelen.
6. **Importeren tussen bands.** Client-side her-versleutelen; server-side rolcheck
   op beide bands.
7. **Afronden.** Lid toevoegen/intrekken met (her)inpakken van de BDK; eventueel
   BDK-rotatie; remember-me ontgrendel-prompt; randgevallen en foutmeldingen.

Elke fase is afzonderlijk testbaar en laat niet-versleutelde bands ongemoeid.

---

## 11. Implementatiestatus

Alle zeven fasen zijn geïmplementeerd:

1. **Fundament** ✅ — sleutelpaar per gebruiker (`api/keys.php`, `crypto.js`),
   KEK uit wachtwoord, privésleutel in `sessionStorage`, herverpakken bij
   wachtwoordwijziging.
2. **Kluis per band** ✅ — `bands.is_encrypted`, `band_member_keys`, BDK,
   herstelcodes, automatische sleuteltoekenning aan (nieuwe) leden (`api/vault.php`,
   `LGVault`). Statusindicator per lid op de bandpagina.
3. **Nummers** ✅ — `songs.enc_blob`, drum-SVG bij opslaan in de blob,
   automatische migratie van bestaande nummers.
4. **Setlijsten** ✅ — `setlists.enc_blob` (naam), automatische migratie.
5. **Delen** ✅ — `bands.share_blob` + deelsleutel-in-fragment (`LGShare`,
   `public.php` client-side ontsleutelen).
6. **Importeren tussen bands** ✅ — client-side her-versleutelen op de
   Nummers-pagina; server dwingt lid/leider-rechten op beide bands af.
7. **Afronden** ✅ — ontgrendel-prompt na remember-me auto-login en
   herstel-login met herstelcode (`LGKeys.unlock`, `LGKeys.recoverWithCode`,
   `api/keys.php` acties `get_recovery`/`rewrap`), sleutels wissen bij logout.

### Bekende beperkingen / nog open
- **BDK-rotatie** bij het intrekken van een lid is voorbereid in het schema
  (`key_version`) maar nog niet als actieve flow geïmplementeerd; een verwijderd
  lid verliest wel direct toegang (zijn `band_member_keys`-rij), maar de BDK zelf
  wordt niet automatisch ververst.
- **Admin-wachtwoordreset** zet een nieuw login-wachtwoord maar opent de kluis
  niet; de gebruiker gebruikt daarna zijn **herstelcode** (ontgrendel-prompt) om
  de kluis weer met het nieuwe wachtwoord te koppelen.
- **Login-wachtwoord na herstel**: `recoverWithCode` koppelt de kluis aan een
  nieuw wachtwoord, maar wijzigt het login-wachtwoord zelf niet — dat blijft een
  losse stap via Profiel.
- De drumstructuur passeert de server in klare tekst op het moment van renderen
  (bewuste, gedocumenteerde compromis — §5.4).
```
