# Privacybeleid LiveGig

_Laatst bijgewerkt: 2 juni 2026_

Dit privacybeleid beschrijft welke persoonsgegevens de webapplicatie **LiveGig**
verwerkt, met welk doel, op welke grondslag, hoe lang ze worden bewaard en welke
rechten je hebt. Het beleid is opgesteld conform de Algemene verordening
gegevensbescherming (AVG/GDPR).

> Dit document gaat over de **juridische** verwerking van persoonsgegevens. De
> technische opzet van de end-to-end-versleuteling staat in [PRIVACY.md](PRIVACY.md);
> het beveiligingsmodel in [SECURITY.md](SECURITY.md).

---

## 1. Verwerkingsverantwoordelijke

LiveGig is een zelf-gehoste applicatie. De **verwerkingsverantwoordelijke** is de
beheerder van de installatie waarop jij een account hebt. Voor de installatie op
`app.blastcoverband.nl` is dat:

- **Contact:** macsnoeren@gmail.com

Heb je een account op een andere installatie, dan is de beheerder daarvan
verantwoordelijk. Dit beleid beschrijft de standaardverwerking van de software;
een beheerder kan aanvullende afspraken maken.

---

## 2. Welke persoonsgegevens verwerken we

### 2.1 Accountgegevens
| Gegeven | Toelichting |
|---|---|
| Gebruikersnaam | Door jou gekozen, gebruikt om in te loggen en je te tonen aan bandleden. |
| E-mailadres | Voor identificatie en accountbeheer. |
| Wachtwoord | Nooit als platte tekst opgeslagen — alleen als **bcrypt-hash**. |
| Rol | `user` of `admin`. |
| Bandlidmaatschap | In welke bands je zit en met welke rol (`member` / `leader`). |

### 2.2 Beveiligingsgegevens
| Gegeven | Toelichting |
|---|---|
| 2FA-secret (TOTP) | Alleen als je tweefactorauthenticatie inschakelt. Verlaat de server niet. |
| 2FA-back-upcodes | Als SHA-256-hash opgeslagen, eenmalig getoond bij activering. |
| "Onthoud mij"-tokens | Alleen de SHA-256-hash staat in de database; de platte token staat in je cookie. |
| IP-adres | Vastgelegd in het audit-logboek bij gevoelige acties (login, accountwijzigingen) en gebruikt voor rate-limiting tegen brute-force-aanvallen. |
| Inlogpogingen | Tijdelijke tellers per account en per IP om misbruik te beperken. |
| Audit-logboek | Tijdstip, actor, IP en type actie bij admin- en accountgebeurtenissen. |

### 2.3 Inhoudsgegevens (door jou ingevoerd)
Repertoire (nummers, artiest, BPM, toonsoort, notities, drumstructuur), setlijsten
en banden. Dit zijn doorgaans **geen** persoonsgegevens, maar notities kunnen die
bevatten. Wanneer de **kluis (E2EE)** voor een band is ingeschakeld, wordt deze
inhoud **versleuteld** opgeslagen en is die voor de server en de beheerder
onleesbaar (zie [PRIVACY.md](PRIVACY.md)).

### 2.4 Wat we **niet** verwerken
- Geen trackingcookies, geen advertenties, geen analytics van derden.
- Geen profilering of geautomatiseerde besluitvorming.
- Geen verkoop of verhuur van gegevens aan derden.

---

## 3. Doeleinden en grondslagen

| Doel | Grondslag (AVG art. 6) |
|---|---|
| Account aanmaken en de dienst leveren (inloggen, repertoire/setlijsten beheren) | Uitvoering van de overeenkomst (art. 6.1.b) |
| Beveiliging: 2FA, rate-limiting, audit-logboek, sessiebeheer | Gerechtvaardigd belang — beveiliging van de dienst en de gegevens (art. 6.1.f) |
| Tweefactorauthenticatie inschakelen | Toestemming (art. 6.1.a) — optioneel, jij kiest dit zelf |
| Wettelijke verplichtingen (indien van toepassing) | Wettelijke plicht (art. 6.1.c) |

---

## 4. Cookies en lokale opslag

