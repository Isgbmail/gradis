<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';

$order = null;
$n = (string)($_GET['n'] ?? ''); $tok = (string)($_GET['t'] ?? '');
if ($n !== '' && $tok !== '') {
    $o = q1('SELECT * FROM orders WHERE number = ?', [$n]);
    if ($o && hash_equals((string)$o['access_token'], $tok)) $order = $o;
}
if (!$order) {
    http_response_code(404);
    page_head('Commande introuvable');
    echo '<section class="gr-section"><div class="container"><div class="gr-empty"><i class="bi bi-search"></i><h1 class="h3">Commande introuvable</h1><p>Le lien est incomplet ou invalide.</p><a class="btn btn-gr" href="' . e(url('index.php')) . '">Retour à l\'accueil</a></div></div></section>';
    page_foot();
    exit;
}
$items = q('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
$methods = ['cod' => 'Paiement à la livraison', 'whatsapp' => 'Confirmation par WhatsApp'];
$waMsg = wa_order_message($order, $items);
page_head('Commande confirmée', ['active' => '']);
?>
<section class="gr-section">
    <div class="container" style="max-width:860px">
        <div class="text-center mb-4">
            <div class="gr-confirm-ico"><i class="bi bi-check2"></i></div>
            <h1 class="mb-2">Merci <?= e($order['first_name']) ?>, votre commande est enregistrée</h1>
            <p class="text-muted mb-1">Numéro de commande</p>
            <div class="fs-3 fw-semibold" style="letter-spacing:.06em"><?= e($order['number']) ?></div>
            <p class="mt-3 text-muted">Nous vous contactons très prochainement pour confirmer les détails. Conservez ce numéro.</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a class="btn btn-gr btn-lg" href="<?= e(wa_link($waMsg)) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Envoyer ma commande sur WhatsApp</a>
                <a class="btn btn-gr-outline btn-lg" href="<?= e(url('catalogue.php')) ?>">Continuer mes achats</a>
            </div>
        </div>

        <div class="gr-panel mb-3">
            <h2 class="h4">Récapitulatif</h2>
            <?php foreach ($items as $it): $cfg = json_decode((string)$it['config_json'], true); ?>
                <div class="d-flex gap-3 py-3 border-bottom">
                    <div class="gr-line__img" style="width:64px" data-outfit='<?= e(json_encode($cfg['render'] ?? [], JSON_UNESCAPED_UNICODE)) ?>'></div>
                    <div class="flex-grow-1">
                        <strong><?= e($it['title']) ?></strong> <span class="text-muted">× <?= (int)$it['qty'] ?></span>
                        <ul class="gr-line__details mb-0"><?php foreach (preg_split('/\R/', (string)$it['details']) ?: [] as $d) if (trim($d) !== '') echo '<li>' . e($d) . '</li>'; ?></ul>
                    </div>
                    <div class="fw-semibold text-nowrap"><?= e(money($it['line_total'])) ?></div>
                </div>
            <?php endforeach; ?>
            <div class="gr-sumrow mt-2"><span>Sous-total</span><span><?= e(money($order['subtotal'])) ?></span></div>
            <div class="gr-sumrow"><span>Livraison</span><span><?= $order['shipping'] > 0 ? e(money($order['shipping'])) : 'Offerte' ?></span></div>
            <div class="gr-sumrow gr-sumrow--total align-items-baseline"><span>Total</span><strong><?= e(money($order['total'])) ?></strong></div>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><div class="gr-panel h-100"><h2 class="h5">Livraison</h2><p class="mb-0"><?= e($order['first_name'] . ' ' . $order['last_name']) ?><br><?= nl2br(e($order['address'])) ?><br><?= e($order['city']) ?><br><?= e($order['phone']) ?></p></div></div>
            <div class="col-md-6"><div class="gr-panel h-100"><h2 class="h5">Règlement</h2><p class="mb-1"><?= e($methods[$order['payment_method']] ?? $order['payment_method']) ?></p><p class="text-muted small mb-0">Aucun paiement n'a été effectué en ligne. Statut : <strong>en attente de confirmation</strong>.</p></div></div>
        </div>
    </div>
</section>
<script nonce="<?= e(csp_nonce()) ?>">try { localStorage.removeItem('gradis.cart.v1'); } catch (e) {}</script>
<?php page_foot();
