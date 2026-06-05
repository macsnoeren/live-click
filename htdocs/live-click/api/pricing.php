<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/pricing.php';
requireAdmin();
csrfRequire();
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $today   = date('Y-m-d');
    $current = currentPricing();
    $rows    = allPricing();
    foreach ($rows as &$r) {
        $r['total']        = pricingTotal($r);
        $r['net']          = pricingNet($r);
        $r['vat_amount']   = pricingVatAmount($r);
        $r['is_current']   = ((int)$r['id'] === (int)($current['id'] ?? 0));
        $r['is_scheduled'] = $r['effective_from'] > $today;
    }
    echo json_encode(['ok' => true, 'pricing' => $rows, 'today' => $today]);
    exit;
}

if ($method === 'POST') {
    $data     = json_decode(file_get_contents('php://input'), true) ?: [];
    $interval = trim((string)($data['interval'] ?? '12 months')) ?: '12 months';
    $base     = max(0, (float)($data['base_amount'] ?? 0));
    $fee      = max(0, (float)($data['mollie_fee']  ?? 0));
    $vat      = max(0, (float)($data['vat_percent'] ?? 0));
    $eff      = trim((string)($data['effective_from'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eff)) {
        echo json_encode(['ok' => false, 'error' => 'Ongeldige ingangsdatum.']); exit;
    }
    if ($base <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Basisbedrag moet groter dan 0 zijn.']); exit;
    }

    $db->prepare(
        "INSERT INTO pricing (interval, base_amount, mollie_fee, vat_percent, effective_from)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$interval, $base, $fee, $vat, $eff]);
    auditLog('pricing.create', 'pricing', (int)$db->lastInsertId(),
        ['effective_from' => $eff, 'base' => $base, 'fee' => $fee, 'vat' => $vat]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Geen id']); exit; }

    $row = $db->prepare('SELECT effective_from FROM pricing WHERE id = ?');
    $row->execute([$id]);
    $eff = $row->fetchColumn();
    if ($eff === false) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Niet gevonden']); exit; }

    // Alleen geplande (toekomstige) tarieven mogen weg; huidig/historie blijft staan.
    if ($eff <= date('Y-m-d')) {
        echo json_encode(['ok' => false, 'error' => 'Alleen geplande (toekomstige) tarieven kunnen verwijderd worden.']); exit;
    }
    $db->prepare('DELETE FROM pricing WHERE id = ?')->execute([$id]);
    auditLog('pricing.delete', 'pricing', $id);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
