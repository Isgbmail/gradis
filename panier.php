<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';
page_head('Panier', ['active' => '', 'desc' => 'Votre panier Gradis.']);
?>
<section class="gr-pagehead">
    <div class="container">
        <div class="gr-crumbs"><a href="<?= e(url('index.php')) ?>">Accueil</a> / Panier</div>
        <h1><?= e(t('cart.title')) ?></h1>
    </div>
</section>

<section class="py-4 py-lg-5">
    <div class="container">
        <div id="cartLoading" class="text-center py-5"><span class="gr-spinner"></span><div class="text-muted mt-2">Chargement de votre panier…</div></div>

        <div id="cartEmpty" class="gr-empty" hidden>
            <i class="bi bi-bag"></i><h2 class="h3"><?= e(t('cart.empty')) ?></h2>
            <p>Découvrez nos tenues ou composez la vôtre.</p>
            <a class="btn btn-gr" href="<?= e(url('catalogue.php')) ?>">Voir le catalogue</a>
            <a class="btn btn-gr-outline" href="<?= e(url('configurateur.php')) ?>">Composer ma tenue</a>
        </div>

        <div id="cartError" class="alert alert-danger" hidden role="alert"></div>

        <div class="row g-4" id="cartBody" hidden>
            <div class="col-lg-8"><div class="gr-panel" id="cartLines"></div>
                <a class="btn btn-link mt-2 ps-0" href="<?= e(url('catalogue.php')) ?>"><i class="bi bi-arrow-left"></i> <?= e(t('btn.continue')) ?></a></div>
            <div class="col-lg-4">
                <div class="gr-panel gr-summary">
                    <h2 class="h4 mb-3">Récapitulatif</h2>
                    <div class="gr-sumrow"><span>Sous-total</span><span id="sSub">—</span></div>
                    <div class="gr-sumrow"><span>Livraison</span><span id="sShip">—</span></div>
                    <div class="small text-muted" id="sFree"></div>
                    <div class="small text-muted mt-2" id="sDeliveryNote" hidden>La livraison est à convenir avec nous sur WhatsApp.</div>
                    <div class="gr-sumrow gr-sumrow--total align-items-baseline"><span>Total</span><strong id="sTotal">—</strong></div>
                    <div class="d-grid gap-2 mt-3">
                        <a class="btn btn-gr btn-lg" id="goCheckout" href="<?= e(url('commande.php')) ?>"><?= e(t('btn.checkout')) ?></a>
                        <a class="btn btn-gr-navy" id="waCart" href="#" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> <?= e(t('btn.order_wa')) ?></a>
                    </div>
                    <p class="gr-note mb-0 mt-3"><i class="bi bi-shield-check"></i> Prix confirmés par notre serveur. Aucun paiement n'est demandé en ligne.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php page_foot(['scripts' => ['assets/js/panier.js']]);
