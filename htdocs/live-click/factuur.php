<?php
/**
 * Downloadbare factuur voor één betaling van de ingelogde gebruiker.
 *
 * Bewust een KALE, print-geoptimaliseerde pagina (geen nav/kluis-prompt). De
 * gebruiker kan 'm via de browser opslaan als PDF (knop "Download / print" of
 * automatisch printvenster met ?print=1). Geen externe PDF-library nodig.
 *
 * Beveiliging: alleen de eigen betaling (user_id moet matchen) én alleen als die
 * daadwerkelijk betaald is.
 */
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/config.php';
requireLogin();

$user   = currentUser();
$userId = (int)$user['id'];
$payId  = (int)($_GET['payment_id'] ?? 0);

$db = getDB();
$stmt = $db->prepare(
    'SELECT id, mollie_payment_id, amount, currency, status, description, paid_at, created_at
       FROM payments WHERE id = ? AND user_id = ?'
);
$stmt->execute([$payId, $userId]);
$pay = $stmt->fetch();

// E-mailadres van de klant erbij.
$uStmt = $db->prepare('SELECT username, email FROM users WHERE id = ?');
$uStmt->execute([$userId]);
$uInfo = $uStmt->fetch() ?: ['username' => $user['username'], 'email' => ''];

function fmtEur($v): string {
    return '€ ' . number_format((float)$v, 2, ',', '.');
}

$valid = $pay && $pay['status'] === 'paid';

// Bedragen: het betaalde bedrag is inclusief btw. Splits uit als er btw geldt.
$gross = $valid ? (float)$pay['amount'] : 0.0;
$vatPct = (float)INVOICE_VAT_PERCENT;
$net    = $vatPct > 0 ? $gross / (1 + $vatPct / 100) : $gross;
$vatAmt = $gross - $net;

$paidDate = $valid ? ($pay['paid_at'] ?: $pay['created_at']) : '';
$invNo    = $valid ? 'LG-' . date('Y', strtotime($paidDate)) . '-' . sprintf('%05d', (int)$pay['id']) : '';
$cur      = $valid ? ($pay['currency'] ?: 'EUR') : 'EUR';
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $valid ? 'Factuur ' . htmlspecialchars($invNo) : 'Factuur' ?> — LiveGig</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1c1c1c;
               background: #f3f4f6; margin: 0; padding: 24px; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; padding: 48px;
                 border: 1px solid #e5e7eb; border-radius: 8px; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
        .brand { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; }
        .muted { color: #6b7280; }
        .small { font-size: 0.85rem; }
        h1 { font-size: 1.25rem; margin: 0 0 4px; }
        .meta { text-align: right; }
        .parties { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 36px; }
        .parties h2 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;
                      color: #6b7280; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { padding: 10px 8px; text-align: left; }
        thead th { border-bottom: 2px solid #1c1c1c; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
        tbody td { border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin-left: auto; }
        .totals td { padding: 6px 8px; }
        .totals .grand td { border-top: 2px solid #1c1c1c; font-weight: 700; font-size: 1.05rem; }
        .actions { max-width: 760px; margin: 0 auto 16px; text-align: right; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; border: 1px solid #d1d5db;
               background: #111827; color: #fff; text-decoration: none; font-size: 0.9rem; cursor: pointer; }
        .btn.secondary { background: #fff; color: #111827; }
        .foot { margin-top: 40px; color: #6b7280; font-size: 0.8rem; line-height: 1.6; }
        .err { max-width: 760px; margin: 40px auto; background: #fff; border: 1px solid #e5e7eb;
               border-radius: 8px; padding: 32px; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: 0; border-radius: 0; padding: 24px; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>

<?php if (!$valid): ?>
    <div class="err">
        <h1>Factuur niet beschikbaar</h1>
        <p class="muted">Deze betaling bestaat niet, is niet van jou, of is (nog) niet betaald.</p>
        <p><a class="btn secondary" href="subscribe.php">Terug naar abonnement</a></p>
    </div>
<?php else: ?>

    <div class="actions">
        <a class="btn secondary" href="subscribe.php">← Terug</a>
        <a class="btn" href="#" onclick="window.print();return false;">Download / print (PDF)</a>
    </div>

    <div class="sheet">
        <div class="top">
            <div>
                <div class="brand"><?= htmlspecialchars(INVOICE_COMPANY_NAME) ?></div>
                <div class="muted small">
                    <?php foreach (array_filter(array_map('trim', explode("\n", INVOICE_COMPANY_ADDRESS))) as $line): ?>
                        <?= htmlspecialchars($line) ?><br>
                    <?php endforeach; ?>
                    <?php if (INVOICE_COMPANY_EMAIL): ?><?= htmlspecialchars(INVOICE_COMPANY_EMAIL) ?><br><?php endif; ?>
                    <?php if (INVOICE_COMPANY_KVK): ?>KvK: <?= htmlspecialchars(INVOICE_COMPANY_KVK) ?><br><?php endif; ?>
                    <?php if (INVOICE_COMPANY_VATID): ?>BTW: <?= htmlspecialchars(INVOICE_COMPANY_VATID) ?><?php endif; ?>
                </div>
            </div>
            <div class="meta">
                <h1>Factuur</h1>
                <div class="small"><strong><?= htmlspecialchars($invNo) ?></strong></div>
                <div class="small muted">Datum: <?= htmlspecialchars(date('d-m-Y', strtotime($paidDate))) ?></div>
            </div>
        </div>

        <div class="parties">
            <div>
                <h2>Aan</h2>
                <div><?= htmlspecialchars($uInfo['username']) ?></div>
                <?php if (!empty($uInfo['email'])): ?>
                <div class="muted small"><?= htmlspecialchars($uInfo['email']) ?></div>
                <?php endif; ?>
            </div>
            <div class="meta">
                <h2>Betaling</h2>
                <div class="small">Voldaan via Mollie</div>
                <?php if (!empty($pay['mollie_payment_id'])): ?>
                <div class="muted small">Ref: <?= htmlspecialchars($pay['mollie_payment_id']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Omschrijving</th><th class="num">Bedrag</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($pay['description'] ?: 'LiveGig abonnement') ?></td>
                    <td class="num"><?= fmtEur($vatPct > 0 ? $net : $gross) ?></td>
                </tr>
            </tbody>
        </table>

        <table class="totals">
            <?php if ($vatPct > 0): ?>
            <tr><td>Subtotaal</td><td class="num"><?= fmtEur($net) ?></td></tr>
            <tr><td>Btw <?= rtrim(rtrim(number_format($vatPct, 2, ',', ''), '0'), ',') ?>%</td><td class="num"><?= fmtEur($vatAmt) ?></td></tr>
            <?php endif; ?>
            <tr class="grand"><td>Totaal <?= htmlspecialchars($cur) ?></td><td class="num"><?= fmtEur($gross) ?></td></tr>
        </table>

        <div class="foot">
            <?php if ($vatPct <= 0): ?>
            Geen btw in rekening gebracht.
            <?php endif; ?>
            Dit bedrag is voldaan; deze factuur dient als betaalbewijs. Bedankt voor je bijdrage —
            die houdt LiveGig betaalbaar en onafhankelijk.
        </div>
    </div>

    <?php if (($_GET['print'] ?? '') === '1'): ?>
    <script>window.addEventListener("load", function(){ window.print(); });</script>
    <?php endif; ?>

<?php endif; ?>
</body>
</html>
