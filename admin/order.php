<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$admin = admin_require();
$id = (int)($_GET['id'] ?? 0);
$o = q1('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('Commande introuvable.', 'danger'); redirect(url('admin/orders.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'status') {
        $new = post('status'); $note = mb_substr(post('note'), 0, 500);
        if (!isset(ORDER_STATUSES[$new])) flash('Statut invalide.', 'danger');
        elseif ($o['status'] === 'cancelled' && $new !== 'cancelled') flash('Une commande annulée (stock déjà remis en vente) ne peut pas être réactivée. Créez une nouvelle commande.', 'danger');
        elseif ($new !== $o['status'] || $note !== '') {
            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($new === 'cancelled' && $o['status'] !== 'cancelled') restock_order($id);
                ex('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?', [$new, now(), $id]);
                insert('order_status_history', ['order_id' => $id, 'status' => $new, 'note' => $note ?: null, 'admin_id' => $admin['id'], 'created_at' => now()]);
                $pdo->commit();
                flash('Statut mis à jour : ' . ORDER_STATUSES[$new] . ($new === 'cancelled' ? ' (stock remis en vente).' : '.'));
            } catch (Throwable $e) { $pdo->rollBack(); error_log($e->getMessage()); flash('Erreur lors de la mise à jour.', 'danger'); }
        }
    } elseif ($act === 'note') {
        ex('UPDATE orders SET admin_note = ?, updated_at = ? WHERE id = ?', [mb_substr(post('admin_note'), 0, 2000), now(), $id]);
        flash('Note interne enregistrée.');
    } elseif ($act === 'payment') {
        $ps = post('payment_status');
        if (isset(PAYMENT_STATUSES[$ps])) { ex('UPDATE orders SET payment_status = ?, updated_at = ? WHERE id = ?', [$ps, now(), $id]); flash('Statut de paiement mis à jour (saisie manuelle).'); }
    }
    redirect(url('admin/order.php?id=' . $id));
}

