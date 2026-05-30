# Security

Dit document beschrijft het beveiligingsmodel van LiveGig, de bevindingen uit de
review van mei 2026, en hoe ze zijn opgelost. Het dient zowel als changelog
(wat is er gefixt en waarom) als als referentie voor toekomstige reviewers.

---

## 1. Beveiligingsmodel in het kort

* **Auth**: sessie-cookies met `HttpOnly`, `SameSite=Lax`, en `Secure` onder HTTPS.
* **2FA**: TOTP (RFC 6238) + single-use backup-codes (SHA-256-hashed).
* **Wachtwoorden**: bcrypt via `password_hash(PASSWORD_DEFAULT)`, minimaal 12 tekens.
* **CSRF**: per-sessie token, automatisch meegestuurd door jQuery's `ajaxSend` op
  alle niet-GET requests; hidden `_csrf` veld in formulieren.
* **Autorisatie**: band-lidmaatschap-check op alle multi-tenant data
  (songs, setlists, share/invite tokens).
* **Audit log**: gebeurtenistabel `audit_log` voor admin- en account-acties.
* **Rate limiting**: per-account en per-IP teller op login, TOTP, en 2FA-management.
* **Bearer tokens** (share, invite, remember-me): plaintext in URL/cookie,
  SHA-256-hash in de database.
* **Secrets**: niet in git — `includes/config.local.php` (gitignored) of env vars.

---

## 2. Bevindingen en oplossingen

Bevindingen zijn ingedeeld op impact (Kritiek / Hoog / Gemiddeld / Laag) en
voorzien van een ID voor cross-referencing.

### 2.1 Kritiek

| ID | Bevinding | Oplossing | Bestanden |
|---|---|---|---|
| **K1** | Spotify Client ID + Secret hardcoded in `includes/config.php` en gecommit naar git. | Secrets verplaatst naar `config.local.php` (gitignored) of env vars (`LIVEGIG_SPOTIFY_*`). `.gitignore` bijgewerkt. **Roteer de oude secret in het Spotify Dashboard — die staat nog in git history.** | `.gitignore`, `includes/config.php`, `includes/config.local.example.php` |
| **K2** | `api/songs.php` GET/POST/DELETE: elke ingelogde user kon songs van elke band lezen, bewerken, verwijderen. | `requireBandAccess()` op band_id; `getSongBandId()` voor eigenaarscheck bij update/delete. | `htdocs/live-click/api/songs.php`, `includes/auth.php` |
| **K3** | `api/setlists.php`: zelfde IDOR als K2, plus songs van andere bands konden worden gesmokkeld in een setlist. | Band-access check; bij setlist-POST worden songs gefilterd op `band_id = doel-band`. | `htdocs/live-click/api/setlists.php` |
| **K4** | Default `admin` / `admin` account werd geseed zonder `must_change_password`-flag; veel installaties bleven ongewijzigd. | Seed zet nu `must_change_password = 1`. Eerste login dwingt wachtwoordwijziging af. | `includes/db.php` |
| **K5** | Geen `session_regenerate_id()` na login — kwetsbaar voor session fixation. | `session_regenerate_id(true)` in `_completeLogin()` én `loginWithRememberToken()`. | `includes/auth.php` |
| **K6** | Geen CSRF-bescherming op enig endpoint. SameSite-attribuut ontbrak. | Per-sessie CSRF-token (`csrfToken()`); validatie via `csrfRequire()` op alle POST/PUT/DELETE/PATCH endpoints. jQuery `ajaxSend`-hook stuurt `X-CSRF-Token` automatisch mee. Hidden `_csrf` velden in login, 2FA, join, import. | `includes/auth.php`, `includes/header.php`, `includes/footer.php`, alle `api/*.php` en form-pagina's |
| **K7** | Sessiecookie zonder `HttpOnly`, `Secure`, of `SameSite`. | `session_set_cookie_params()` in `sessionStart()` vóór `session_start()`. Secure-flag auto-detect via HTTPS / X-Forwarded-Proto / poort 443. SameSite=Lax (Strict zou cross-site invite-links breken). | `includes/auth.php:sessionStart` |

