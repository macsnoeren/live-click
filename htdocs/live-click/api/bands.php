<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db      = getDB();
$method  = $_SERVER['REQUEST_METHOD'];
$user    = currentUser();
$isAdmin = $user['role'] === 'admin';

/* ---- helpers ---- */

function getBandsForUser(PDO $db, int $userId, bool $isAdmin): array {
    if ($isAdmin) {
        $bands = $db->query('SELECT * FROM bands ORDER BY name')->fetchAll();
    } else {
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
        // beheerknoppen kan tonen ('leader' geeft ledenbeheer; admin = altijd).
        $b['my_role'] = $isAdmin ? 'admin' : null;
        if (!$isAdmin) {
            foreach ($b['members'] as $m) {
                if ((int)$m['id'] === $userId) { $b['my_role'] = $m['role']; break; }
            }
        }
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
    echo json_encode(['ok' => true, 'bands' => getBandsForUser($db, $user['id'], $isAdmin)]);
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
        if (!$isAdmin && !isBandLeader($db, $bandId, $user['id'])) {
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

    $id        = (int)($data['id'] ?? 0);
    $name      = trim($data['name'] ?? '');
    $desc      = trim($data['description'] ?? '');
    $memberIds = $data['member_ids'] ?? null;

    if (!$name) { echo json_encode(['ok' => false, 'error' => 'Naam verplicht']); exit; }

    if ($id) {
        // Edit — only leader of this band or site admin
        if (!$isAdmin && !isBandLeader($db, $id, $user['id'])) {
            echo json_encode(['ok' => false, 'error' => 'Alleen de bandleider mag de band bewerken']); exit;
        }
        $db->prepare('UPDATE bands SET name=?,description=? WHERE id=?')->execute([$name, $desc, $id]);

        // Admin member-list replacement: preserve existing roles
        if ($isAdmin && $memberIds !== null) {
            $cur = $db->prepare('SELECT user_id, role FROM band_members WHERE band_id=?');
            $cur->execute([$id]);
            $roleMap = [];
            foreach ($cur->fetchAll() as $m) $roleMap[(int)$m['user_id']] = $m['role'];

            $db->prepare('DELETE FROM band_members WHERE band_id=?')->execute([$id]);
            $ins = $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id,role) VALUES (?,?,?)');
            foreach ($memberIds as $uid) {
                $uid  = (int)$uid;
                $role = $roleMap[$uid] ?? 'member';
                $ins->execute([$uid, $id, $role]);
            }
        }
    } else {
        // Create — any logged-in user; creator becomes leader
        $db->prepare('INSERT INTO bands (name,description) VALUES (?,?)')->execute([$name, $desc]);
        $id = $db->lastInsertId();

        if ($isAdmin && $memberIds !== null) {
            $ins = $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id,role) VALUES (?,?,?)');
            foreach ($memberIds as $uid) {
                $role = ((int)$uid === (int)$user['id']) ? 'leader' : 'member';
                $ins->execute([(int)$uid, $id, $role]);
            }
            // Ensure the admin creating the band is always added as leader
            if (!in_array((int)$user['id'], array_map('intval', $memberIds))) {
                $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id,role) VALUES (?,?,?)')
                   ->execute([$user['id'], $id, 'leader']);
            }
        } else {
            $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id,role) VALUES (?,?,?)')
               ->execute([$user['id'], $id, 'leader']);
        }
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

        // Removing someone else: only leader or admin
        if (!$isSelf && !$isAdmin && !isBandLeader($db, $bandId, $user['id'])) {
            echo json_encode(['ok' => false, 'error' => 'Alleen de bandleider mag leden verwijderen']); exit;
        }

        // Removing yourself (leave): always allowed — even for leaders
        if (!$isSelf && !$isAdmin && !isBandMember($db, $bandId, $user['id'])) {
            echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
        }

        $wasLeader = isBandLeader($db, $bandId, $userId);

        $db->prepare('DELETE FROM band_members WHERE band_id=? AND user_id=?')->execute([$bandId, $userId]);

        if ($wasLeader) {
            // Promote the next member alphabetically, or delete the band if empty
            $next = $db->prepare(
                'SELECT u.id FROM band_members bm JOIN users u ON u.id = bm.user_id
                 WHERE bm.band_id = ? ORDER BY u.username LIMIT 1'
            );
            $next->execute([$bandId]);
            $newLeader = $next->fetchColumn();

            if ($newLeader) {
                $db->prepare("UPDATE band_members SET role='leader' WHERE band_id=? AND user_id=?")
                   ->execute([$bandId, $newLeader]);
            } else {
                // No members left — remove the band entirely
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
