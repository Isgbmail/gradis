<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';

$products = catalog_products();
$cats = categories_all();
$tiers = tiers_all();
$opts = options_all();

// Couleurs distinctes proposées par les produits (pour le filtre)
$used = [];
foreach ($products as $p) foreach ($p['color_opts'] as $c) $used[$c['code']] = $c;
$sizes = array_column($opts['size'] ?? [], 'label', 'code');
$prices = array_map(fn($p) => (float)$p['price'], $products);
$pmin = $prices ? (int)floor(min($prices)) : 0;
$pmax = $prices ? (int)ceil(max($prices)) : 1000;

page_head('Nos tenues', ['active' => 'shop', 'desc' => 'Catalogue Gradis : tenues complètes, toges, écharpes, toques et accessoires de graduation. Filtrez par gamme, couleur, taille et prix.']);
?>
<section class="gr-pagehead">
    <div class="container">
        <div class="gr-crumbs"><a href="<?= e(url('index.php')) ?>">Accueil</a> / Nos tenues</div>
        <h1>Nos tenues</h1>
        <p>Tenues complètes ou pièces à l'unité : choisissez, filtrez, comparez.</p>
    </div>
</section>

<section class="py-4 py-lg-5">
    <div class="container">
        <div class="d-flex gap-2 mb-3 align-items-center">
            <div class="position-relative flex-grow-1">
                <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                <input type="search" id="fSearch" class="form-control ps-5" placeholder="Recherche instantanée…" aria-label="Rechercher dans le catalogue" autocomplete="off" value="<?= e($_GET['q'] ?? '') ?>">
            </div>
            <button class="btn btn-gr-navy gr-filter-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#filters" aria-controls="filters"><i class="bi bi-sliders"></i> Filtres</button>
            <select id="fSort" class="form-select w-auto d-none d-sm-block" aria-label="Trier">
                <option value="order">Pertinence</option>
                <option value="price-asc">Prix croissant</option>
                <option value="price-desc">Prix décroissant</option>
                <option value="name">Nom (A–Z)</option>
            </select>
        </div>

        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="filters" aria-labelledby="filtersLabel">
                    <div class="offcanvas-header">
                        <h2 class="offcanvas-title h4" id="filtersLabel">Filtres</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filters" aria-label="Fermer"></button>
                    </div>
                    <div class="offcanvas-body d-block">
                        <div class="gr-panel gr-filters">
                            <div class="d-sm-none mb-3">
                                <label class="gr-label" for="fSortM">Trier par</label>
                                <select id="fSortM" class="form-select">
                                    <option value="order">Pertinence</option><option value="price-asc">Prix croissant</option>
                                    <option value="price-desc">Prix décroissant</option><option value="name">Nom (A–Z)</option>
                                </select>
                            </div>
                            <span class="gr-label">Type de produit</span>
                            <div class="gr-chips" id="fCat" role="radiogroup" aria-label="Type de produit">
                                <label class="gr-chip"><input type="radio" name="cat" value="" checked><span>Tous</span></label>
                                <?php foreach ($cats as $c): ?>
                                    <label class="gr-chip"><input type="radio" name="cat" value="<?= e($c['slug']) ?>" <?= ($_GET['cat'] ?? '') === $c['slug'] ? 'checked' : '' ?>><span><?= e($c['name']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <span class="gr-label">Gamme</span>
                            <div class="gr-chips" id="fTier" role="radiogroup" aria-label="Gamme">
                                <label class="gr-chip"><input type="radio" name="tier" value="" checked><span>Toutes</span></label>
                                <?php foreach ($tiers as $code => $t): ?>
                                    <label class="gr-chip"><input type="radio" name="tier" value="<?= e($code) ?>" <?= ($_GET['tier'] ?? '') === $code ? 'checked' : '' ?>><span><?= e($t['name']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($used): ?>
                            <span class="gr-label">Couleur</span>
                            <div class="gr-chips" id="fColor" role="radiogroup" aria-label="Couleur">
                                <label class="gr-chip"><input type="radio" name="color" value="" checked><span>Toutes</span></label>
                                <?php foreach ($used as $code => $c): ?>
                                    <label class="gr-chip gr-chip--color"><input type="radio" name="color" value="<?= e($code) ?>"><span><i class="gr-dot" style="--c:<?= e($c['hex']) ?>"></i><?= e($c['label']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <span class="gr-label">Taille</span>
                            <div class="gr-chips" id="fSize" role="radiogroup" aria-label="Taille">
                                <label class="gr-chip"><input type="radio" name="size" value="" checked><span>Toutes</span></label>
                                <?php foreach ($sizes as $code => $lab): ?>
                                    <label class="gr-chip"><input type="radio" name="size" value="<?= e($code) ?>"><span><?= e($lab) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <span class="gr-label">Prix (DH)</span>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" id="fMin" class="form-control" min="0" placeholder="<?= $pmin ?>" aria-label="Prix minimum" inputmode="numeric">
                                <span>–</span>
                                <input type="number" id="fMax" class="form-control" min="0" placeholder="<?= $pmax ?>" aria-label="Prix maximum" inputmode="numeric">
                            </div>
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="fStock"><label class="form-check-label" for="fStock">En stock uniquement</label>
                            </div>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="fFav"><label class="form-check-label" for="fFav">Mes favoris <i class="bi bi-heart-fill text-danger"></i></label>
                            </div>
                            <button type="button" class="btn btn-gr-outline w-100 mt-4" id="fReset">Réinitialiser</button>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted" id="fCount" aria-live="polite"><?= count($products) ?> produit<?= count($products) > 1 ? 's' : '' ?></div>
                </div>
                <?php if ($products): ?>
                    <div class="row g-3 g-xl-4" id="grid">
                        <?php foreach ($products as $p) echo product_card($p); ?>
                    </div>
                    <div class="gr-empty" id="noResult" hidden>
                        <i class="bi bi-search"></i><strong class="d-block text-dark mb-1">Aucun produit ne correspond à vos critères</strong>
                        Essayez d'élargir les filtres, ou <button class="btn btn-link p-0 align-baseline" type="button" id="fReset2">réinitialisez-les</button>.
                    </div>
                <?php else: ?>
                    <div class="gr-empty"><i class="bi bi-bag"></i>Le catalogue est vide pour le moment. Composez votre tenue dans le
                        <a href="<?= e(url('configurateur.php')) ?>">configurateur</a> ou écrivez-nous sur WhatsApp.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script type="application/json" id="gr-products"><?= json_encode(array_map('product_public', $products), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php page_foot(['scripts' => ['assets/js/catalogue.js']]);