### 2.2 Hoog

| ID | Bevinding | Oplossing | Bestanden |
|---|---|---|---|
| **H1** | Open redirect via `api/switch-band.php?redirect=`. | Centrale helper `safeLocalRedirect($candidate, $fallback)` valideert dat redirect een veilig relatief pad is (geen scheme, `//`, `\\`, CR/LF, of `:`). | `includes/auth.php`, `htdocs/live-click/api/switch-band.php` |
| **H2** | `login.php?next=` open redirect via permissieve regex. | Gebruikt nu dezelfde `safeLocalRedirect()` helper. | `htdocs/live-click/login.php` |
| **H3** | `api/songs.php` lekte `PDOException::getMessage()` aan de client (schema/queryinfo). | Error-message wordt naar `error_log()` geschreven; gebruiker krijgt generieke 500. | `htdocs/live-click/api/songs.php` |
| **H5** | `enable_2fa_start` kon worden aangeroepen ook als 2FA al actief was — een gekaapte sessie kon zo de authenticator vervangen. | `enable_2fa_start` en `enable_2fa_confirm` retourneren 409 als 2FA reeds actief. Gebruiker moet eerst expliciet `disable_2fa` (vereist huidige TOTP-code). | `htdocs/live-click/api/profile.php` |
| **H6 + M10** | Geen rate-limiting op login, 2FA, of 2FA-management → brute-force mogelijk. | Nieuwe `auth_attempts` tabel + `rateLimitCheck($bucket, $max, $window)` / `rateLimitRecord($bucket)`. Per-account (5/10 min) + per-IP (20/10 min) buckets. Returns HTTP 429 + `Retry-After`. Failsafe: DB-fout blokkeert niet. | `includes/db.php`, `includes/auth.php`, `htdocs/live-click/login.php`, `htdocs/live-click/api/profile.php` |
| **H7** | Account-enumeration: `password_verify` werd overgeslagen als user niet bestond → meetbaar verschil in responstijd. | `login()` voert áltijd `password_verify` uit (tegen `$dummyHash` als user niet bestaat). | `includes/auth.php:login` |
| **H8** | Wachtwoord-minimum was 6 (`register.php`) en 8 (`api/profile.php`); inconsistent en te kort. | Centrale `validatePasswordStrength()` enforced minimaal 12 tekens overal (register, change-password, admin user-create). | `includes/auth.php`, `register.php`, `api/profile.php`, `api/users.php` |
| **H9** | TOTP-secret werd via `api.qrserver.com` als QR-code naar een derde partij gestuurd bij setup. | `Totp::qrUrl()` gedeprecateerd. API stuurt `otpauth_uri` ipv `qr_url`. UI toont secret als kopieerbare tekst + `<a href="otpauth://...">`-link (mobiel tikt erop → opent direct authenticator-app). CSP-uitzondering verwijderd. **Secret verlaat de server niet meer.** | `includes/totp.php`, `htdocs/live-click/api/profile.php`, `htdocs/live-click/profile.php`, `includes/security_headers.php` |

### 2.3 Gemiddeld

