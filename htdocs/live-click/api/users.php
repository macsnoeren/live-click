<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
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
    $bandIds            = $data['band_ids'] ?? [];
    $mustChangePassword = isset($data['must_change_password']) ? (int)(bool)$data['must_change_password'] : 0;

    if (!$username || !$email) { echo json_encode(['ok'=>false,'error'=>'Gebruikersnaam en e-mail verplicht']); exit; }

    if ($id) {
        if ($password) {
            $db->prepare('UPDATE users SET username=?,email=?,password_hash=?,role=?,must_change_password=? WHERE id=?')
               ->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$role,$mustChangePassword,$id]);
        } else {
            $db->prepare('UPDATE users SET username=?,email=?,role=?,must_change_password=? WHERE id=?')
               ->execute([$username,$email,$role,$mustChangePassword,$id]);
        }
    } else {
        if (!$password) { echo json_encode(['ok'=>false,'error'=>'Wachtwoord verplicht bij nieuw account']); exit; }
        try {
            $db->prepare('INSERT INTO users (username,email,password_hash,role,must_change_password) VALUES (?,?,?,?,?)')
               ->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$role,$mustChangePassword]);
            $id = $db->lastInsertId();
        } catch (PDOException $e) {
            echo json_encode(['ok'=>false,'error'=>'Gebruikersnaam of e-mail al in gebruik']); exit;
        }
    }

    $db->prepare('DELETE FROM band_members WHERE user_id=?')->execute([$id]);
    $ins = $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id) VALUES (?,?)');
    foreach ($bandIds as $bid) { $ins->execute([$id,(int)$bid]); }

    echo json_encode(['ok'=>true,'id'=>$id]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
