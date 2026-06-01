<?php
/**
 * E2EE-sleutelbeheer per gebruiker (fase 1 — zie PRIVACY.md).
 *
 * GET            → status + sleutelmateriaal van de huidige gebruiker:
 *                  { ok, has_keys, kdf_salt, pubkey, enc_privkey }
 *                  (enc_privkey + salt zijn nodig om de privésleutel in de
 *                   browser te ontsleutelen; ze zijn nutteloos zonder het
 *                   wachtwoord van de gebruiker)
 *
 * POST {action:"init", kdf_salt, pubkey, enc_privkey}
 *                → eenmalig sleutelpaar registreren. Alleen toegestaan als de
 *                  gebruiker nog géén publieke sleutel heeft (voorkomt dat een
 *                  gekaapte sessie bestaande sleutels overschrijft → dataverlies).
 *
 * De server slaat alleen op; hij ziet nooit de privésleutel in klare tekst,
 * noch het wachtwoord of de afgeleide KEK.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db     = getDB();
$userId = (int)currentUser()['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $row = $db->prepare('SELECT kdf_salt, pubkey, enc_privkey, recovery_salt, enc_privkey_recovery FROM users WHERE id = ?');
    $row->execute([$userId]);
    $r = $row->fetch() ?: [];
    echo json_encode([
        'ok'           => true,
        'has_keys'     => !empty($r['pubkey']) && !empty($r['enc_privkey']),
        'has_recovery' => !empty($r['enc_privkey_recovery']),
        'kdf_salt'     => $r['kdf_salt']    ?? null,
        'pubkey'       => $r['pubkey']      ?? null,
        'enc_privkey'  => $r['enc_privkey'] ?? null,
    ]);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? '';

    if ($action === 'init') {
        $salt    = trim($data['kdf_salt']    ?? '');
        $pubkey  = trim($data['pubkey']      ?? '');
        $encPriv = trim($data['enc_privkey'] ?? '');
        if ($salt === '' || $pubkey === '' || $encPriv === '') {
            echo json_encode(['ok' => false, 'error' => 'Onvolledig sleutelmateriaal.']); exit;
        }
        // Grofweg base64 valideren + lengte begrenzen (anti-misbruik).
        foreach (['salt' => $salt, 'pubkey' => $pubkey, 'encPriv' => $encPriv] as $v) {
            if (strlen($v) > 8000 || !preg_match('#^[A-Za-z0-9+/=:_."\{\}\,\-]+$#', $v)) {
                echo json_encode(['ok' => false, 'error' => 'Ongeldig sleutelformaat.']); exit;
            }
        }

        // Alleen toestaan als er nog geen sleutel is — voorkomt overschrijven.
        $cur = $db->prepare('SELECT pubkey FROM users WHERE id = ?');
        $cur->execute([$userId]);
        if (!empty($cur->fetchColumn())) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Er bestaat al een sleutelpaar.']); exit;
        }

        $db->prepare('UPDATE users SET kdf_salt = ?, pubkey = ?, enc_privkey = ? WHERE id = ?')
           ->execute([$salt, $pubkey, $encPriv, $userId]);
        auditLog('user.keys_init', 'user', $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_recovery') {
        // Levert het herstelmateriaal: salt + met de herstelcode versleutelde
        // privésleutel. Nutteloos zonder de herstelcode (die de server niet kent).
        // Gebruikt door de herstelflow nadat de gebruiker is ingelogd maar de
        // kluis niet met het wachtwoord te openen was (bv. na admin-reset).
        $row = $db->prepare('SELECT recovery_salt, enc_privkey_recovery FROM users WHERE id = ?');
        $row->execute([$userId]);
        $r = $row->fetch() ?: [];
        if (empty($r['enc_privkey_recovery'])) {
            echo json_encode(['ok' => false, 'error' => 'Geen herstelcode ingesteld voor dit account.']); exit;
        }
        echo json_encode([
            'ok'                   => true,
            'recovery_salt'        => $r['recovery_salt'],
            'enc_privkey_recovery' => $r['enc_privkey_recovery'],
        ]);
        exit;
    }

    if ($action === 'rewrap') {
        // Vervangt de met het wachtwoord versleutelde privésleutel + salt. Wordt
        // gebruikt na een succesvolle herstelactie (client heeft de privésleutel
        // via de herstelcode ontsleuteld en verpakt hem onder het nieuwe wachtwoord).
        $salt    = trim($data['kdf_salt']    ?? '');
        $encPriv = trim($data['enc_privkey'] ?? '');
        if ($salt === '' || $encPriv === '') {
            echo json_encode(['ok' => false, 'error' => 'Onvolledig sleutelmateriaal.']); exit;
        }
        if (strlen($salt) > 8000 || strlen($encPriv) > 8000) {
            echo json_encode(['ok' => false, 'error' => 'Ongeldig formaat.']); exit;
        }
        $cur = $db->prepare('SELECT pubkey FROM users WHERE id = ?');
        $cur->execute([$userId]);
        if (empty($cur->fetchColumn())) {
            echo json_encode(['ok' => false, 'error' => 'Er is nog geen sleutelpaar.']); exit;
        }
        $db->prepare('UPDATE users SET kdf_salt = ?, enc_privkey = ? WHERE id = ?')
           ->execute([$salt, $encPriv, $userId]);
        auditLog('user.keys_rewrap', 'user', $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'set_recovery') {
        // Tweede, met een herstelcode versleutelde kopie van de privésleutel
        // (zie PRIVACY.md §8). Vereist dat er al een sleutelpaar bestaat.
        $rsalt = trim($data['recovery_salt'] ?? '');
        $renc  = trim($data['enc_privkey_recovery'] ?? '');
        if ($rsalt === '' || $renc === '') {
            echo json_encode(['ok' => false, 'error' => 'Onvolledig herstelmateriaal.']); exit;
        }
        if (strlen($rsalt) > 8000 || strlen($renc) > 8000) {
            echo json_encode(['ok' => false, 'error' => 'Ongeldig formaat.']); exit;
        }
        $cur = $db->prepare('SELECT pubkey FROM users WHERE id = ?');
        $cur->execute([$userId]);
        if (empty($cur->fetchColumn())) {
            echo json_encode(['ok' => false, 'error' => 'Er is nog geen sleutelpaar.']); exit;
        }
        $db->prepare('UPDATE users SET recovery_salt = ?, enc_privkey_recovery = ? WHERE id = ?')
           ->execute([$rsalt, $renc, $userId]);
        auditLog('user.recovery_set', 'user', $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Onbekende actie.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