| ID | Bevinding | Oplossing | Bestanden |
|---|---|---|---|
| **M1** | Share-token en invite-token werden plaintext in DB opgeslagen → bij DB-lek direct bruikbaar. | SHA-256-hash in DB, plaintext alleen 1× geretourneerd bij creatie. Eenmalige migratie hasht bestaande tokens (gedeelde URLs blijven werken). UI past zich aan: bestaande link toont geen URL meer, alleen "regenereer" knop. | `includes/db.php`, `htdocs/live-click/api/share.php`, `htdocs/live-click/api/invite.php`, `htdocs/live-click/public.php`, `htdocs/live-click/join.php`, `htdocs/live-click/bands.php` |
| **M2** | `api/search.php` doorzocht alle bands ongeacht lidmaatschap. | Beperkt tot bands waarvan de gebruiker lid is (admin ziet alles). | `htdocs/live-click/api/search.php` |
| **M4** | Geen lengtelimiet op `description` en `drum_notation` → DoS via gigantische velden naar `localStorage` van alle bandleden. | Max 4 KB description, 5 KB drum_notation. | `htdocs/live-click/api/songs.php` |
| **M6** | Remember-me-token werd 30 dagen lang niet geroteerd. | Bij elke succesvolle remember-me-login wordt het token vervangen (`createRememberToken` in `loginWithRememberToken`). | `includes/auth.php` |
| **M7** | Geen security headers. | Centrale `includes/security_headers.php` met CSP (default-src 'self'; frame-ancestors 'none'; form-action 'self'), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Strict-Transport-Security` (alleen onder HTTPS). Geladen vanuit `bootstrap.php`. | `includes/security_headers.php`, `htdocs/live-click/bootstrap.php` |
| **M8** | `logout.php` was een GET-request → CSRF-uitlog mogelijk. | `logout.php` accepteert alleen POST + CSRF. Header-menu rendert een mini-form. GET toont confirm-form (geen 405). | `htdocs/live-click/logout.php`, `includes/header.php` |
| **M9** | TOTP-vergelijking gebruikte `===` → niet constant-time. | `hash_equals()` in `Totp::verify`. | `includes/totp.php` |

### 2.4 Laag

| ID | Bevinding | Status |
|---|---|---|
| **L1** | Geen migratie-versietabel; `ALTER TABLE … ADD COLUMN` in try/catch wordt bij elke request gepoogd. | Cosmetisch — niet opgelost. |
| **L2** | `filemtime()` in CSS/JS query strings lekt server-tijden. | Cosmetisch — niet opgelost. |
| **L3** | Geen 2FA backup codes → verlies van phone = lockout. | **Opgelost.** Nieuwe tabel `totp_backup_codes`. 8 codes (10 cijfers, single-use, SHA-256-hashed). Eenmalig getoond bij activering. Login accepteert backup-code via `?backup=1`. Profile-pagina toont resterend aantal codes + "Nieuwe codes" knop (vereist huidige TOTP). |
| **L5** | `DrumSvg` gebruikt `htmlspecialchars(..., ENT_XML1)` (geen quote-escape). | Niet opgelost — labels/comments staan tussen text-tags, geen attribuutgebruik. Defense-in-depth: overweeg `ENT_QUOTES \| ENT_XML1`. |
| **L6** | Geen audit log van admin-acties. | **Opgelost.** Nieuwe `audit_log` tabel + `auditLog($action, $targetType, $targetId, $details)` helper. Toegepast op: `login.success`, `login.backup_code_used`, `user.create`, `user.update`, `user.password_reset_by_admin`, `user.password_change`, `user.2fa_enable`, `user.2fa_disable`, `user.2fa_backup_regenerate`, `band.delete`. Failsafe — een logfout blokkeert nooit de actie. |
| **L7/L8** | File/dir-permissies op `data/` zijn deploy-actie, niet code. | Zie installatie-richtlijnen onder. |

---

## 3. Threat model summary

LiveGig is een **multi-tenant SaaS-achtige app** voor coverband-drummers. Elke
band is een tenant; band-leden delen songs, setlists en uitnodigings-/deellinks.

**Vertrouwensgrenzen:**

* **Anoniem (geen sessie)**: kan public.php?t=… (share-token) bekijken, en via
  een invite-link join.php?token=… een uitnodiging accepteren ná inloggen. Alles
  anders is achter `requireLogin()`.
* **User**: ziet zijn eigen bands en hun data. CRUD op songs, setlists, en
  share/invite tokens binnen die bands. Kan eigen profiel beheren (wachtwoord, 2FA).
* **Band-leader**: idem, plus invite/share-tokens beheren en leden uit zijn
  band verwijderen.
* **Admin**: alle bands, alle users; kan accounts aanmaken, rollen toewijzen,
  bands verwijderen.

**Wat we beschermen tegen:**

* **CSRF** — alle state-changing endpoints vereisen CSRF-token.
* **IDOR / horizontal privesc** — alle multi-tenant endpoints checken band-lidmaatschap.
* **Session fixation / hijack** — regenerate na auth; HttpOnly + Secure + SameSite cookies.
* **Brute force op login/2FA** — rate-limiting per account + per IP.
* **Account enumeration** — login-timing constant gemaakt.
* **Open redirect** — alle redirects via `safeLocalRedirect()` allowlist.
* **Stored bearer tokens** — hashes in DB; plaintext alleen in URL/cookie.
* **XSS** — server-side `htmlspecialchars()` overal, client-side `escHtml()` voor
  user-controlled strings die naar `innerHTML` gaan.
* **Secret leakage** — TOTP-secret verlaat de server niet meer (geen externe QR).

**Niet expliciet beschermd:**

* **Targeted social engineering** van band-leiders die share/invite-links lekken.
* **Server-side request forgery** in de search-fallback (Tunebat/Spotify/MB
  endpoints zijn hardcoded — gebruiker kan de URL niet beïnvloeden).
* **Dependency vulnerabilities** in Bootstrap/jQuery — lokale kopieën in
  `assets/vendor/`; werk handmatig bij (zie README).

---

## 4. Configuratie / Deploy

### 4.1 Secrets

Zet Spotify- en GetSongBPM-credentials in **één** van deze twee plekken (niet beide):

* `includes/config.local.php` (kopie van `config.local.example.php`, gitignored), of
* Omgevingsvariabelen `LIVEGIG_SPOTIFY_CLIENT_ID`, `LIVEGIG_SPOTIFY_CLIENT_SECRET`, `LIVEGIG_GETSONGBPM_API_KEY`.

### 4.2 File-permissies

```bash
# data/ alleen voor de webserver-user
chown -R www-data:www-data /projects/app.blastcoverband.nl/data
chmod 750 /projects/app.blastcoverband.nl/data
chmod 640 /projects/app.blastcoverband.nl/data/livegig.db
chmod 600 /projects/app.blastcoverband.nl/data/.spotify_token