LiveGig gebruikt **alleen functionele** cookies en lokale opslag:

| Item | Type | Doel |
|---|---|---|
| Sessiecookie | Cookie (`HttpOnly`, `SameSite=Lax`, `Secure` onder HTTPS) | Je ingelogde sessie onthouden. |
| "Onthoud mij"-cookie | Cookie | Ingelogd blijven tussen bezoeken (optioneel). |
| `localStorage` | Browseropslag | Nummers, setlijsten en drumstructuren cachen zodat het dashboard **offline** werkt (bv. op het podium zonder internet). |

Er worden geen cookies geplaatst voor tracking of marketing. Hiervoor is geen
cookietoestemmingsbanner vereist.

---

## 5. Ontvangers en externe diensten

### 5.1 Hosting
De gegevens staan in een SQLite-database op de server van de beheerder. Een
eventuele hostingpartij verwerkt gegevens uitsluitend in opdracht van de
verwerkingsverantwoordelijke (verwerker).

### 5.2 Nummer-zoekdiensten
Bij het opzoeken van een nummer stuurt de applicatie alleen de **zoekterm**
(titel/artiest) naar externe muziekdiensten — **nooit** je persoonsgegevens:

- **Tunebat**, **GetSongBPM**, **Spotify** en **MusicBrainz** (waterval).

Spotify is gevestigd in de VS. Er worden alleen muziek-zoekopdrachten gedeeld,
geen accountgegevens. Gebruik je de zoekfunctie niet, dan wordt er niets gedeeld.

---

## 6. Bewaartermijnen

| Gegeven | Bewaartermijn |
|---|---|
| Account- en inhoudsgegevens | Tot je het account (laat) verwijderen. |
| Inlogpogingen (rate-limiting) | Automatisch opgeruimd na uiterlijk 1 uur. |
| "Onthoud mij"-tokens | Tot de vervaldatum of bij uitloggen; bij elk gebruik geroteerd. |
| Audit-logboek | Bewaard ten behoeve van beveiliging en verantwoording; de beheerder bepaalt de termijn. |

---

## 7. Beveiliging

LiveGig past technische en organisatorische maatregelen toe, waaronder:
wachtwoord-hashing (bcrypt), optionele tweefactorauthenticatie (TOTP),
CSRF-bescherming, rate-limiting, beveiligde sessiecookies, security headers, en
optionele **end-to-end-versleuteling** per band. Details staan in
[SECURITY.md](SECURITY.md) en [PRIVACY.md](PRIVACY.md).

---

## 8. Jouw rechten

Op grond van de AVG heb je recht op:

- **Inzage** in je persoonsgegevens.
- **Rectificatie** (correctie) van onjuiste gegevens.
- **Verwijdering** ("recht op vergetelheid").
- **Beperking** van de verwerking.
- **Dataportabiliteit** (gegevens overdragen).
- **Bezwaar** tegen verwerking op grond van gerechtvaardigd belang.
- **Intrekken van toestemming** (bv. 2FA uitschakelen), zonder terugwerkende kracht.

Een verzoek kun je richten aan de beheerder (zie §1). Veel rechten kun je zelf
uitvoeren via *Profiel* (wachtwoord, 2FA) of door je account te laten verwijderen.

---

## 9. Klachten

Ben je het oneens met hoe je gegevens worden verwerkt, neem dan eerst contact op
met de beheerder. Je hebt ook het recht een klacht in te dienen bij de
toezichthouder, de **Autoriteit Persoonsgegevens** (autoriteitpersoonsgegevens.nl).

---

## 10. Datalekken

Bij een datalek met risico voor jouw rechten en vrijheden meldt de
verwerkingsverantwoordelijke dit binnen 72 uur bij de Autoriteit Persoonsgegevens
en, indien vereist, aan de betrokkenen. Vermoed je een lek of kwetsbaarheid? Mail
naar macsnoeren@gmail.com (zie ook [SECURITY.md](SECURITY.md) §6).

---

## 11. Wijzigingen

Dit privacybeleid kan worden aangepast wanneer de applicatie of regelgeving
verandert. De datum bovenaan geeft de laatste wijziging aan.
