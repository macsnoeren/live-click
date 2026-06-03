<?php
/**
 * Fair-use opslagberekening.
 *
 * Beleid: elke gebruiker mag maximaal STORAGE_QUOTA_BYTES gebruiken. Opslag hangt
 * aan bands, en een band "hoort" bij zijn leider(s) — de betalende partij. Het
 * verbruik van een gebruiker is daarom de som van alle bands die hij/zij LEIDT.
 *
 * Twee opslagbronnen:
 *   1. Database-inhoud (tekstkolommen in songs/setlists; bytes via BLOB-cast)
 *   2. PDF-bladmuziek (bestanden in data/pdfs/, gekoppeld via songs.pdf_path)
 *
 * Spotify-previews tellen NIET mee (externe URL's, geen lokale opslag).
 */

require_once __DIR__ . '/db.php';

if (!defined('STORAGE_QUOTA_BYTES')) {
    // 500 MB per gebruiker. Overschrijfbaar door dit elders eerder te definiëren
    // of via de env-var LIVEGIG_STORAGE_QUOTA_MB.
    $mb = (int)(getenv('LIVEGIG_STORAGE_QUOTA_MB') ?: 500);
    define('STORAGE_QUOTA_BYTES', $mb * 1024 * 1024);
}

/** Map met PDF-bestanden (buiten de webroot). */
function storagePdfDir(): string {
    return APP_ROOT . '/data/pdfs';
}

/** band_id's waarvan de gebruiker leider is. */
function userLedBandIds(int $userId): array {
    $s = getDB()->prepare("SELECT band_id FROM band_members WHERE user_id = ? AND role = 'leader'");
    $s->execute([$userId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Opslag (in bytes) van een set bands, gesplitst in 'db' en 'pdf'.
 * Lege bandenlijst → nul.
 */
function bandsStorageBytes(array $bandIds): array {
    if (!$bandIds) return ['db' => 0, 'pdf' => 0];
    $db    = getDB();
    $place = implode(',', array_fill(0, count($bandIds), '?'));

    // Database-bytes uit songs. BLOB-cast geeft echte bytes i.p.v. tekenaantal.
    $songSql = "SELECT COALESCE(SUM(
            length(CAST(COALESCE(enc_blob,'')      AS BLOB)) +
            length(CAST(COALESCE(lyrics,'')        AS BLOB)) +
            length(CAST(COALESCE(chords,'')        AS BLOB)) +
            length(CAST(COALESCE(drum_notation,'') AS BLOB)) +
            length(CAST(COALESCE(drum_svg,'')      AS BLOB)) +
            length(CAST(COALESCE(description,'')   AS BLOB)) +
            length(CAST(COALESCE(title,'')         AS BLOB)) +
            length(CAST(COALESCE(artist,'')        AS BLOB))
        ), 0) FROM songs WHERE band_id IN ($place)";
    $st = $db->prepare($songSql);
    $st->execute($bandIds);
    $dbBytes = (int)$st->fetchColumn();

    // Database-bytes uit setlists.
    $slSql = "SELECT COALESCE(SUM(
            length(CAST(COALESCE(enc_blob,'') AS BLOB)) +
            length(CAST(COALESCE(name,'')     AS BLOB))
        ), 0) FROM setlists WHERE band_id IN ($place)";
    $st = $db->prepare($slSql);
    $st->execute($bandIds);
    $dbBytes += (int)$st->fetchColumn();

    // PDF-bestanden optellen.
    $st = $db->prepare("SELECT pdf_path FROM songs
                         WHERE band_id IN ($place) AND pdf_path IS NOT NULL AND pdf_path <> ''");
    $st->execute($bandIds);
    $pdfBytes = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $p) {
        $full = storagePdfDir() . '/' . basename((string)$p);
        if (is_file($full)) $pdfBytes += (int)filesize($full);
    }

    return ['db' => $dbBytes, 'pdf' => $pdfBytes];
}

/**
 * Volledig verbruiksoverzicht voor een gebruiker.
 * @return array{db_bytes:int,pdf_bytes:int,used_bytes:int,quota_bytes:int,remaining_bytes:int,percent:int,over:bool}
 */
function userStorageUsage(int $userId): array {
    $parts = bandsStorageBytes(userLedBandIds($userId));
    $used  = $parts['db'] + $parts['pdf'];
    $quota = STORAGE_QUOTA_BYTES;
    return [
        'db_bytes'        => $parts['db'],
        'pdf_bytes'       => $parts['pdf'],
        'used_bytes'      => $used,
        'quota_bytes'     => $quota,
        'remaining_bytes' => max(0, $quota - $used),
        'percent'         => $quota > 0 ? min(100, (int)floor($used / $quota * 100)) : 0,
        'over'            => $used >= $quota,
    ];
}

/** Bytes die de gebruiker nog over heeft (0 als over de limiet). */
function userStorageRemaining(int $userId): int {
    return userStorageUsage($userId)['remaining_bytes'];
}

/**
 * De "eigenaar" van een band voor quotum-doeleinden: de oudste leider
 * (laagste band_members.id met rol leader). Null als de band leiderloos is.
 */
function bandStorageOwnerId(int $bandId): ?int {
    $s = getDB()->prepare("SELECT user_id FROM band_members
                            WHERE band_id = ? AND role = 'leader' ORDER BY id ASC LIMIT 1");
    $s->execute([$bandId]);
    $v = $s->fetchColumn();
    return $v === false ? null : (int)$v;
}
