<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();

$f = [
    'q' => trim((string)($_GET['q'] ?? '')), 'status' => (string)($_GET['status'] ?? ''), 'city' => trim((string)($_GET['city'] ?? '')),
    'from' => (string)($_GET['from'] ?? ''), 'to' => (string)($_GET['to'] ?? ''),
];
$where = ['1=1']; $p = [];
if ($f['q'] !== '') { $where[] = '(number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)'; $l = '%' . $f['q'] . '%'; array_push($p, $l, $l, $l, $l, $l); }
if (isset(ORDER_STATUSES[$f['status']])) { $where[] = 'status = ?'; $p[] = $f['status']; }
if ($f['city'] !== '') { $where[] = 'city LIKE ?'; $p[] = '%' . $f['city'] . '%'; }
$validDate = fn(string $d) => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
if (!$validDate($f['from'])) $f['from'] = '';
if (!$validDate($f['to'])) $f['to'] = '';
if ($f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to']) [$f['from'], $f['to']] = [$f['to'], $f['from']]; // dates inversées : on les remet dans l'ordre
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'substr(created_at,1,10) >= ?'; $p[] = $f['from']; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'substr(created_at,1,10) <= ?'; $p[] = $f['to']; }
$W = implode(' AND ', $where);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commandes-gradis-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Numéro', 'Date', 'Statut', 'Nom', 'Prénom', 'Téléphone', 'E-mail', 'Ville', 'Adresse', 'Sous-total', 'Livraison', 'Total', 'Paiement'], ';');
    foreach (q("SELECT * FROM orders WHERE $W ORDER BY id DESC", $p) as $r)
        fputcsv($o, [$r['number'], $r['created_at'], ORDER_STATUSES[$r['status']], $r['last_name'], $r['first_name'], $r['phone'], $r['email'], $r['city'], $r['address'], $r['subtotal'], $r['shipping'], $r['total'], $r['payment_method'] . '/' . $r['payment_status']], ';');
    exit;
}

$per = 25; $page = max(1, (int)($_GET['page'] ?? 1));
$total = (int)qv("SELECT COUNT(*) FROM orders WHERE $W", $p);
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$rows = q("SELECT * FROM orders WHERE $W ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
$qs = fn(array $extra = []) => '?' . http_build_query(array_filter(array_merge($f, $extra), fn($v) => $v !== '' && $v !== null));

admin_head('Commandes', 'orders'); ?>
<form class="adm-card mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label" for="q">Recherche</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="N°, nom, téléphone, e-mail"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="st">Statut</label><select class="form-select" id="st" name="status"><option value="">Tous</option><?php foreach (ORDER_STATUSES as $k => $v): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="ci">Ville</label><input class="form-control" id="ci" name="city" value="<?= e($f['city']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="fr">Du</label><input class="form-control" type="date" id="fr" name="from" max="<?= date('Y-m-d') ?>" value="<?= e($f['from']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="to">Au</label><input class="form-control" type="date" id="to" name="to" max="<?= date('Y-m-d') ?>" value="<?= e($f['to']) ?>"></div>
        <div class="col-md-1 d-grid"><button class="btn btn-gr">OK</button></div>
    </div>
</form>
<div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-muted"><?= $total ?> commande<?= $total > 1 ? 's' : '' ?></span>
    <a class="btn btn-sm btn-outline-dark" href="<?= e($qs(['export' => 'csv'])) ?>"><i class="bi bi-download"></i> Export CSV</a>
</div>
<div class="adm-card p-0">
<?php if (!$rows): ?><p class="p-4 mb-0 text-muted">Aucune commande ne correspond.</p><?php else: ?>
<div class="table-responsive"><table class="table table-hover adm-table"><thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Ville</th><th class="text-end">Total</th><th>Paiement</th><th>Statut</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $o): ?><tr>
    <td class="fw-semibold"><a href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>"><?= e($o['number']) ?></a></td>
    <td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td>
    <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><div class="small text-muted"><?= e($o['phone']) ?></div></td>
    <td><?= e($o['city']) ?></td><td class="text-end text-nowrap"><?= e(money($o['total'])) ?></td>
    <td class="small"><?= e(['cod' => 'À la livraison', 'whatsapp' => 'WhatsApp'][$o['payment_method']] ?? $o['payment_method']) ?><br><span class="text-muted"><?= e(PAYMENT_STATUSES[$o['payment_status']] ?? '') ?></span></td>
    <td><span class="badge text-bg-<?= e(STATUS_BADGE[$o['status']]) ?>"><?= e(ORDER_STATUSES[$o['status']]) ?></span></td>
    <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>">Ouvrir</a>
        <a class="btn btn-sm btn-success" target="_blank" rel="noopener" aria-label="WhatsApp" href="<?= e('https://wa.me/' . wa_phone($o['phone']) . '?text=' . rawurlencode("Bonjour {$o['first_name']}, c'est Gradis au sujet de votre commande {$o['number']}.")) ?>"><i class="bi bi-whatsapp"></i></a></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php if ($pages > 1): ?><nav class="mt-3" aria-label="Pages"><ul class="pagination"><?php for ($i = 1; $i <= $pages; $i++): ?><li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav><?php endif;
admin_foot();
