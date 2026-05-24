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
    ");

    // Add columns introduced after initial schema (safe to run on existing DBs)
    try { $db->exec('ALTER TABLE songs ADD COLUMN preview_url TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN spotify_id TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN drum_notation TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN drum_svg TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE songs ADD COLUMN drum_svg_updated_at DATETIME'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE bands ADD COLUMN share_token TEXT'); } catch (PDOException $e) {}
    try { $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_bands_share_token ON bands(share_token) WHERE share_token IS NOT NULL'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN totp_secret TEXT'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0'); } catch (PDOException $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0'); } catch (PDOException $e) {}

    // Seed default admin if no users exist
    $count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count == 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
           ->execute(['admin', 'admin@livegig.local', $hash]);
    }
}
