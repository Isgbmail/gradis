<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';

$methods = payment_methods();
$deliveryEnabled = delivery_enabled();
$cities = ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda', 'Kénitra', 'Tétouan', 'Salé', 'Témara', 'Mohammedia', 'El Jadida', 'Safi', 'Nador', 'Béni Mellal', 'Settat', 'Khouribga', 'Taza', 'Essaouira', 'Ouarzazate', 'Errachidia', 'Larache', 'Al Hoceïma', 'Berkane', 'Khémisset', 'Laâyoune', 'Dakhla', 'Guelmim'];

page_head(t('checkout.title'), ['active' => '', 'desc' => 'Finalisez votre commande Gradis.']);
?>
<section class="gr-pagehead">
    <div class="container">
        <div class="gr-crumbs"><a href="<?= e(url('index.php')) ?>">Accueil</a> / <a href="<?= e(url('panier.php')) ?>">Panier</a> / Commande</div>
        <h1><?= e(t('checkout.title')) ?></h1>
    </div>
</section>

<section class="py-4 py-lg-5">
    <div class="container">
        <div id="coLoading" class="text-center py-5"><span class="gr-spinner"></span></div>
        <div id="coEmpty" class="gr-empty" hidden><i class="bi bi-bag"></i><h2 class="h3">Votre panier est vide</h2>
            <a class="btn btn-gr mt-2" href="<?= e(url('catalogue.php')) ?>">Voir le catalogue</a></div>

        <?php if (!$deliveryEnabled): ?>
            <div class="alert alert-info">La livraison n'est pas proposée en ligne pour le moment. Contactez-nous sur
                <a href="<?= e(wa_link(wa_greeting() . "\nJe souhaite commander et convenir des modalités de livraison.")) ?>" target="_blank" rel="noopener">WhatsApp</a>
                pour passer commande.</div>
        <?php elseif (!$methods): ?>
            <div class="alert alert-warning">Aucun mode de commande n'est activé pour le moment. Contactez-nous sur
                <a href="<?= e(wa_link(wa_greeting())) ?>" target="_blank" rel="noopener">WhatsApp</a>.</div>
        <?php endif; ?>

        <?php if ($deliveryEnabled): ?>
        <form id="coForm" class="row g-4" novalidate hidden>
            <div class="col-lg-7">
                <div class="gr-panel mb-4">
                    <h2 class="h4 mb-3">Vos coordonnées</h2>
                    <div class="row g-3">
                        <div class="col-sm-6"><label class="form-label" for="last_name">Nom</label><input class="form-control" id="last_name" name="last_name" autocomplete="family-name" required maxlength="100"><div class="invalid-feedback"></div></div>
                        <div class="col-sm-6"><label class="form-label" for="first_name">Prénom</label><input class="form-control" id="first_name" name="first_name" autocomplete="given-name" required maxlength="100"><div class="invalid-feedback"></div></div>
                        <div class="col-sm-6"><label class="form-label" for="email">Adresse e-mail</label><input class="form-control" id="email" name="email" type="email" autocomplete="email" required maxlength="190"><div class="invalid-feedback"></div></div>
                        <div class="col-sm-6"><label class="form-label" for="phone">Téléphone</label><input class="form-control" id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="06 12 34 56 78" required maxlength="25"><div class="invalid-feedback"></div></div>
                    </div>
                </div>
                <div class="gr-panel mb-4">
                    <h2 class="h4 mb-3">Livraison</h2>
                    <div class="row g-3">
                        <div class="col-sm-5"><label class="form-label" for="city">Ville</label><input class="form-control" id="city" name="city" list="cities" autocomplete="address-level2" required maxlength="100"><datalist id="cities"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist><div class="invalid-feedback"></div></div>
                        <div class="col-sm-7"><label class="form-label" for="address">Adresse complète</label><textarea class="form-control" id="address" name="address" rows="2" autocomplete="street-address" required maxlength="400" placeholder="N°, rue, quartier, repère…"></textarea><div class="invalid-feedback"></div></div>
                        <div class="col-12"><label class="form-label" for="note">Remarque (facultatif)</label><textarea class="form-control" id="note" name="note" rows="2" maxlength="500" placeholder="Date de la cérémonie, horaires de livraison…"></textarea></div>
                    </div>
                </div>
                <div class="gr-panel">
                    <h2 class="h4 mb-3">Mode de commande</h2>
                    <?php $first = true; foreach ($methods as $code => $m): ?>
                        <label class="gr-paycard"><input class="form-check-input mt-1" type="radio" name="payment_method" value="<?= e($code) ?>" <?= $first ? 'checked' : '' ?>><span><strong><?= e($m['label']) ?></strong><small><?= e($m['hint']) ?></small></span></label>
                    <?php $first = false; endforeach; ?>
                    <div class="invalid-feedback d-block" id="err_payment_method"></div>
                    <p class="gr-note mb-0"><i class="bi bi-shield-lock"></i> Aucun paiement en ligne n'est effectué sur ce site : le règlement se fait selon le mode choisi ci-dessus.</p>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="gr-panel gr-summary">
                    <h2 class="h4 mb-3">Résumé de la commande</h2>
                    <div id="coLines"></div>
                    <div class="gr-sumrow"><span>Sous-total</span><span id="cSub"></span></div>
                    <div class="gr-sumrow"><span>Livraison</span><span id="cShip"></span></div>
                    <div class="gr-sumrow gr-sumrow--total align-items-baseline"><span>Total</span><strong id="cTotal"></strong></div>
                    <div class="alert alert-danger mt-3 mb-0" id="coError" role="alert" hidden></div>
                    <button type="submit" class="btn btn-gr btn-lg w-100 mt-3" id="coSubmit" <?= $methods ? '' : 'disabled' ?>><span class="label">Confirmer la commande</span></button>
                    <a class="btn btn-link w-100 mt-1" href="<?= e(url('panier.php')) ?>">Modifier mon panier</a>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php page_foot(['scripts' => $deliveryEnabled ? ['assets/js/commande.js'] : []]);
