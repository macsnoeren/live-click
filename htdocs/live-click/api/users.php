<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireAdmin();
csrfRequire();
header('Content-Type: application/json');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function getUsersWithBands(PDO $db): array {
    $users = $db->query('SELECT id, username, email, role, must_change_password, created_at FROM users ORDER BY username')->fetchAll();
    foreach ($users as &$u) {
        $stmt = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id=bm.band_id WHERE bm.user_id=?');
        $stmt->execute([$u['id']]);
        $u['bands'] = $stmt->fetchAll();
    }
    return $users;
}

if ($method === 'GET') {
    echo json_encode(['ok'=>true,'users'=>getUsersWithBands($db)]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id                 = (int)($data['id'] ?? 0);
    $username           = trim($data['username'] ?? '');
    $email              = trim($data['email'] ?? '');
    $password           = $data['password'] ?? '';
    $role               = in_array($data['role']??'', ['admin','user']) ? $data['role'] : 'user';
    $mustChangePassword = isset($data['must_change_password']) ? (int)(bool)$data['must_change_password'] : 0;

    if (!$username || !$email) { echo json_encode(['ok'=>false,'error'=>'Gebruikersnaam en e-mail verplicht']); exit; }

    if ($password && ($pwErr = validatePasswordStrength($password)) !== null) {
        echo json_encode(['ok'=>false,'error'=>$pwErr]); exit;
    }

    if ($id) {
        if ($password) {
            $db->prepare('UPDATE users SET username=?,email=?,password_hash=?,role=?,must_change_password=? WHERE id=?')
               ->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$role,$mustChangePassword,$id]);
            auditLog('user.password_reset_by_admin', 'user', (int)$id, ['must_change_password' => (bool)$mustChangePassword]);
        } else {
            $db->prepare('UPDATE users SET username=?,email=?,role=?,must_change_password=? WHERE id=?')
               ->execute([$username,$email,$role,$mustChangePassword,$id]);
        }
        auditLog('user.update', 'user', (int)$id, ['username' => $username, 'role' => $role]);
    } else {
        if (!$password) { echo json_encode(['ok'=>false,'error'=>'Wachtwoord verplicht bij nieuw account']); exit; }
        try {
            $db->prepare('INSERT INTO users (username,email,password_hash,role,must_change_password) VALUES (?,?,?,?,?)')
               ->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$role,$mustChangePassword]);
            $id = $db->lastInsertId();
            auditLog('user.create', 'user', (int)$id, ['username' => $username, 'role' => $role]);
        } catch (PDOException $e) {
            echo json_encode(['ok'=>false,'error'=>'Gebruikersnaam of e-mail al in gebruik']); exit;
        }
    }

    // Bandlidmaatschap wordt NIET meer door de admin beheerd — leiders regelen
    // dat zelf via uitnodigingen en rollen. De admin beheert alleen het account.
    echo json_encode(['ok'=>true,'id'=>$id]);
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Geen id']); exit; }

    // Je kunt jezelf niet verwijderen (voorkomt dat je jezelf buitensluit).
    if ($id === (int)currentUser()['id']) {
        echo json_encode(['ok'=>false,'error'=>'Je kunt je eigen account niet verwijderen.']); exit;
    }

    $row = $db->prepare('SELECT username, role FROM users WHERE id=?');
    $row->execute([$id]);
    $target = $row->fetch();
    if (!$target) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Gebruiker niet gevonden.']); exit; }

    // Bescherm de laatste admin: er moet er altijd minstens één overblijven.
    if ($target['role'] === 'admin') {
        $cnt = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($cnt <= 1) {
            echo json_encode(['ok'=>false,'error'=>'Dit is de laatste admin; die kan niet verwijderd worden.']); exit;
        }
    }

    // band_members verdwijnt via ON DELETE CASCADE; bands waar deze gebruiker de
    // enige leider was, blijven bestaan (een andere leider/lid kan ze beheren of
    // de admin kan ze verwijderen). Songs/setlists blijven aan de band gekoppeld.
    $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    auditLog('user.delete', 'user', $id, ['username' => $target['username']]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
