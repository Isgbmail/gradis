<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();

$counts = array_fill_keys(array_keys(ORDER_STATUSES), 0);
foreach (q('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
$totalOrders = array_sum($counts);
$in = "'" . implode("','", REVENUE_STATUSES) . "'";
$revenue = (float)qv("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($in)");
$pendingValue = (float)qv("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'pending'");
$avg = $totalOrders ? (float)qv("SELECT COALESCE(AVG(total),0) FROM orders WHERE status <> 'cancelled'") : 0;
$recent = q('SELECT id, number, first_name, last_name, city, total, status, created_at FROM orders ORDER BY id DESC LIMIT 8');

// 30 derniers jours
$since = date('Y-m-d', strtotime('-29 days'));
$byDay = [];
foreach (q("SELECT substr(created_at,1,10) d, COUNT(*) n, COALESCE(SUM(CASE WHEN status IN ($in) THEN total END),0) rev FROM orders WHERE substr(created_at,1,10) >= ? GROUP BY d", [$since]) as $r) $byDay[$r['d']] = $r;
$labels = $orders30 = $rev30 = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($d));
    $orders30[] = (int)($byDay[$d]['n'] ?? 0);
    $rev30[] = (float)($byDay[$d]['rev'] ?? 0);
}
$topCities = q("SELECT city, COUNT(*) n FROM orders WHERE status <> 'cancelled' GROUP BY city ORDER BY n DESC LIMIT 5");
$topItems = q("SELECT i.title, SUM(i.qty) q FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status <> 'cancelled' GROUP BY i.title ORDER BY q DESC LIMIT 5");

admin_head('Tableau de bord', 'dash');
?>
<?php if (setting('demo_notice') === '1'): ?>
<div class="alert alert-warning"><i class="bi bi-info-circle"></i> Les produits, prix et avis du site sont des <strong>données de démonstration</strong>. Modifiez-les dans Produits, Gammes & options et Avis. Les statistiques ci-dessous ne comptent que les commandes réellement enregistrées.
    <form method="post" action="<?= e(url('admin/settings.php')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="hide_demo"><button class="btn btn-sm btn-outline-dark ms-2">Masquer ce message et la mention « exemple » du site</button></form></div>
<?php endif; ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="adm-stat"><small>Commandes</small><strong><?= $totalOrders ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="adm-stat adm-stat--gold"><small>Chiffre d'affaires (hors attente/annulées)</small><strong><?= e(money($revenue)) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="adm-stat"><small>En attente de traitement</small><strong><?= e(money($pendingValue)) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="adm-stat"><small>Panier moyen</small><strong><?= e(money(round($avg))) ?></strong></div></div>
</div>
<div class="row g-3 mb-3">
    <?php foreach (ORDER_STATUSES as $k => $label): ?>
        <div class="col-6 col-md-4 col-xl-2"><a class="adm-stat d-block text-decoration-none" href="<?= e(url('admin/orders.php?status=' . $k)) ?>"><small><?= e($label) ?></small><strong><?= $counts[$k] ?></strong></a></div>
    <?php endforeach; ?>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="adm-card"><h2>Commandes et chiffre d'affaires — 30 derniers jours</h2><div class="adm-chartbox"><canvas id="chDays" role="img" aria-label="Commandes par jour"></canvas></div></div></div>
    <div class="col-lg-4"><div class="adm-card"><h2>Répartition par statut</h2><div class="adm-chartbox"><canvas id="chStatus" role="img" aria-label="Répartition des statuts"></canvas></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-7"><div class="adm-card"><h2>Commandes récentes</h2>
        <?php if (!$recent): ?><p class="text-muted mb-0">Aucune commande pour le moment.</p><?php else: ?>
        <div class="table-responsive"><table class="table adm-table"><thead><tr><th>N°</th><th>Client</th><th>Ville</th><th class="text-end">Total</th><th>Statut</th></tr></thead><tbody>
        <?php foreach ($recent as $o): ?><tr>
            <td><a href="<?= e(url('admin/order.php?id=' . $o['id'])) ?>" class="fw-semibold"><?= e($o['number']) ?></a><div class="small text-muted"><?= e(date('d/m H:i', strtotime($o['created_at']))) ?></div></td>
            <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?></td><td><?= e($o['city']) ?></td>
            <td class="text-end"><?= e(money($o['total'])) ?></td>
            <td><span class="badge text-bg-<?= e(STATUS_BADGE[$o['status']]) ?>"><?= e(ORDER_STATUSES[$o['status']]) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></div></div>
    <div class="col-lg-5 d-flex flex-column gap-3">
        <div class="adm-card"><h2>Articles les plus commandés</h2>
            <?php if (!$topItems): ?><p class="text-muted mb-0">Pas encore de données.</p><?php else: ?><ol class="mb-0 ps-3"><?php foreach ($topItems as $t): ?><li><?= e($t['title']) ?> <span class="text-muted">× <?= (int)$t['q'] ?></span></li><?php endforeach; ?></ol><?php endif; ?></div>
        <div class="adm-card"><h2>Villes principales</h2>
            <?php if (!$topCities): ?><p class="text-muted mb-0">Pas encore de données.</p><?php else: ?><ul class="mb-0 ps-3"><?php foreach ($topCities as $t): ?><li><?= e($t['city']) ?> <span class="text-muted">— <?= (int)$t['n'] ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
    </div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">window.ADM = <?= json_for_script(['labels' => $labels, 'orders' => $orders30, 'rev' => $rev30,
    'status' => ['labels' => array_values(ORDER_STATUSES), 'data' => array_values($counts)]]) ?>;</script>
<?php admin_foot(['assets/vendor/chart/chart.umd.js', 'assets/js/admin-dashboard.js']);
