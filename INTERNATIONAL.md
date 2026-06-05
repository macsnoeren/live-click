# Internationale klanten — btw & belasting

Analyse van wat er fiscaal/administratief verandert zodra niet alleen Nederlanders,
maar ook buitenlandse klanten de dienst (LiveGig, een digitale abonnementsdienst)
gebruiken en betalen.

> **Disclaimer:** dit is geen fiscaal advies. Het is een overzicht om het gesprek
> met een boekhouder / de Belastingdienst gericht te kunnen voeren. De regels
> (vooral btw) zijn precies en kunnen wijzigen. Stem dit af vóór de eerste
> buitenlandse betaling.

## Het belangrijkste inzicht: het gaat om btw, niet om inkomstenbelasting

- **Inkomsten-/winstbelasting** — de omzet blijft Nederlandse bedrijfswinst,
  ongeacht waar de klant zit. Een Duitse of Amerikaanse klant verandert daar
  niets aan; je betaalt er gewoon in Nederland belasting over. Dit is het
  eenvoudige deel.
- **Btw** — hier zit alle complexiteit. De dienst is een **digitale dienst**
  (elektronisch geleverde dienst). Daarvoor is de btw verschuldigd in het **land
  van de klant**, niet in dat van de leverancier.

## Vier situaties (de kern)

| Klant | Btw-behandeling |
|---|---|
| **NL consument/bedrijf** | Zoals nu: 21% NL-btw. |
| **EU-consument (B2C)** | Btw van het **land van de klant** (bv. 19% DE, 20% FR). Innen via de **OSS-regeling**. |
| **EU-bedrijf met geldig btw-nummer (B2B)** | **Btw verlegd** (reverse charge): 0% rekenen, klant draagt zelf af. Btw-nummer valideren (VIES) en op de factuur "btw verlegd" vermelden. |
| **Niet-EU (bv. VS, VK, Zwitserland)** | Buiten de EU-btw; meestal geen NL-btw. **Maar** sommige landen eisen lokale registratie (VK-btw, Noorwegen, Zwitserland, Australië GST) zodra je daar verkoopt. |

## De OSS-regeling en de €10.000-drempel

Voor EU-consumenten geldt een belangrijke versoepeling:

- **Onder €10.000/jaar** totale grensoverschrijdende B2C-omzet binnen de EU →
  je mag gewoon **NL-btw (21%)** blijven rekenen en normaal aangeven.
- **Boven €10.000** → registreren voor de **One Stop Shop (OSS)** bij de
  Belastingdienst: per land het juiste btw-tarief rekenen en **één
  kwartaal-OSS-aangifte** doen voor alle EU-landen samen (geen aparte
  registratie per land).

> De **KOR** (kleineondernemersregeling) geldt alleen binnenlands en helpt niet
> voor grensoverschrijdende verkopen.

## Wat dit technisch betekent voor de app (zelf-doen-route)

Doe je het zelf (met Mollie zoals nu), dan komt erbij:

1. **Land van de klant bepalen én bewijzen** — voor digitale diensten 2
   niet-tegenstrijdige locatiebewijzen bewaren (bv. factuuradres + IP/bankland).
   Mollie levert het land mee.
2. **Variabele btw-tarieven per EU-land** in het tariefsysteem. De huidige
   `pricing`-/factuurlogica gaat uit van één vast btw%; dat moet per land worden.
3. **B2B reverse charge** — btw-nummer kunnen invoeren, valideren (VIES) en de
   factuur aanpassen ("btw verlegd, art. 196 EU-btw-richtlijn").
4. **Facturen** — het juiste tarief / de juiste vermelding per situatie tonen.
5. **OSS-administratie** — omzet per land bijhouden voor de kwartaalaangifte.

Dit is een serieuze uitbreiding van de huidige (NL-only) implementatie.

## De grote afkorting: Merchant of Record

Met een **Merchant of Record** (bv. **Paddle** of **Lemon Squeezy**) wordt díe
partij juridisch de verkoper richting de klant:

- **Zij** regelen alle internationale btw/sales tax: registratie, juiste tarieven
  per land, OSS, VK-btw, US sales tax — alles.
- Jij krijgt een uitbetaling en hoeft je alleen om de **NL-inkomstenbelasting** te
  bekommeren.
- Nadeel: hogere fee (~5% i.p.v. Mollie's lage transactiekosten) en zwakkere
  iDEAL-ondersteuning.

De afweging kantelt zodra je serieus internationaal gaat. Voor "vooral NL + een
enkele buitenlander onder de €10k" is Mollie + (eventueel) OSS prima te overzien.
Wil je actief wereldwijd verkopen zonder fiscale rompslomp, dan is een MoR vaak
elke procent waard — het neemt de hele lijst hierboven over.

## Samenvatting / advies

1. **Onder €10k EU-omzet** — niets dringends veranderen: blijf 21% NL-btw rekenen,
   ook aan EU-consumenten. In de gaten houden.
2. **Boven €10k of bewust internationaal** — kies tussen:
   - **(a) OSS aanvragen** + de app uitbreiden met landtarieven en reverse charge, of
   - **(b) overstappen op een Merchant of Record** die het overneemt.
3. **Niet-EU klanten** — per land checken of er een registratieplicht ontstaat
   (meestal pas vanaf bepaalde drempels).
4. **Bespreek dit met een boekhouder** vóór de eerste buitenlandse betaling —
   vooral de OSS-vraag en of een MoR slimmer is.

## Gevolgen voor de codebase (indien zelf-doen)

Als gekozen wordt voor de zelf-doen-route, raakt dit minstens:

- `includes/pricing.php` / `pricing`-tabel — btw-percentage moet **per land**
  bepaald worden i.p.v. één vast `vat_percent`.
- `htdocs/live-click/factuur.php` — btw-regel/vermelding afhankelijk van
  EU-consument / EU-B2B (verlegd) / niet-EU.
- Klantgegevens — land en (optioneel) btw-nummer vastleggen + valideren (VIES).
- Een omzet-per-land-rapportage voor de OSS-aangifte.

Een concreet implementatieplan hiervoor is nog niet uitgewerkt; dit document legt
alleen de fiscale uitgangspunten vast.
