<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/mollie.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db      = getDB();
$method  = $_SERVER['REQUEST_METHOD'];
$user    = currentUser();
$isAdmin = $user['role'] === 'admin';

/* ---- helpers ---- */

function getBandsForUser(PDO $db, int $userId, bool $allBands = false): array {
    if ($allBands) {
        // Alleen voor het admin-paneel (verwijderen): álle bands.
        $bands = $db->query('SELECT * FROM bands ORDER BY name')->fetchAll();
    } else {
        // Standaard: alleen bands waar de gebruiker zelf lid van is — ook voor
        // de admin. De admin ziet niet automatisch andermans bands.
        $stmt = $db->prepare(
            'SELECT b.* FROM bands b JOIN band_members bm ON bm.band_id = b.id
             WHERE bm.user_id = ? ORDER BY b.name'
        );
        $stmt->execute([$userId]);
        $bands = $stmt->fetchAll();
    }
    foreach ($bands as &$b) {
        $stmt = $db->prepare(
            'SELECT u.id, u.username, bm.role
             FROM band_members bm JOIN users u ON u.id = bm.user_id
             WHERE bm.band_id = ? ORDER BY bm.role DESC, u.username' // leader first
        );
        $stmt->execute([$b['id']]);
        $b['members'] = $stmt->fetchAll();

        // Rol van de huidige gebruiker in deze band, zodat de frontend de juiste
        // beheerknoppen kan tonen. De admin krijgt GEEN leider-UI: zijn rol is
        // puur zijn eventuele eigen lidmaatschap (meestal geen). Admin ziet de
        // bandenlijst alleen om te kunnen verwijderen (admin-paneel).
        $b['my_role'] = null;
        foreach ($b['members'] as $m) {
            if ((int)$m['id'] === $userId) { $b['my_role'] = $m['role']; break; }
        }
        // Facturatie: is deze band geblokkeerd (geen leider met actief abonnement)?
        $b['blocked'] = bandIsBlocked((int)$b['id']) ? 1 : 0;
    }
    return $bands;
}

function isBandLeader(PDO $db, int $bandId, int $userId): bool {
    $s = $db->prepare("SELECT 1 FROM band_members WHERE band_id=? AND user_id=? AND role='leader'");
    $s->execute([$bandId, $userId]);
    return (bool)$s->fetch();
}

function isBandMember(PDO $db, int $bandId, int $userId): bool {
    $s = $db->prepare('SELECT 1 FROM band_members WHERE band_id=? AND user_id=?');
    $s->execute([$bandId, $userId]);
    return (bool)$s->fetch();
}

/* ---- GET ---- */

