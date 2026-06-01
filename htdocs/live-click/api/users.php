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

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