$items = q('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
$custs = [];
foreach (q('SELECT c.* FROM customizations c JOIN order_items i ON i.id = c.order_item_id WHERE i.order_id = ?', [$id]) as $c) $custs[$c['order_item_id']][] = $c;
$hist = q('SELECT h.*, a.name FROM order_status_history h LEFT JOIN admin_users a ON a.id = h.admin_id WHERE h.order_id = ? ORDER BY h.id DESC', [$id]);
$waMsg = wa_greeting() . " ici Gradis. Votre commande {$o['number']} : statut « " . ORDER_STATUSES[$o['status']] . " ». Total : " . money($o['total']) . '.';
$waUrl = 'https://wa.me/' . wa_phone($o['phone']) . '?text=' . rawurlencode($waMsg);
$trimLabel = setting('label_trim', 'Billets décoratifs');
admin_head('Commande ' . $o['number'], 'orders'); ?>
<p><a href="<?= e(url('admin/orders.php')) ?>"><i class="bi bi-arrow-left"></i> Toutes les commandes</a></p>
<div class="row g-3">
<div class="col-lg-8 d-flex flex-column gap-3">
    <div class="adm-card">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div><h2 class="mb-0"><?= e($o['number']) ?></h2><div class="text-muted small">Passée le <?= e(date('d/m/Y à H:i', strtotime($o['created_at']))) ?></div></div>
            <div><span class="badge fs-6 text-bg-<?= e(STATUS_BADGE[$o['status']]) ?>"><?= e(ORDER_STATUSES[$o['status']]) ?></span></div>
        </div>
        <?php foreach ($items as $it): $cfg = json_decode((string)$it['config_json'], true) ?: []; ?>
        <div class="d-flex gap-3 py-3 border-top">
            <div class="adm-prev" data-outfit='<?= e(json_encode($cfg['render'] ?? [], JSON_UNESCAPED_UNICODE)) ?>'></div>
            <div class="flex-grow-1">
                <div class="fw-semibold"><?= e($it['title']) ?> <span class="badge text-bg-light border"><?= e($it['tier_name']) ?></span></div>
                <ul class="small mb-1 ps-3 mt-1"><?php foreach (preg_split('/\R/', (string)$it['details']) ?: [] as $d) if (trim($d) !== ''): ?><li><?= e(str_replace('Billets décoratifs', $trimLabel, $d)) ?></li><?php endif; ?></ul>
                <?php foreach ($custs[$it['id']] ?? [] as $c): ?>
                    <div class="small border rounded p-2 mb-1 bg-light"><i class="bi bi-brush"></i> <strong><?= $c['zone'] === 'scarf' ? 'Écharpe' : 'Toque' ?></strong> :
                        « <?= e($c['body']) ?> » — police <?= e($c['font']) ?>, fil <?= e($c['color']) ?>, <?= e(['left' => 'à gauche', 'center' => 'centré', 'right' => 'à droite'][$c['align']] ?? $c['align']) ?>, <?= (int)$c['font_size'] ?> px</div>
                <?php endforeach; ?>
            </div>
            <div class="text-end text-nowrap"><?= (int)$it['qty'] ?> × <?= e(money($it['unit_price'])) ?><br><strong><?= e(money($it['line_total'])) ?></strong></div>
        </div>
        <?php endforeach; ?>
        <div class="border-top pt-3 ms-auto" style="max-width:320px">
            <div class="d-flex justify-content-between"><span>Sous-total</span><span><?= e(money($o['subtotal'])) ?></span></div>
            <div class="d-flex justify-content-between"><span>Livraison</span><span><?= $o['shipping'] > 0 ? e(money($o['shipping'])) : 'Offerte' ?></span></div>
            <div class="d-flex justify-content-between fs-5 fw-bold"><span>Total</span><span><?= e(money($o['total'])) ?></span></div>
        </div>
        <p class="small text-muted mt-3 mb-0">Les prix ci-dessus sont ceux enregistrés au moment de la commande ; ils ne changent pas si le catalogue évolue.</p>
    </div>
    <div class="adm-card"><h2>Historique</h2><ul class="adm-timeline">
        <?php foreach ($hist as $h): ?><li><strong><?= e(ORDER_STATUSES[$h['status']] ?? $h['status']) ?></strong> — <?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?> <span class="text-muted">(<?= e($h['name'] ?? 'client') ?>)</span><?= $h['note'] ? '<br><span class="text-muted">' . e($h['note']) . '</span>' : '' ?></li><?php endforeach; ?>
    </ul></div>
</div>
<div class="col-lg-4 d-flex flex-column gap-3">
    <div class="adm-card"><h2>Client</h2>
        <div class="fw-semibold"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></div>
        <div><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $o['phone'])) ?>"><?= e($o['phone']) ?></a></div>
        <div><i class="bi bi-envelope"></i> <a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a></div>
        <div class="mt-2"><i class="bi bi-geo-alt"></i> <strong><?= e($o['city']) ?></strong><br><?= nl2br(e($o['address'])) ?></div>
        <?php if ($o['customer_note']): ?><div class="mt-2 small"><strong>Remarque du client :</strong> <?= e($o['customer_note']) ?></div><?php endif; ?>
        <a class="btn btn-success w-100 mt-3" target="_blank" rel="noopener" href="<?= e($waUrl) ?>"><i class="bi bi-whatsapp"></i> Contacter sur WhatsApp</a>
    </div>
    <div class="adm-card"><h2>Statut</h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
            <select class="form-select mb-2" name="status" aria-label="Statut"><?php foreach (ORDER_STATUSES as $k => $v): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <input class="form-control mb-2" name="note" maxlength="500" placeholder="Commentaire (facultatif)">
            <button class="btn btn-gr w-100">Mettre à jour</button>
            <div class="form-text">Annuler une commande remet les articles en stock.</div>
        </form>
    </div>
    <div class="adm-card"><h2>Paiement</h2>
        <div class="small mb-2">Mode choisi : <strong><?= e(['cod' => 'Paiement à la livraison', 'whatsapp' => 'Confirmation WhatsApp'][$o['payment_method']] ?? $o['payment_method']) ?></strong><br>Aucun paiement en ligne n'est géré : ce statut est saisi manuellement.</div>
        <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="payment">
            <select class="form-select" name="payment_status" aria-label="Paiement"><?php foreach (PAYMENT_STATUSES as $k => $v): ?><option value="<?= $k ?>" <?= $o['payment_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <button class="btn btn-outline-dark">OK</button></form>
    </div>
    <div class="adm-card"><h2>Note interne</h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="note">
            <textarea class="form-control mb-2" name="admin_note" rows="4" maxlength="2000" aria-label="Note interne"><?= e($o['admin_note']) ?></textarea>
            <button class="btn btn-outline-dark w-100">Enregistrer la note</button></form>
    </div>
</div>
</div>
<?php admin_foot();