if ($method === 'GET') {
    // ?all=1 → álle bands, uitsluitend voor admins (admin-paneel, verwijderen).
    // Anders: alleen de eigen bands van de gebruiker.
    $wantAll = !empty($_GET['all']);
    if ($wantAll && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    echo json_encode(['ok' => true, 'bands' => getBandsForUser($db, $user['id'], $wantAll)]);
    exit;
}

/* ---- POST (create / edit) ---- */

if ($method === 'POST') {
    $data      = json_decode(file_get_contents('php://input'), true);
    $action    = $data['action'] ?? '';

    /* ---- Ledenrol wijzigen (leider/admin) ---- */
    if ($action === 'set_role') {
        $bandId   = (int)($data['band_id'] ?? 0);
        $targetId = (int)($data['user_id'] ?? 0);
        $role     = $data['role'] ?? '';
        if (!$bandId || !$targetId) { echo json_encode(['ok'=>false,'error'=>'Band en gebruiker verplicht']); exit; }
        if (!in_array($role, ['leader','member','viewer'], true)) {
            echo json_encode(['ok'=>false,'error'=>'Ongeldige rol']); exit;
        }
        // Alleen de bandleider mag rollen wijzigen — de admin niet.
        if (!isBandLeader($db, $bandId, $user['id'])) {
            echo json_encode(['ok'=>false,'error'=>'Alleen de bandleider mag rollen wijzigen']); exit;
        }
        if (!isBandMember($db, $bandId, $targetId)) {
            echo json_encode(['ok'=>false,'error'=>'Deze gebruiker is geen lid van de band']); exit;
        }
        // Bescherm tegen het verwijderen van de laatste leider.
        if ($role !== 'leader' && isBandLeader($db, $bandId, $targetId)) {
            $cnt = $db->prepare("SELECT COUNT(*) FROM band_members WHERE band_id=? AND role='leader'");
            $cnt->execute([$bandId]);
            if ((int)$cnt->fetchColumn() <= 1) {
                echo json_encode(['ok'=>false,'error'=>'De band moet minstens één leider houden. Maak eerst iemand anders leider.']); exit;
            }
        }
        $db->prepare('UPDATE band_members SET role=? WHERE band_id=? AND user_id=?')
           ->execute([$role, $bandId, $targetId]);
        auditLog('band.member_role_changed', 'band', $bandId, ['user_id'=>$targetId, 'role'=>$role]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    /* ---- Leiderschap van een leiderloze band overnemen ---- */
    if ($action === 'claim_leadership') {
        $bandId = (int)($data['band_id'] ?? 0);
        if (!$bandId) { echo json_encode(['ok'=>false,'error'=>'Band verplicht']); exit; }
        if (!isBandMember($db, $bandId, $user['id'])) {
            echo json_encode(['ok'=>false,'error'=>'Je bent geen lid van deze band']); exit;
        }
        // Alleen overnemen als de band geen leider (meer) heeft.
        $hasLeader = $db->prepare("SELECT 1 FROM band_members WHERE band_id=? AND role='leader'");
        $hasLeader->execute([$bandId]);
        if ($hasLeader->fetch()) {
            echo json_encode(['ok'=>false,'error'=>'Deze band heeft al een leider']); exit;
        }
        // Leider worden vereist actieve facturatie (geen nieuwe proef bij overname).
        if (billingEnforced() && !userHasActiveBilling($user['id'])) {
            echo json_encode(['ok'=>false,'needs_subscription'=>true,
                'error'=>'Leider worden vereist een actief abonnement.']); exit;
        }
        $db->prepare("UPDATE band_members SET role='leader' WHERE band_id=? AND user_id=?")
           ->execute([$bandId, $user['id']]);
        auditLog('band.leadership_claimed', 'band', $bandId, ['user_id'=>$user['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    $id        = (int)($data['id'] ?? 0);
    $name      = trim($data['name'] ?? '');
    $desc      = trim($data['description'] ?? '');

    if (!$name) { echo json_encode(['ok' => false, 'error' => 'Naam verplicht']); exit; }

    if ($id) {
        // Bewerken — alleen de bandleider. De admin heeft hier geen rechten
        // (bandnaam/leden vallen onder leiderschap, niet onder accountbeheer).
        if (!isBandLeader($db, $id, $user['id'])) {
            echo json_encode(['ok' => false, 'error' => 'Alleen de bandleider mag de band bewerken']); exit;
        }
        $db->prepare('UPDATE bands SET name=?,description=? WHERE id=?')->execute([$name, $desc, $id]);
    } else {
        // Aanmaken — de maker wordt leider, en leider zijn is betaald. Vereist
        // dus een actief abonnement of lopende proefperiode. Lid/kijker zijn
        // blijft gratis (dat loopt via join.php, niet hierlangs).
        if (billingEnforced() && !userHasActiveBilling($user['id'])) {
            echo json_encode([
                'ok' => false,
                'needs_subscription' => true,
                'trial_used' => userTrialUsed($user['id']),
                'error' => 'Een band aanmaken vereist een abonnement. Start je gratis proefmaand om door te gaan.',
            ]); exit;
        }
        $db->prepare('INSERT INTO bands (name,description) VALUES (?,?)')->execute([$name, $desc]);
        $id = $db->lastInsertId();
        $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id,role) VALUES (?,?,?)')
           ->execute([$user['id'], $id, 'leader']);
    }

    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

/* ---- DELETE (remove member or delete band) ---- */

if ($method === 'DELETE') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    $userId = (int)($data['user_id'] ?? 0);
    $id     = (int)($data['id'] ?? 0);

    if ($bandId && $userId) {
        $isSelf = ($userId === (int)$user['id']);

        // Iemand anders verwijderen: alleen de bandleider (admin heeft hier geen rol).
        if (!$isSelf && !isBandLeader($db, $bandId, $user['id'])) {
            echo json_encode(['ok' => false, 'error' => 'Alleen de bandleider mag leden verwijderen']); exit;
        }

        // Jezelf verwijderen (verlaten): altijd toegestaan, mits je lid bent.
        if (!$isSelf && !isBandMember($db, $bandId, $user['id'])) {
            echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
        }

        $wasLeader = isBandLeader($db, $bandId, $userId);

        $db->prepare('DELETE FROM band_members WHERE band_id=? AND user_id=?')->execute([$bandId, $userId]);

        if ($wasLeader) {
            // GEEN stille auto-promotie meer: leider zijn is betaald, dus we
            // duwen niemand ongevraagd in een betaalplichtige rol. Zijn er nog
            // andere leiders, dan loopt de band gewoon door. Is er geen leider
            // meer, dan komt de band in een "leiderloos" → geblokkeerd-staat
            // (bandIsBlocked) totdat een lid het leiderschap claimt (en betaalt).
            // Alleen als er helemaal niemand meer over is, ruimen we de band op.
            $remaining = $db->prepare('SELECT COUNT(*) FROM band_members WHERE band_id=?');
            $remaining->execute([$bandId]);
            if ((int)$remaining->fetchColumn() === 0) {
                $db->prepare('DELETE FROM bands WHERE id=?')->execute([$bandId]);
            }
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($id) {
        requireAdmin();
        $db->prepare('DELETE FROM bands WHERE id=?')->execute([$id]);
        auditLog('band.delete', 'band', $id);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Geen id']); exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
