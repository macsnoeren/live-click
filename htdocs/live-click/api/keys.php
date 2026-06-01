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

    if ($action === 'set_recovery') {
        // Tweede, met een herstelcode versleutelde kopie van de privésleutel
        // (zie PRIVACY.md §8). Vereist dat er al een sleutelpaar bestaat.
        // code_hash = SHA-256 van de genormaliseerde code; dient als server-side
        // bewijs in de herstelflow. De code zelf komt nooit binnen.
        $rsalt    = trim($data['recovery_salt'] ?? '');
        $renc     = trim($data['enc_privkey_recovery'] ?? '');
        $codeHash = trim($data['code_hash'] ?? '');
        if ($rsalt === '' || $renc === '' || $codeHash === '') {
            echo json_encode(['ok' => false, 'error' => 'Onvolledig herstelmateriaal.']); exit;
        }
        if (strlen($rsalt) > 8000 || strlen($renc) > 8000) {
            echo json_encode(['ok' => false, 'error' => 'Ongeldig formaat.']); exit;
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $codeHash)) {
            echo json_encode(['ok' => false, 'error' => 'Ongeldige code-hash.']); exit;
        }
        $cur = $db->prepare('SELECT pubkey FROM users WHERE id = ?');
        $cur->execute([$userId]);
        if (empty($cur->fetchColumn())) {
            echo json_encode(['ok' => false, 'error' => 'Er is nog geen sleutelpaar.']); exit;
        }
        $db->prepare('UPDATE users SET recovery_salt = ?, enc_privkey_recovery = ?, recovery_code_hash = ? WHERE id = ?')
           ->execute([$rsalt, $renc, $codeHash, $userId]);
        auditLog('user.recovery_set', 'user', $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'recover_complete') {
        // Eénstaps-herstel: client heeft met de herstelcode de privésleutel
        // ontsleuteld en opnieuw verpakt onder het NIEUWE wachtwoord. De code_hash
        // bewijst dat de gebruiker de echte herstelcode heeft → we mogen tegelijk
        // het login-wachtwoord zetten (zonder het oude wachtwoord te kennen).
        $salt     = trim($data['kdf_salt']    ?? '');
        $encPriv  = trim($data['enc_privkey'] ?? '');
        $codeHash = trim($data['code_hash']   ?? '');
        $newPw    = (string)($data['new_password'] ?? '');

        if ($salt === '' || $encPriv === '' || $codeHash === '' || $newPw === '') {
            echo json_encode(['ok' => false, 'error' => 'Onvolledige herstelgegevens.']); exit;
        }
        if (strlen($salt) > 8000 || strlen($encPriv) > 8000) {
            echo json_encode(['ok' => false, 'error' => 'Ongeldig formaat.']); exit;
        }
        if (($pwErr = validatePasswordStrength($newPw)) !== null) {
            echo json_encode(['ok' => false, 'error' => $pwErr]); exit;
        }

        // Rate-limit: voorkom brute-force op de herstelcode-hash.
        rateLimitCheck('recover:user:' . $userId, 5, 600);
        rateLimitCheck('recover:ip:'   . clientIp(), 20, 600);

        $row = $db->prepare('SELECT recovery_code_hash FROM users WHERE id = ?');
        $row->execute([$userId]);
        $stored = $row->fetchColumn();
        if (!$stored) {
            echo json_encode(['ok' => false, 'error' => 'Geen herstelcode ingesteld voor dit account.']); exit;
        }
        if (!hash_equals((string)$stored, $codeHash)) {
            rateLimitRecord('recover:user:' . $userId);
            rateLimitRecord('recover:ip:'   . clientIp());
            echo json_encode(['ok' => false, 'error' => 'Onjuiste herstelcode.']); exit;
        }

        // Kluis-sleutel (onder nieuw wachtwoord) én login-wachtwoord in één keer.
        $db->prepare('UPDATE users SET kdf_salt = ?, enc_privkey = ?, password_hash = ?, must_change_password = 0 WHERE id = ?')
           ->execute([$salt, $encPriv, password_hash($newPw, PASSWORD_DEFAULT), $userId]);
        unset($_SESSION['must_change_password']);
        auditLog('user.recover_complete', 'user', $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Onbekende actie.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
