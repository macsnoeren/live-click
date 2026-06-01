# Testchecklist — End-to-end-versleuteling (LiveGig)

Stap-voor-stap testplan voor de E2EE-functionaliteit (zie [PRIVACY.md](PRIVACY.md)).
Vink af terwijl je test. Werk bij voorkeur met **2 testaccounts** in **2 browsers**
(of een normaal venster + een incognitovenster) zodat je het lid-perspectief
kunt nabootsen.

---

## 0. Randvoorwaarden (eerst checken!)

- [ ] App draait via **HTTPS** of **http://localhost** (niet via een `http://`-IP).
      WebCrypto werkt niet op een "onveilige" origin.
      → Test in de browserconsole: `!!(window.crypto && crypto.subtle)` moet `true` zijn.
- [ ] Minstens 2 gebruikersaccounts beschikbaar (bv. `leider` en `lid`).
- [ ] Een testband met een paar nummers en 1–2 setlijsten (nog onversleuteld).
- [ ] DevTools open (F12) — tab **Console**, **Network** en **Application → Storage**.

> **Tip — schone start:** wil je vanaf nul testen, log uit en controleer in
> Application → Session Storage dat `lg_priv` weg is.

---

## 1. Sleutelfundament (fase 1)

**1a. Sleutelpaar wordt aangemaakt bij eerste login**
- [ ] Log in met `leider` (met wachtwoord, niet remember-me).
- [ ] Network: er is een `POST api/keys.php` met `action: init` (alleen de
      állereerste keer voor dit account).
- [ ] Application → Session Storage: `lg_priv` bestaat (base64-string).
- [ ] Console: `LGKeys.keyState()` → `"unlocked"`.

**1b. Tweede login maakt géén nieuw sleutelpaar**
- [ ] Log uit en weer in.
- [ ] Network: `GET api/keys.php` toont `has_keys: true`; **geen** nieuwe `init`.
- [ ] `lg_priv` is opnieuw gevuld.

**1c. Server slaat niets leesbaars op**
- [ ] `GET api/keys.php` (in Network) → response bevat `enc_privkey` (versleuteld),
      `pubkey`, `kdf_salt`. **Geen** leesbare privésleutel.

**1d. Wachtwoord wijzigen behoudt kluistoegang**
- [ ] Profiel → wachtwoord wijzigen.
- [ ] Log uit, log in met het **nieuwe** wachtwoord.
- [ ] `LGKeys.keyState()` → `"unlocked"` (de kluis opent dus nog steeds).

**1e. Logout wist sleutels**
- [ ] Log uit. Application → Session Storage: `lg_priv` en eventuele
      `lg_bdk_*` zijn weg.

---

## 2. Kluis per band aanzetten (fase 2)

**2a. Voorbereiding: zorg dat beide leden een sleutel hebben**
- [ ] Log één keer in met `lid` (zodat ook dat account een sleutelpaar krijgt).
- [ ] Voeg `lid` toe aan de testband (als lid of leider, niet kijker).

**2b. Band versleutelen**
- [ ] Log in als `leider` → **Bands** → bij de testband: **"Band versleutelen"**.
- [ ] Bevestig in de modal.
- [ ] Er verschijnt een **herstelcode** (eenmalig). **Noteer deze** — nodig in §7.
- [ ] Na sluiten: de band toont een groen **slot-icoon** 🔒.

**2c. Controleer de serverstaat**
- [ ] DB (of via Network `GET api/vault.php?band_id=…`): `is_encrypted: true`,
      en er zijn `band_member_keys`-rijen voor de leden mét sleutel.
- [ ] Console: er is een `lg_bdk_<bandid>_v1` in Session Storage.

**2d. Statusindicator per lid**
- [ ] Op de bandkaart: leden met sleutel tonen 🔑 (groen), leden zonder sleutel ⏳ (geel).

**2e. Automatische sleuteltoekenning**
- [ ] Had `lid` nog geen sleutel toen je versleutelde? Log dan nu in als `lid`
      (sleutelpaar wordt gemaakt), en open daarna als `leider` opnieuw de
      Bands-pagina. Het ⏳ van `lid` wordt 🔑 (auto-grant op de achtergrond).

---

## 3. Nummers versleutelen (fase 3)

**3a. Automatische migratie van bestaande nummers**
- [ ] Open als `leider` de **Nummers**-pagina van de versleutelde band.
- [ ] Network: meerdere `POST api/songs.php` met `enc_blob` (de migratie).
- [ ] DB: bij die nummers is `enc_blob` gevuld en `title`/`artist` **leeg**.
- [ ] De pagina toont nog gewoon de titels/artiesten (client ontsleutelt).

