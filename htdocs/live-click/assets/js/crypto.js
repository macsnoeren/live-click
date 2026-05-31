/* =====================================================================
 * LiveGig — E2EE cryptomodule (fase 1)
 *
 * Bevat de bouwstenen voor end-to-end-versleuteling (zie PRIVACY.md):
 *   - PBKDF2 (wachtwoord → KEK)
 *   - RSA-OAEP sleutelpaar per gebruiker
 *   - AES-256-GCM voor blobs en het in-/uitpakken van sleutels
 *   - een sessie-keystore die de ontsleutelde privésleutel in sessionStorage
 *     bewaart (overleeft paginanavigatie binnen het tabblad; gaat nooit naar
 *     de server; wordt gewist bij uitloggen)
 *
 * Fase 1 gebruikt hiervan: KEK afleiden, sleutelpaar genereren, privésleutel
 * in-/uitpakken, en de bootstrap-flow. De AES/RSA-helpers voor bandsleutels
 * en blobs staan er vast in voor de volgende fasen.
 * ===================================================================== */
(function (global) {
    'use strict';

    var subtle = (global.crypto && global.crypto.subtle) || null;
    var PBKDF2_ITERS = 310000;

    /* ── base64 ⇄ ArrayBuffer ─────────────────────────────────────── */
    function bufToB64(buf) {
        var bytes = new Uint8Array(buf), bin = '';
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin);
    }
    function b64ToBuf(b64) {
        var bin = atob(b64), bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes.buffer;
    }
    function randomBytes(n) {
        var b = new Uint8Array(n);
        global.crypto.getRandomValues(b);
        return b;
    }
    var enc = new TextEncoder();
    var dec = new TextDecoder();

    /* ── KEK: wachtwoord → AES-GCM-sleutel via PBKDF2 ─────────────── */
    function deriveKEK(password, saltBytes) {
        return subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey'])
            .then(function (base) {
                return subtle.deriveKey(
                    { name: 'PBKDF2', salt: saltBytes, iterations: PBKDF2_ITERS, hash: 'SHA-256' },
                    base,
                    { name: 'AES-GCM', length: 256 },
                    false,
                    ['encrypt', 'decrypt']
                );
            });
    }

    /* ── AES-256-GCM: blob {v,iv,ct} ⇄ data ───────────────────────── */
    function aesEncryptBytes(key, bytes) {
        var iv = randomBytes(12);
        return subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, bytes).then(function (ct) {
            return { v: 1, iv: bufToB64(iv), ct: bufToB64(ct) };
        });
    }
    function aesDecryptBytes(key, blob) {
        return subtle.decrypt({ name: 'AES-GCM', iv: new Uint8Array(b64ToBuf(blob.iv)) },
            key, b64ToBuf(blob.ct));
    }
    function aesEncryptJSON(key, obj) {
        return aesEncryptBytes(key, enc.encode(JSON.stringify(obj)));
    }
    function aesDecryptJSON(key, blob) {
        return aesDecryptBytes(key, blob).then(function (buf) { return JSON.parse(dec.decode(buf)); });
    }
    /* Random AES-256-GCM-sleutel (bv. een Band Data Key) */
    function aesGenKey(extractable) {
        return subtle.generateKey({ name: 'AES-GCM', length: 256 }, !!extractable, ['encrypt', 'decrypt']);
    }
    function aesExportRawB64(key) {
        return subtle.exportKey('raw', key).then(bufToB64);
    }
    function aesImportRawB64(b64, extractable) {
        return subtle.importKey('raw', b64ToBuf(b64), { name: 'AES-GCM' }, !!extractable, ['encrypt', 'decrypt']);
    }

    /* ── RSA-OAEP sleutelpaar per gebruiker ───────────────────────── */
    function generateKeypair() {
        return subtle.generateKey(
            { name: 'RSA-OAEP', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
            true,
            ['encrypt', 'decrypt', 'wrapKey', 'unwrapKey']
        );
    }
    function exportPubB64(pubKey) {
        return subtle.exportKey('spki', pubKey).then(bufToB64);
    }
    function importPubB64(b64) {
        return subtle.importKey('spki', b64ToBuf(b64), { name: 'RSA-OAEP', hash: 'SHA-256' }, true, ['encrypt', 'wrapKey']);
    }
    function importPrivPkcs8(buf) {
        return subtle.importKey('pkcs8', buf, { name: 'RSA-OAEP', hash: 'SHA-256' }, true, ['decrypt', 'unwrapKey']);
    }

    /* ── Privésleutel in-/uitpakken onder de KEK ──────────────────── */
    function wrapPrivkey(privKey, kek) {
        return subtle.exportKey('pkcs8', privKey).then(function (pkcs8) {
            return aesEncryptBytes(kek, pkcs8);
        });
    }
    // Retourneert { key: CryptoKey, pkcs8b64 } zodat de caller de privésleutel
    // kan cachen in sessionStorage zonder opnieuw het wachtwoord nodig te hebben.
    function unwrapPrivkey(blob, kek) {
        return aesDecryptBytes(kek, blob).then(function (pkcs8) {
            return importPrivPkcs8(pkcs8).then(function (key) {
                return { key: key, pkcs8b64: bufToB64(pkcs8) };
            });
        });
    }

    /* ── Bandsleutel (BDK) in-/uitpakken met RSA (voor latere fasen) ─ */
    function wrapBdkForPub(bdk, pubKey) {
        return subtle.wrapKey('raw', bdk, pubKey, { name: 'RSA-OAEP' }).then(bufToB64);
    }
    function unwrapBdkWithPriv(wrappedB64, privKey, extractable) {
        return subtle.unwrapKey('raw', b64ToBuf(wrappedB64), privKey, { name: 'RSA-OAEP' },
            { name: 'AES-GCM', length: 256 }, !!extractable, ['encrypt', 'decrypt']);
    }

    global.LGCrypto = {
        available: !!subtle,
        bufToB64: bufToB64, b64ToBuf: b64ToBuf, randomBytes: randomBytes,
        deriveKEK: deriveKEK,
        aesEncryptBytes: aesEncryptBytes, aesDecryptBytes: aesDecryptBytes,
        aesEncryptJSON: aesEncryptJSON, aesDecryptJSON: aesDecryptJSON, aesGenKey: aesGenKey,
        aesExportRawB64: aesExportRawB64, aesImportRawB64: aesImportRawB64,
        generateKeypair: generateKeypair, exportPubB64: exportPubB64, importPubB64: importPubB64,
        importPrivPkcs8: importPrivPkcs8,
        wrapPrivkey: wrapPrivkey, unwrapPrivkey: unwrapPrivkey,
        wrapBdkForPub: wrapBdkForPub, unwrapBdkWithPriv: unwrapBdkWithPriv
    };

    /* =================================================================
     * Sessie-keystore + bootstrap
     * ================================================================= */
    var SS_PRIV = 'lg_priv';     // pkcs8/base64 van de ontsleutelde privésleutel
    var SS_PW   = 'lg_pw_tmp';   // wachtwoord, kortstondig tussen login en eerste pagina

    var _privKeyMem = null;      // geïmporteerde CryptoKey (cache binnen één pagina)

    function _ss(key) { try { return sessionStorage.getItem(key); } catch (e) { return null; } }
    function _ssSet(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
    function _ssDel(k) { try { sessionStorage.removeItem(k); } catch (e) {} }

    function csrfHeaders() {
        var h = { 'Content-Type': 'application/json' };
        if (global.LG_CSRF) h['X-CSRF-Token'] = global.LG_CSRF;
        return h;
    }

    /** Status 'unlocked' | 'locked' | 'unsupported'. */
    function keyState() {
        if (!subtle) return 'unsupported';
        return _ss(SS_PRIV) ? 'unlocked' : 'locked';
    }

    /** Geeft de geïmporteerde privésleutel (of null als vergrendeld). */
    function getPrivateKey() {
        if (_privKeyMem) return Promise.resolve(_privKeyMem);
        var b64 = _ss(SS_PRIV);
        if (!b64) return Promise.resolve(null);
        return importPrivPkcs8(b64ToBuf(b64)).then(function (k) { _privKeyMem = k; return k; });
    }

    function lock() {
        _privKeyMem = null;
        _ssDel(SS_PRIV);
        _ssDel(SS_PW);
        // Ook alle in de sessie gecachede bandsleutels (BDK's) wissen.
        try {
            var rm = [];
            for (var i = 0; i < sessionStorage.length; i++) {
                var k = sessionStorage.key(i);
                if (k && k.indexOf('lg_bdk_') === 0) rm.push(k);
            }
            rm.forEach(_ssDel);
        } catch (e) {}
    }

    /**
     * Zorgt dat de privésleutel beschikbaar is. Wordt op elke ingelogde pagina
     * aangeroepen (via footer.php). Idempotent en stil:
     *   - al ontgrendeld            → klaar
     *   - geen sleutels + wachtwoord → genereer sleutelpaar en upload
     *   - wel sleutels + wachtwoord  → ontsleutel privésleutel
     *   - geen wachtwoord            → vergrendeld laten (fase 2/7: ontgrendel-prompt)
     */
    function bootstrap() {
        if (!subtle) return Promise.resolve('unsupported');
        if (_ss(SS_PRIV)) return Promise.resolve('unlocked');

        var pw = _ss(SS_PW);
        return fetch('api/keys.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (st) {
                if (!st.ok) return 'locked';

                if (!st.has_keys) {
                    if (!pw) return 'locked';
                    return _createKeys(pw).then(function () { _ssDel(SS_PW); return 'unlocked'; });
                }

                if (!pw) return 'locked';
                var salt = new Uint8Array(b64ToBuf(st.kdf_salt));
                return deriveKEK(pw, salt)
                    .then(function (kek) { return unwrapPrivkey(JSON.parse(st.enc_privkey), kek); })
                    .then(function (res) {
                        _privKeyMem = res.key;
                        _ssSet(SS_PRIV, res.pkcs8b64);
                        _ssDel(SS_PW);
                        return 'unlocked';
                    })
                    .catch(function () {
                        // Verkeerd wachtwoord of sleutel onder ander wachtwoord ingepakt
                        // (bv. na admin-reset → herstelflow in fase 2). Niet crashen.
                        _ssDel(SS_PW);
                        return 'locked';
                    });
            })
            .catch(function () { return 'locked'; });
    }

    function _createKeys(pw) {
        var salt = randomBytes(16), pkcs8b64;
        return generateKeypair().then(function (kp) {
            return Promise.all([
                exportPubB64(kp.publicKey),
                subtle.exportKey('pkcs8', kp.privateKey)
            ]).then(function (out) {
                var pubB64 = out[0]; pkcs8b64 = bufToB64(out[1]);
                return deriveKEK(pw, salt).then(function (kek) {
                    return aesEncryptBytes(kek, out[1]).then(function (encPriv) {
                        return fetch('api/keys.php', {
                            method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                            body: JSON.stringify({
                                action: 'init',
                                kdf_salt: bufToB64(salt),
                                pubkey: pubB64,
                                enc_privkey: JSON.stringify(encPriv)
                            })
                        }).then(function (r) { return r.json(); }).then(function (res) {
                            if (!res.ok) throw new Error(res.error || 'init mislukt');
                            _ssSet(SS_PRIV, pkcs8b64);
                        });
                    });
                });
            });
        });
    }

    /**
     * Herversleutelt de privésleutel onder een nieuw wachtwoord (bij wijzigen).
     * Geeft { kdf_salt, enc_privkey } terug om met de wachtwoordwijziging mee te
     * sturen, of null als er (nog) geen sleutelpaar is.
     */
    function rewrapForNewPassword(newPassword) {
        var b64 = _ss(SS_PRIV);
        if (!b64) return Promise.resolve(null);
        var salt = randomBytes(16);
        return deriveKEK(newPassword, salt).then(function (kek) {
            return aesEncryptBytes(kek, b64ToBuf(b64)).then(function (encPriv) {
                return { kdf_salt: bufToB64(salt), enc_privkey: JSON.stringify(encPriv) };
            });
        });
    }

    /* ── Herstelcodes (zie PRIVACY.md §8) ─────────────────────────────
     * Genereert een herstelcode, leidt daaruit een recovery-KEK af, verpakt de
     * privésleutel ermee en uploadt die als tweede kopie. Retourneert de code
     * (eenmalig tonen). Vereist een ontgrendelde privésleutel in de sessie. */
    function genRecoveryCode() {
        // 5 groepen van 5 tekens uit een verwarringsarm alfabet → ~125 bits
        var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var bytes = randomBytes(25), out = [];
        for (var i = 0; i < 25; i++) {
            out.push(alphabet[bytes[i] % alphabet.length]);
            if (i % 5 === 4 && i !== 24) out.push('-');
        }
        return out.join('');
    }

    function setupRecovery() {
        var b64 = _ss(SS_PRIV);
        if (!b64) return Promise.reject(new Error('Kluis niet ontgrendeld.'));
        var code = genRecoveryCode();
        var salt = randomBytes(16);
        // Spaties/streepjes negeren bij het afleiden, zodat invoer soepel is.
        var norm = code.replace(/[^A-Za-z0-9]/g, '');
        return deriveKEK(norm, salt).then(function (kek) {
            return aesEncryptBytes(kek, b64ToBuf(b64)).then(function (encPriv) {
                return fetch('api/keys.php', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify({
                        action: 'set_recovery',
                        recovery_salt: bufToB64(salt),
                        enc_privkey_recovery: JSON.stringify(encPriv)
                    })
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res.ok) throw new Error(res.error || 'Herstel instellen mislukt');
                    return code;
                });
            });
        });
    }

    global.LGKeys = {
        SS_PW: SS_PW,
        bootstrap: bootstrap,
        keyState: keyState,
        getPrivateKey: getPrivateKey,
        rewrapForNewPassword: rewrapForNewPassword,
        setupRecovery: setupRecovery,
        lock: lock
    };

    /* =================================================================
     * LGVault — Band Data Keys (BDK) beheren en gebruiken
     *
     * De BDK versleutelt straks de inhoud van een band (fase 3+). Hier:
     *   - status(bandId)      → kluisstatus + ledenlijst (voor de leider)
     *   - enableVault(bandId) → BDK genereren, voor elk lid met publieke
     *                           sleutel inpakken, kluis aanzetten
     *   - getBandKey(bandId)  → BDK (CryptoKey) uit cache of via uitpakken
     *   - grantMissing(...)   → leden zonder kopie alsnog de BDK geven
     * BDK's worden per band/versie in sessionStorage gecachet (lg_bdk_<id>_v<ver>).
     * ================================================================= */
    var _bdkMem = {}; // bandId → CryptoKey (binnen één pagina)

    function _bdkCacheKey(bandId, ver) { return 'lg_bdk_' + bandId + '_v' + ver; }

    function vaultStatus(bandId) {
        return fetch('api/vault.php?band_id=' + encodeURIComponent(bandId),
            { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); });
    }

    function enableVault(bandId) {
        if (!subtle) return Promise.reject(new Error('Versleuteling niet beschikbaar in deze browser.'));
        return vaultStatus(bandId).then(function (st) {
            if (!st.ok) throw new Error(st.error || 'Kan kluisstatus niet ophalen.');
            if (st.is_encrypted) throw new Error('De kluis staat al aan.');
            if (!st.can_manage) throw new Error('Alleen de bandleider kan de kluis aanzetten.');

            var members = st.members || [];
            var ver = st.key_version || 1;
            var withKey = members.filter(function (m) { return m.pubkey; });
            var without = members.filter(function (m) { return !m.pubkey; });

            // De uitvoerende leider moet zelf een sleutel hebben, anders sluit
            // hij zichzelf buiten (en kan de server de kluis niet aanzetten).
            var meHasKey = members.some(function (m) { return m.is_me && m.pubkey; });
            if (!meHasKey) {
                throw new Error(
                    'Je eigen versleutelingssleutel staat nog niet geregistreerd op de server. ' +
                    'Log één keer uit en weer in (met je wachtwoord) zodat je sleutel wordt ' +
                    'aangemaakt, en probeer het daarna opnieuw.');
            }
            if (!withKey.length) {
                throw new Error('Nog geen enkel bandlid heeft een versleutelingssleutel.');
            }

            return aesGenKey(true).then(function (bdk) {
                // BDK voor elk lid met publieke sleutel inpakken
                var jobs = withKey.map(function (m) {
                    return importPubB64(m.pubkey)
                        .then(function (pub) { return wrapBdkForPub(bdk, pub); })
                        .then(function (wrapped) { return { user_id: m.user_id, wrapped_bdk: wrapped }; });
                });
                return Promise.all(jobs).then(function (keys) {
                    return fetch('api/vault.php', {
                        method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                        body: JSON.stringify({ action: 'enable', band_id: bandId, keys: keys })
                    }).then(function (r) { return r.json(); }).then(function (res) {
                        if (!res.ok) throw new Error(res.error || 'Kluis aanzetten mislukt.');
                        // BDK meteen cachen voor deze sessie
                        return aesExportRawB64(bdk).then(function (raw) {
                            _ssSet(_bdkCacheKey(bandId, ver), raw);
                            _bdkMem[bandId] = bdk;
                            return { ok: true, without_keys: without.map(function (m) { return m.username; }) };
                        });
                    });
                });
            });
        });
    }

    function getBandKey(bandId) {
        if (_bdkMem[bandId]) return Promise.resolve(_bdkMem[bandId]);
        return vaultStatus(bandId).then(function (st) {
            if (!st.ok || !st.is_encrypted) return null;
            var ver = st.key_version || 1;
            var cached = _ss(_bdkCacheKey(bandId, ver));
            if (cached) {
                return aesImportRawB64(cached, true).then(function (k) { _bdkMem[bandId] = k; return k; });
            }
            if (!st.my_wrapped_bdk) return null; // (nog) geen toegang tot de kluis
            return getPrivateKey().then(function (priv) {
                if (!priv) return null;
                return unwrapBdkWithPriv(st.my_wrapped_bdk, priv, true).then(function (bdk) {
                    _bdkMem[bandId] = bdk;
                    return aesExportRawB64(bdk).then(function (raw) {
                        _ssSet(_bdkCacheKey(bandId, ver), raw);
                        return bdk;
                    });
                });
            });
        });
    }

    /* Leden zonder eigen BDK-kopie er alsnog één geven (vereist hun pubkey en
     * dat jij de BDK hebt). Retourneert het aantal uitgedeelde sleutels. */
    function grantMissing(bandId) {
        return Promise.all([vaultStatus(bandId), getBandKey(bandId)]).then(function (out) {
            var st = out[0], bdk = out[1];
            if (!st.ok || !st.is_encrypted || !st.can_manage || !bdk) return 0;
            var missing = (st.members || []).filter(function (m) { return m.pubkey && !m.has_key; });
            if (!missing.length) return 0;
            var jobs = missing.map(function (m) {
                return importPubB64(m.pubkey)
                    .then(function (pub) { return wrapBdkForPub(bdk, pub); })
                    .then(function (wrapped) { return { user_id: m.user_id, wrapped_bdk: wrapped }; });
            });
            return Promise.all(jobs).then(function (keys) {
                return fetch('api/vault.php', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify({ action: 'grant', band_id: bandId, keys: keys })
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res.ok) throw new Error(res.error || 'Sleutels uitdelen mislukt.');
                    return keys.length;
                });
            });
        });
    }

    global.LGVault = {
        status: vaultStatus,
        enableVault: enableVault,
        getBandKey: getBandKey,
        grantMissing: grantMissing
    };
})(window);
