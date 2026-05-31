<?php
/**
 * E2EE-kluis per band (fase 2 — zie PRIVACY.md).
 *
 * GET ?band_id=N
 *   Status van de kluis voor de huidige gebruiker:
 *   {
 *     ok, is_encrypted, key_version,
 *     my_wrapped_bdk,          // de BDK ingepakt voor jou (of null)
 *     can_manage,              // ben je leider/admin van deze band?
 *     members: [ { user_id, username, role, pubkey, has_key } ]
 *       // alleen bij can_manage — nodig om de BDK voor anderen in te pakken
 *   }
 *
 * POST {action:"enable", band_id, keys:[{user_id, wrapped_bdk}]}
 *   Leider/admin zet de kluis aan: slaat per lid de ingepakte BDK op en zet
 *   is_encrypted=1. Mag alleen als de kluis nog UIT staat.
 *
 * POST {action:"grant", band_id, keys:[{user_id, wrapped_bdk}]}
 *   Leider/admin geeft een lid (dat nog geen kopie had) alsnog de BDK — bv. een
 *   nieuw lid dat inmiddels een publieke sleutel heeft.
 *
 * De server bewaart uitsluitend ingepakte sleutels; hij kan de BDK nooit zelf
 * uitpakken (daarvoor is de privésleutel van een lid nodig, die de server niet kent).
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db     = getDB();
$user   = currentUser();
$userId = (int)$user['id'];
$method = $_SERVER['REQUEST_METHOD'];

/* Korte base64/JSON-validatie van een ingepakte sleutel. */
function vaultValidWrapped($v): bool {
    return is_string($v) && $v !== '' && strlen($v) <= 4000
        && preg_match('#^[A-Za-z0-9+/=]+$#', $v);
}

if ($method === 'GET') {
    $bandId = (int)($_GET['band_id'] ?? 0);
    if (!$bandId || !userCanAccessBand($bandId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang tot deze band.']); exit;
    }

    $b = $db->prepare('SELECT is_encrypted, key_version FROM bands WHERE id = ?');
    $b->execute([$bandId]);
    $band = $b->fetch();
    if (!$band) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Band niet gevonden.']); exit; }

    $ver = (int)$band['key_version'];

    // Mijn eigen ingepakte BDK voor de huidige sleutelversie
    $mk = $db->prepare('SELECT wrapped_bdk FROM band_member_keys WHERE band_id=? AND user_id=? AND key_version=?');
    $mk->execute([$bandId, $userId, $ver]);
    $myWrapped = $mk->fetchColumn() ?: null;

    $canManage = userIsBandLeader($bandId);

    $resp = [
        'ok'             => true,
        'is_encrypted'   => (int)$band['is_encrypted'] === 1,
        'key_version'    => $ver,
        'my_wrapped_bdk' => $myWrapped,
        'can_manage'     => $canManage,
    ];

    // Leider/admin krijgt de ledenlijst met publieke sleutels, zodat de client
    // de BDK voor (nieuwe) leden kan inpakken. Pubkeys zijn niet geheim.
    if ($canManage) {
        $stmt = $db->prepare(
            "SELECT u.id AS user_id, u.username, bm.role, u.pubkey,
                    (SELECT COUNT(*) FROM band_member_keys k
                      WHERE k.band_id = bm.band_id AND k.user_id = u.id AND k.key_version = ?) AS has_key
               FROM band_members bm JOIN users u ON u.id = bm.user_id
              WHERE bm.band_id = ? ORDER BY u.username"
        );
        $stmt->execute([$ver, $bandId]);
        $members = [];
        foreach ($stmt->fetchAll() as $m) {
            $members[] = [
                'user_id'  => (int)$m['user_id'],
                'username' => $m['username'],
                'role'     => $m['role'],
                'pubkey'   => $m['pubkey'] ?: null,
                'has_key'  => (int)$m['has_key'] > 0,
                'is_me'    => (int)$m['user_id'] === $userId,
            ];
        }
        $resp['members'] = $members;
    }

    echo json_encode($resp);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? '';
    $bandId = (int)($data['band_id'] ?? 0);

    if (!$bandId) { echo json_encode(['ok'=>false,'error'=>'band_id ontbreekt']); exit; }
    if (!userIsBandLeader($bandId)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'Alleen de bandleider mag de kluis beheren.']); exit;
    }

    $b = $db->prepare('SELECT is_encrypted, key_version FROM bands WHERE id = ?');
    $b->execute([$bandId]);
    $band = $b->fetch();
    if (!$band) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Band niet gevonden.']); exit; }
    $ver = (int)$band['key_version'];

    // Verzamelde sleutels valideren tegen daadwerkelijke bandleden.
    $keys = $data['keys'] ?? [];
    if (!is_array($keys) || !$keys) { echo json_encode(['ok'=>false,'error'=>'Geen sleutels meegegeven.']); exit; }

    $mem = $db->prepare('SELECT user_id FROM band_members WHERE band_id=?');
    $mem->execute([$bandId]);
    $memberIds = array_map('intval', $mem->fetchAll(PDO::FETCH_COLUMN));
    $memberSet = array_flip($memberIds);

    $clean = [];
    foreach ($keys as $k) {
        $uid = (int)($k['user_id'] ?? 0);
        $wb  = $k['wrapped_bdk'] ?? '';
        if (!isset($memberSet[$uid])) continue;          // alleen echte leden
        if (!vaultValidWrapped($wb)) {
            echo json_encode(['ok'=>false,'error'=>'Ongeldig sleutelformaat.']); exit;
        }
        $clean[$uid] = $wb;
    }
    if (!$clean) { echo json_encode(['ok'=>false,'error'=>'Geen geldige sleutels.']); exit; }

    if ($action === 'enable') {
        if ((int)$band['is_encrypted'] === 1) {
            echo json_encode(['ok'=>false,'error'=>'De kluis staat al aan voor deze band.']); exit;
        }
        // De uitvoerende leider moet zichzelf een sleutel geven (anders sluit hij
        // zichzelf buiten).
        if (!isset($clean[$userId])) {
            echo json_encode(['ok'=>false,'error'=>'Je eigen sleutel ontbreekt.']); exit;
        }
        $ins = $db->prepare('INSERT OR REPLACE INTO band_member_keys (band_id,user_id,wrapped_bdk,key_version) VALUES (?,?,?,?)');
        foreach ($clean as $uid => $wb) $ins->execute([$bandId, $uid, $wb, $ver]);
        $db->prepare('UPDATE bands SET is_encrypted = 1 WHERE id = ?')->execute([$bandId]);
        auditLog('band.vault_enable', 'band', $bandId, ['members' => count($clean)]);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'grant') {
        if ((int)$band['is_encrypted'] !== 1) {
            echo json_encode(['ok'=>false,'error'=>'De kluis staat niet aan.']); exit;
        }
        $ins = $db->prepare('INSERT OR REPLACE INTO band_member_keys (band_id,user_id,wrapped_bdk,key_version) VALUES (?,?,?,?)');
        foreach ($clean as $uid => $wb) $ins->execute([$bandId, $uid, $wb, $ver]);
        auditLog('band.vault_grant', 'band', $bandId, ['members' => count($clean)]);
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Onbekende actie.']); exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
