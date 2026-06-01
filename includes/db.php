<?php
define('DB_PATH', __DIR__ . '/../data/livegig.db');

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON;');
        initSchema($db);
    }
    return $db;
}

function initSchema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS bands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS band_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            band_id INTEGER NOT NULL,
            role TEXT DEFAULT 'member',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE,
            UNIQUE(user_id, band_id)
        );

        CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            artist TEXT NOT NULL,
            bpm INTEGER,
            song_key TEXT,
            duration TEXT,
            starts TEXT,
            description TEXT,
            band_id INTEGER,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS setlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            band_id INTEGER NOT NULL,
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS setlist_songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setlist_id INTEGER NOT NULL,
            song_id INTEGER NOT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (setlist_id) REFERENCES setlists(id) ON DELETE CASCADE,
            FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS band_invites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            band_id INTEGER NOT NULL UNIQUE,
            token TEXT NOT NULL UNIQUE,
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS auth_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bucket TEXT NOT NULL,
            ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_auth_attempts_bucket_ts ON auth_attempts(bucket, ts);

        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actor_id INTEGER,
            actor_username TEXT,
            ip TEXT,
            action TEXT NOT NULL,
            target_type TEXT,
            target_id INTEGER,
            details TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_audit_ts ON audit_log(ts);
        CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit_log(actor_id);

        CREATE TABLE IF NOT EXISTS totp_backup_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            code_hash TEXT NOT NULL,
            used_at DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_backup_user ON totp_backup_codes(user_id);

        -- ── Facturatie / abonnementen (Mollie) ──────────────────────────────
        -- Kortingscodes worden in de app zelf beheerd: Mollie kent géén
        -- ingebouwd couponsysteem, dus de kortinglogica leeft hier.
        --   type     'percent' (value = 0-100) of 'fixed' (value = bedrag in EUR)
        --   duration 'once' (alleen eerste incasso) of 'forever' (hele looptijd)
        CREATE TABLE IF NOT EXISTS discount_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL DEFAULT 'percent',
            value REAL NOT NULL DEFAULT 0,
            description TEXT,
            duration TEXT NOT NULL DEFAULT 'once',
            max_redemptions INTEGER,
            times_redeemed INTEGER NOT NULL DEFAULT 0,
            valid_until DATETIME,
            active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Eén abonnement per gebruiker. De Mollie-id's blijven NULL tot de
        -- betaalflow (first payment + subscription) is doorlopen.
        --   status  pending|trialing|active|canceled|suspended
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            mollie_customer_id TEXT,
            mollie_subscription_id TEXT,
            mollie_mandate_id TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            amount REAL NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT 'EUR',
            interval TEXT NOT NULL DEFAULT '1 month',
            discount_code_id INTEGER,
            trial_ends_at DATETIME,
            next_payment_at DATETIME,
            started_at DATETIME,
            canceled_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (discount_code_id) REFERENCES discount_codes(id) ON DELETE SET NULL
        );
        CREATE INDEX IF NOT EXISTS idx_subscriptions_user ON subscriptions(user_id);

        -- Losse afschrijvingen. Wordt gevuld door de Mollie-webhook.
        --   status  open|pending|paid|failed|canceled|expired|refunded
        CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            subscription_id INTEGER,
            mollie_payment_id TEXT UNIQUE,
            amount REAL NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT 'EUR',
            status TEXT NOT NULL DEFAULT 'open',
            description TEXT,
            paid_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
        );
        CREATE INDEX IF NOT EXISTS idx_payments_created ON payments(created_at);
    ");

    // Add columns introduced after initial schema (safe to run on existing DBs)
    try { $db->exec('ALTER TABLE songs ADD COLUMN preview_url TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN spotify_id TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN drum_notation TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN drum_svg TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN drum_svg_updated_at DATETIME'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN lyrics TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN chords TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN pdf_path TEXT'); } catch (PDOException $e) {}
    // E2EE (fase 3): versleutelde blob met alle inhoudsvelden van een nummer.
    // Voor versleutelde bands staan de losse plaintext-kolommen leeg/NULL.
    try { $db->exec('ALTER TABLE songs ADD COLUMN enc_blob TEXT'); } catch (PDOException $e) {}
    // E2EE (fase 4): versleutelde blob met de naam van een setlijst.
    try { $db->exec('ALTER TABLE setlists ADD COLUMN enc_blob TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE bands ADD COLUMN share_token TEXT'); } catch (PDOException $e) {}
    try { $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_bands_share_token ON bands(share_token) WHERE share_token IS NOT NULL'); } catch (PDOException $e) {}
    // E2EE (fase 5): versleutelde projectie voor de publieke deellink. Bevat
    // alleen de publiek getoonde velden, versleuteld met een aparte deelsleutel
    // die uitsluitend in de URL-fragment leeft (zie PRIVACY.md §6).
    try { $db->exec('ALTER TABLE bands ADD COLUMN share_blob TEXT'); } catch (PDOException $e) {}
    // E2EE-sleutelmateriaal per gebruiker (zie PRIVACY.md). Allemaal nullable:
    // een gebruiker krijgt pas sleutels bij de eerste login ná invoering.
    //   kdf_salt             — salt voor PBKDF2 (wachtwoord → KEK)
    //   pubkey               — publieke sleutel (klare tekst, SPKI/base64)
    //   enc_privkey          — privésleutel versleuteld onder de KEK ({v,iv,ct} JSON)
    //   enc_privkey_recovery — privésleutel versleuteld onder de recovery-KEK (fase 2)
    //   recovery_salt        — salt voor de herstelcode (fase 2)
    try { $db->exec('ALTER TABLE users ADD COLUMN kdf_salt TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN pubkey TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN enc_privkey TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN enc_privkey_recovery TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN recovery_salt TEXT'); } catch (PDOException $e) {}
    // SHA-256-hash van de herstelcode — server-side bewijs voor de herstelflow,
    // zodat herstel + login-wachtwoord wijzigen in één veilige stap kan. De code
    // zelf gaat nooit naar de server (zie PRIVACY.md §8).
    try { $db->exec('ALTER TABLE users ADD COLUMN recovery_code_hash TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN totp_secret TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0'); } catch (PDOException $e) {}

    // E2EE-kluis per band (zie PRIVACY.md, fase 2).
    //   is_encrypted — staat de kluis aan voor deze band?
    //   key_version  — volgnummer van de Band Data Key (voor rotatie)
    try { $db->exec('ALTER TABLE bands ADD COLUMN is_encrypted INTEGER NOT NULL DEFAULT 0'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE bands ADD COLUMN key_version INTEGER NOT NULL DEFAULT 1'); } catch (PDOException $e) {}

    // De Band Data Key (BDK), per lid ingepakt met hun publieke sleutel.
    // Eén rij per (band, lid, sleutelversie).
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS band_member_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                band_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                wrapped_bdk TEXT NOT NULL,
                key_version INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(band_id, user_id, key_version)
            );
        ");
    } catch (PDOException $e) {}

    // Migratie bandrollen: zorg dat elke band minstens één leider heeft.
    // Bands zonder leider krijgen het oudste lidmaatschap (laagste band_members.id)
    // als leider — "langst lid wordt leader".
    try {
        $bandsNoLeader = $db->query(
            "SELECT b.id FROM bands b
              WHERE NOT EXISTS (SELECT 1 FROM band_members m
                                 WHERE m.band_id = b.id AND m.role = 'leader')"
        )->fetchAll();
        $oldest = $db->prepare('SELECT id FROM band_members WHERE band_id=? ORDER BY id ASC LIMIT 1');
        $prom   = $db->prepare("UPDATE band_members SET role='leader' WHERE id=?");
        foreach ($bandsNoLeader as $b) {
            $oldest->execute([$b['id']]);
            $mid = $oldest->fetchColumn();
            if ($mid) $prom->execute([$mid]);
        }
    } catch (PDOException $e) { /* tabellen bestaan nog niet tijdens initial install */ }

    // Eenmalige migratie: bestaande plaintext share-/invite-tokens vervangen door SHA-256-hash.
    // Plaintext = 32 hex chars (bin2hex(random_bytes(16))); hash = 64 hex chars.
    try {
        $rows = $db->query("SELECT id, share_token FROM bands WHERE share_token IS NOT NULL AND length(share_token) = 32")->fetchAll();
        $upd  = $db->prepare('UPDATE bands SET share_token = ? WHERE id = ?');
        foreach ($rows as $r) {
            $upd->execute([hash('sha256', $r['share_token']), $r['id']]);
        }
    } catch (PDOException $e) { /* migratie kan nog niet draaien tijdens initial install */ }
    try {
        $rows = $db->query("SELECT id, token FROM band_invites WHERE length(token) = 32")->fetchAll();
        $upd  = $db->prepare('UPDATE band_invites SET token = ? WHERE id = ?');
        foreach ($rows as $r) {
            $upd->execute([hash('sha256', $r['token']), $r['id']]);
        }
    } catch (PDOException $e) {}

    // Seed default admin if no users exist.
    // must_change_password = 1 dwingt direct een wachtwoordwijziging af na de eerste login.
    $count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count == 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username, email, password_hash, role, must_change_password) VALUES (?, ?, ?, 'admin', 1)")
           ->execute(['admin', 'admin@livegig.local', $hash]);
    }
}