# includes/config.local.php — alleen leesbaar door webserver
chmod 640 /projects/app.blastcoverband.nl/includes/config.local.php
```

### 4.3 Webserver

* HTTPS verplicht — anders zet PHP de `Secure`-flag niet op cookies.
* Achter een trusted proxy? Zet `$_SERVER['HTTP_X_FORWARDED_PROTO']` correct
  door (Apache: `mod_remoteip`; Nginx: `proxy_set_header`).
* `includes/` en `data/` MOETEN buiten de DocumentRoot blijven.

### 4.4 Database

Bij eerste request worden tabellen + migraties uitgevoerd. Eenmalige migraties
die je niet wilt missen na deploy:

1. **Token hashing** (M1) — bestaande plaintext share/invite tokens worden
   omgezet naar SHA-256-hash. Bestaande gedeelde URLs blijven werken.
2. **Nieuwe tabellen** — `auth_attempts`, `audit_log`, `totp_backup_codes`.

**Maak een DB-backup vóór de eerste deploy** van deze veranderingen.

### 4.5 Default admin

Na eerste request bestaat `admin` / `admin` met `must_change_password = 1`. De
allereerste login forceert wachtwoordwijziging naar minimaal 12 tekens. **Zet
direct ook 2FA aan en bewaar de backup-codes.**

---

## 5. Toekomstig werk (niet kritiek)

* **Backup-codes downloaden als .txt** — UX-verbetering.
* **Nonce-based CSP** — vervang `'unsafe-inline'` zodra alle inline `<script>` /
  `onclick=` is gemigreerd naar externe handlers.
* **Migratie-versietabel** (L1).
* **Hash-based cache-busting** voor static assets (L2).
* **Audit log viewer** voor admins (UI).
* **2FA enforcement policy** — optioneel admin-instelbaar.
* **Per-IP trust-proxy config** — als achter Cloudflare/Nginx wordt gedraaid.

---

## 6. Reporting

Vond je een kwetsbaarheid? Mail naar [macsnoeren@gmail.com](mailto:macsnoeren@gmail.com).
Geef ons 90 dagen om te fixen voordat je publiceert.
