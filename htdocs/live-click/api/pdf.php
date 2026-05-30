<?php
/**
 * Bladmuziek-PDF per nummer: uploaden (POST), bekijken (GET) en verwijderen (DELETE).
 *
 * Opslag: APP_ROOT/data/pdfs/  — bewust BUITEN de webroot, dus niet direct
 * opvraagbaar. Serveren gebeurt via dit endpoint met een bandtoegangscheck.
 *
 * Beveiliging:
 *   - alleen ingelogde gebruikers; per nummer wordt bandtoegang gecontroleerd
 *   - upload: max 10 MB, alleen echte PDF's (%PDF-magic + finfo), random bestandsnaam
 *   - serve : Content-Type application/pdf, inline, nosniff. X-Frame-Options en
 *             CSP frame-ancestors worden hier naar 'self' gezet zodat het bestand
 *             in de dashboard-iframe (zelfde origin) getoond mag worden.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire(); // no-op voor GET; verplicht voor POST/DELETE

define('LG_PDF_DIR', APP_ROOT . '/data/pdfs');
define('LG_PDF_MAX', 10 * 1024 * 1024); // 10 MB

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

/* ── Nummer + bandtoegang bepalen ───────────────────────────── */
function pdfRequireSong(PDO $db): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $id = (int)($_GET['song_id'] ?? 0);
    } else {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['song_id'] ?? $_POST['song_id'] ?? 0);
    }
    if (!$id) { pdfJson(['ok' => false, 'error' => 'Geen nummer-id'], 400); }

    $stmt = $db->prepare('SELECT id, title, band_id, pdf_path FROM songs WHERE id = ?');
    $stmt->execute([$id]);
    $song = $stmt->fetch();
    if (!$song) { pdfJson(['ok' => false, 'error' => 'Nummer niet gevonden.'], 404); }

    $bandId = $song['band_id'] !== null ? (int)$song['band_id'] : 0;
    if (!userCanAccessBand($bandId)) {
        pdfJson(['ok' => false, 'error' => 'Geen toegang tot dit nummer.'], 403);
    }
    return $song;
}

function pdfJson(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/* ── GET: PDF tonen ─────────────────────────────────────────── */
if ($method === 'GET') {
    $song = pdfRequireSong($db);
    if (empty($song['pdf_path'])) { pdfJson(['ok' => false, 'error' => 'Geen PDF.'], 404); }

    $path = LG_PDF_DIR . '/' . basename($song['pdf_path']);
    $real = realpath($path);
    if ($real === false || strpos($real, realpath(LG_PDF_DIR)) !== 0 || !is_file($real)) {
        pdfJson(['ok' => false, 'error' => 'PDF-bestand niet gevonden.'], 404);
    }

    // Framing-headers overschrijven zodat het bestand in de eigen iframe mag.
    // (bootstrap zette X-Frame-Options: DENY en frame-ancestors 'none')
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; object-src 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($real));

    $name = preg_replace('/[^A-Za-z0-9 _.-]/', '', $song['title'] ?: 'bladmuziek');
    header('Content-Disposition: inline; filename="' . $name . '.pdf"');

    readfile($real);
    exit;
}

/* ── POST: PDF uploaden ─────────────────────────────────────── */
if ($method === 'POST') {
    $song = pdfRequireSong($db);

    if (empty($_FILES['pdf']) || !is_array($_FILES['pdf'])) {
        pdfJson(['ok' => false, 'error' => 'Geen bestand ontvangen.'], 400);
    }
    $f = $_FILES['pdf'];

    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = ($f['error'] == UPLOAD_ERR_INI_SIZE || $f['error'] == UPLOAD_ERR_FORM_SIZE)
             ? 'Bestand te groot.' : 'Upload mislukt.';
        pdfJson(['ok' => false, 'error' => $msg], 400);
    }
    if ($f['size'] <= 0 || $f['size'] > LG_PDF_MAX) {
        pdfJson(['ok' => false, 'error' => 'PDF mag maximaal 10 MB zijn.'], 413);
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        pdfJson(['ok' => false, 'error' => 'Ongeldige upload.'], 400);
    }

    // Controleer dat het echt een PDF is (magic bytes + finfo)
    $fh    = fopen($f['tmp_name'], 'rb');
    $magic = $fh ? fread($fh, 5) : '';
    if ($fh) fclose($fh);
    $isPdf = ($magic === '%PDF-');
    if ($isPdf && function_exists('finfo_open')) {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $fi ? finfo_file($fi, $f['tmp_name']) : '';
        if ($fi) finfo_close($fi);
        if ($mime && $mime !== 'application/pdf') $isPdf = false;
    }
    if (!$isPdf) {
        pdfJson(['ok' => false, 'error' => 'Alleen PDF-bestanden zijn toegestaan.'], 415);
    }

    if (!is_dir(LG_PDF_DIR) && !@mkdir(LG_PDF_DIR, 0775, true) && !is_dir(LG_PDF_DIR)) {
        pdfJson(['ok' => false, 'error' => 'Kan opslagmap niet aanmaken.'], 500);
    }

    $fname = 'song_' . (int)$song['id'] . '_' . bin2hex(random_bytes(8)) . '.pdf';
    $dest  = LG_PDF_DIR . '/' . $fname;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        pdfJson(['ok' => false, 'error' => 'Opslaan van bestand mislukt.'], 500);
    }

    // Oude PDF opruimen
    if (!empty($song['pdf_path'])) {
        $old = LG_PDF_DIR . '/' . basename($song['pdf_path']);
        if (is_file($old)) @unlink($old);
    }

    try {
        $db->prepare('UPDATE songs SET pdf_path = ? WHERE id = ?')->execute([$fname, (int)$song['id']]);
    } catch (PDOException $e) {
        @unlink($dest);
        error_log('pdf.php PDO: ' . $e->getMessage());
        pdfJson(['ok' => false, 'error' => 'Database fout bij opslaan.'], 500);
    }

    pdfJson(['ok' => true, 'pdf_path' => $fname]);
}

/* ── DELETE: PDF verwijderen ────────────────────────────────── */
if ($method === 'DELETE') {
    $song = pdfRequireSong($db);
    if (!empty($song['pdf_path'])) {
        $old = LG_PDF_DIR . '/' . basename($song['pdf_path']);
        if (is_file($old)) @unlink($old);
    }
    $db->prepare('UPDATE songs SET pdf_path = NULL WHERE id = ?')->execute([(int)$song['id']]);
    pdfJson(['ok' => true]);
}

pdfJson(['ok' => false, 'error' => 'Method not allowed'], 405);