**3b. Server ziet geen inhoud**
- [ ] Network: `GET api/songs.php?band_id=…` → de songs hebben `enc_blob`,
      en `title: ""`, `artist: ""`. **Geen** leesbare inhoud in de response.

**3c. Nieuw nummer opslaan (versleuteld)**
- [ ] Voeg een nieuw nummer toe met titel, artiest, BPM, notities, akkoorden,
      **en een drumstructuur**.
- [ ] Opslaan → in Network gaat alleen `enc_blob` mee (geen plaintext velden).
- [ ] Heropen het nummer: alle velden — inclusief de **drum-SVG** — zijn er weer.

**3d. Dashboard toont ontsleutelde inhoud**
- [ ] Dashboard → "Alle Nummers": titels/artiesten/BPM kloppen.
- [ ] Klik een nummer → notities/tekst/akkoorden/drum-tabs tonen de inhoud.

**3e. Songtekst ophalen (versleuteld opslaan)**
- [ ] Open een nummer zonder songtekst → **Songtekst ophalen**.
- [ ] Network: `api/lyrics.php` antwoordt met `stored: false`, gevolgd door een
      `POST api/songs.php` met `enc_blob` (client versleutelt de tekst).
- [ ] Herlaad → de tekst staat er nog.

**3f. Vergrendelde weergave (geen sleutel)**
- [ ] Log in als een gebruiker **zonder** sleutel voor deze band (bv. een vers
      account dat net is toegevoegd maar nog geen grant kreeg), of test door in
      Session Storage `lg_priv` te verwijderen en de pagina te herladen zónder
      te ontgrendelen.
- [ ] Nummers tonen "🔒 Vergrendeld" i.p.v. een crash.

---

## 4. Setlijsten versleutelen (fase 4)

**4a. Automatische migratie van setlijstnamen**
- [ ] Open als `leider` de **Setlists**-pagina van de versleutelde band.
- [ ] Network: `POST api/setlists.php` met `enc_blob` voor bestaande setlijsten.
- [ ] DB: `setlists.enc_blob` gevuld, `name` leeg.
- [ ] De pagina toont gewoon de setlijstnamen (client ontsleutelt), alfabetisch.

**4b. Nieuwe setlijst opslaan**
- [ ] Maak een nieuwe setlijst met een herkenbare naam + paar nummers.
- [ ] Network: alleen `enc_blob` (geen leesbare `name`).
- [ ] Herlaad → naam en nummers kloppen.

**4c. Dashboard-dropdown**
- [ ] Dashboard → setlijst-dropdown toont de juiste (ontsleutelde) namen.
- [ ] Een setlijst kiezen toont de juiste nummers.

---

## 5. Delen (fase 5)

**5a. Deellink aanmaken voor versleutelde band**
- [ ] Bands → testband → **Setlijst deellink** → **Link aanmaken**.
- [ ] De getoonde URL bevat een fragment: `…/public.php?t=…#k=…`.
- [ ] Er staat een melding dat de link de sleutel bevat.

**5b. Server ziet geen inhoud**
- [ ] Network: `POST api/share.php` bevat `share_blob` (ciphertext); de **`#k=`**
      zit alleen in de URL in de browser, **niet** in de request.

**5c. Publieke pagina bekijken (mét sleutel)**
- [ ] Open de **volledige** link (incl. `#k=…`) in een **incognitovenster**
      (niet ingelogd).
- [ ] De setlijst(en) verschijnen: bandnaam, setlijstnamen, en per nummer
      titel/artiest/BPM/duur/start.
- [ ] **Niet** zichtbaar: notities, songtekst, akkoorden, PDF (zit niet in de projectie).
- [ ] Afdrukken/PDF (knop) toont alle setlijsten.

**5d. Zonder sleutel faalt netjes**
- [ ] Open dezelfde link **zonder** het `#k=…` deel.
- [ ] Nette melding "Deze link mist de sleutel" — geen leesbare data, geen crash.

**5e. Intrekken**
- [ ] Trek de deellink in (Verwijder).
- [ ] De volledige link (mét `#k=`) geeft nu "Link niet gevonden of verlopen".

---

## 6. Importeren tussen bands (fase 6)

**Voorbereiding:** zorg dat je account **lid of leider** is van **twee** bands
(bv. een versleutelde "Band A" en een tweede "Band B"). Test bij voorkeur ook de
combinatie versleuteld → versleuteld.

**6a. Import-UI**
- [ ] Nummers-pagina (Band A) → knop **Importeren**.
- [ ] De bron-band-dropdown toont Band B (en andere bands waar je lid/leider bent),
      **niet** Band A zelf en **niet** bands waar je alleen kijker bent.

**6b. Nummers kiezen en importeren**
- [ ] Kies Band B → de nummers laden (ontsleuteld als Band B versleuteld is).
- [ ] Selecteer een paar nummers → **Importeer geselecteerde**.
- [ ] Melding "X nummer(s) geïmporteerd".
- [ ] De nummers staan nu in Band A, met alle inhoud (titel/BPM/notities/drum).

**6c. Zero-knowledge bij import**
- [ ] Network tijdens import: de `POST api/songs.php` naar Band A bevat alleen
      `enc_blob` (als Band A versleuteld is) — geen leesbare inhoud.

**6d. Rechten**
- [ ] Probeer (als test) te importeren terwijl je in Band A alleen **kijker** bent
      → de Importeren-knop hoort afwezig te zijn (geen edit-rechten).

---

## 7. Herstel & ontgrendelen (fase 7)

**7a. Ontgrendel-prompt na remember-me**
- [ ] Log in als `leider` **met** "Onthoud mij" aangevinkt.
- [ ] Sluit het tabblad/browser, open de app opnieuw (auto-login via cookie).
- [ ] Er verschijnt de **"Kluis ontgrendelen"**-modal.
- [ ] Voer je wachtwoord in → pagina herlaadt → inhoud is leesbaar,
      `LGKeys.keyState()` → `"unlocked"`.

**7b. "Later" laat de kluis op slot**
- [ ] Herhaal 7a maar klik **Later** → nummers tonen "🔒 Vergrendeld"
      (geen crash). Bij de volgende paginaload komt de prompt terug.

**7c. Herstel met herstelcode**
- [ ] Forceer de "vergeten wachtwoord"-situatie: laat een admin het wachtwoord
      van `leider` resetten (Admin → gebruiker → nieuw wachtwoord).
- [ ] Log in als `leider` met het **nieuwe** wachtwoord.
- [ ] De ontgrendel-prompt verschijnt, maar je wachtwoord opent de kluis **niet**
      (want de sleutel is onder het oude wachtwoord verpakt).
- [ ] Klik **"Wachtwoord vergeten? Herstelcode gebruiken"**.
- [ ] Voer de **herstelcode uit §2b** + een nieuw wachtwoord in → **Herstellen**.
- [ ] Pagina herlaadt → kluis is open, inhoud leesbaar.
- [ ] (Aanbevolen) Wijzig daarna je login-wachtwoord via Profiel.

**7d. Verkeerde herstelcode**
- [ ] Probeer herstel met een onjuiste code → nette foutmelding "Onjuiste
      herstelcode", geen crash.

---

## 8. Niet-versleutelde bands blijven werken (regressie)

- [ ] Maak/gebruik een band waarvoor de kluis **uit** staat.
- [ ] Nummers toevoegen/bewerken/verwijderen werkt als vanouds (plaintext in DB).
- [ ] Setlijsten werken.
- [ ] Deellink werkt server-side (geen `#k=` nodig).
- [ ] Dashboard/click-track werken.

---

## 9. Snelle console-hulpjes

```js
// Status van de kluis
LGKeys.keyState()                         // "unlocked" | "locked" | "unsupported"

// Sleutelstatus op de server
fetch('api/keys.php',{headers:{Accept:'application/json'}}).then(r=>r.json()).then(console.log)
// → { has_keys, has_recovery, ... }

// Kluisstatus van een band (vervang 1 door band-id)
fetch('api/vault.php?band_id=1',{headers:{Accept:'application/json'}}).then(r=>r.json()).then(console.log)

// WebCrypto beschikbaar?
!!(window.crypto && crypto.subtle)

// Welke sleutels staan in de sessie?
Object.keys(sessionStorage).filter(k => k.startsWith('lg_'))
```

---

## 10. Bekende beperkingen (geen bug)

- **BDK-rotatie** is nog niet actief: een verwijderd lid verliest direct toegang
  (zijn `band_member_keys`-rij verdwijnt), maar de bandsleutel zelf wordt niet
  ververst. Een eerder lid dat de BDK lokaal had bewaard zou oude, gecachte
  ciphertext nog kunnen openen.
- **Herstel** koppelt alleen de kluis aan een nieuw wachtwoord, niet het
  login-wachtwoord zelf (aparte stap via Profiel).
- **Drumstructuur** wordt server-side gerenderd; de notatie passeert daarbij
  kortstondig de server (over TLS, wordt niet opgeslagen). Bewuste keuze.
- **Cache na ontgrendelen**: voor versleutelde bands bevat localStorage
  ciphertext; offline werken kan dus pas ná het ontgrendelen in die sessie.
