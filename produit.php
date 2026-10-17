<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';

$slug = (string)($_GET['slug'] ?? '');
$found = $slug === '' ? [] : catalog_products('p.slug = ?', [$slug]);
if (!$found) {
    http_response_code(404);
    page_head('Produit introuvable', ['active' => 'shop']);
    echo '<section class="gr-section"><div class="container"><div class="gr-empty"><i class="bi bi-emoji-frown"></i><h1 class="h3">Ce produit est introuvable ou n\'est plus disponible.</h1><a class="btn btn-gr mt-3" href="' . e(url('catalogue.php')) . '">Retour au catalogue</a></div></div></section>';
    page_foot();
    exit;
}
$p = $found[0];
$tier = tiers_all(false)[$p['tier']] ?? null;
$related = array_slice(array_filter(catalog_products('p.category_id = ? AND p.id <> ?', [$p['category_id'], $p['id']]), fn($x) => true), 0, 4);
$kind = $p['kind'];
$inStock = (int)$p['stock'] > 0;
$tiersAllowed = tier_list_csv($p['tiers_allowed']);
$allTiers = tiers_all(false);

page_head($p['name'], ['active' => 'shop', 'desc' => mb_substr(strip_tags((string)$p['description']), 0, 155)]);
?>
<section class="gr-pagehead">
    <div class="container">
        <div class="gr-crumbs"><a href="<?= e(url('index.php')) ?>">Accueil</a> / <a href="<?= e(url('catalogue.php?cat=' . $p['cat_slug'])) ?>"><?= e($p['cat_name']) ?></a> / <?= e($p['name']) ?></div>
    </div>
</section>

<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-6">
                <div class="gr-gallery" id="gallery">
                    <?php if ($p['images']): ?>
                        <img src="<?= e(product_image_url($p['images'][0])) ?>" alt="<?= e($p['name']) ?>" id="galleryMain">
                    <?php else: ?>
                        <div class="gr-svgwrap" id="pvOutfit"></div>
                    <?php endif; ?>
                </div>
                <?php if (count($p['images']) > 1): ?>
                    <div class="gr-thumbs">
                        <?php foreach ($p['images'] as $i => $img): ?>
                            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-img="<?= e(product_image_url($img)) ?>" aria-label="Image <?= $i + 1 ?>"><img src="<?= e(product_image_url($img)) ?>" alt=""></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <span class="gr-tier gr-tier--<?= e($p['tier']) ?> position-static d-inline-block mb-2"><?= e($p['tier_name']) ?></span>
                <h1 class="mb-2" style="font-size:clamp(2rem,4vw,2.8rem)"><?= e($p['name']) ?></h1>
                <div class="gr-price fs-3 mb-3"><?= e(money($p['price'])) ?></div>
                <p class="text-muted"><?= nl2br(e($p['description'])) ?></p>
                <?php if ($tier && $tier['materials']): ?><p class="small text-muted"><i class="bi bi-gem text-warning"></i> <?= e($tier['materials']) ?></p><?php endif; ?>

                <form id="buyForm" class="mt-4" novalidate>
                    <?php if ($p['color_opts']): ?>
                        <div class="mb-3">
                            <span class="gr-label"><?= $kind === 'outfit' || $kind === 'robe' ? 'Couleur de la toge' : 'Couleur' ?></span>
                            <div class="gr-chips" role="radiogroup" aria-label="Couleur">
                                <?php foreach ($p['color_opts'] as $i => $c): ?>
                                    <label class="gr-chip gr-chip--color"><input type="radio" name="color" value="<?= e($c['code']) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><i class="gr-dot" style="--c:<?= e($c['hex']) ?>"></i><?= e($c['label']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($p['sizes']): ?>
                        <div class="mb-3">
                            <span class="gr-label">Taille</span>
                            <div class="gr-chips" role="radiogroup" aria-label="Taille">
                                <?php foreach ($p['sizes'] as $s): ?>
                                    <label class="gr-chip"><input type="radio" name="size" value="<?= e($s['size']) ?>" <?= $s['stock'] <= 0 ? 'disabled' : '' ?>><span><?= e($s['size']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <div class="gr-note">Une taille barrée est momentanément épuisée. Un doute ? <a href="<?= e(wa_link(wa_greeting() . "\nJe voudrais un conseil de taille pour : {$p['name']}")) ?>" target="_blank" rel="noopener">Demandez conseil sur WhatsApp</a>.</div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-4">
                        <span class="gr-label">Quantité</span>
                        <div class="gr-qty"><button type="button" data-q="-1" aria-label="Moins">−</button><input type="number" id="qty" min="1" max="<?= (int)setting('max_qty', '20') ?>" value="1" aria-label="Quantité"><button type="button" data-q="1" aria-label="Plus">+</button></div>
                    </div>
                    <div class="text-danger small mb-2" id="buyError" role="alert" hidden></div>
                    <?php if (!$inStock): ?><div class="alert alert-warning">Ce produit est momentanément épuisé. Écrivez-nous pour connaître la date de retour en stock.</div><?php endif; ?>

                    <div class="d-grid gap-2 d-sm-flex flex-wrap">
                        <button type="submit" class="btn btn-gr btn-lg flex-grow-1" <?= $inStock ? '' : 'disabled' ?>><i class="bi bi-bag-plus"></i> <?= e(t('btn.add_cart')) ?></button>
                        <?php if ($kind !== 'accessory'): ?>
                            <a class="btn btn-gr-outline btn-lg" href="<?= e(url('configurateur.php?product=' . (int)$p['id'])) ?>"><i class="bi bi-brush"></i> <?= e(t('btn.customize')) ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="d-grid gap-2 d-sm-flex mt-2">
                        <a class="btn btn-gr-navy flex-grow-1" id="waOrder" href="#" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> <?= e(t('btn.order_wa')) ?></a>
                        <a class="btn btn-gr-outline flex-grow-1" id="waAsk" href="<?= e(wa_link(wa_greeting() . "\nJ'ai une question sur : {$p['name']}\n" . (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['HTTPS'] ?? '') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : ''))) ?>" target="_blank" rel="noopener"><i class="bi bi-chat-dots"></i> <?= e(t('btn.ask_wa')) ?></a>
                    </div>
                </form>

                <?php if ($kind === 'accessory' && $tiersAllowed): ?>
                    <p class="gr-note mt-4"><i class="bi bi-info-circle"></i> Compatible avec les gammes :
                        <?= e(implode(', ', array_map(fn($c) => $allTiers[$c]['name'] ?? $c, $tiersAllowed))) ?>.</p>
                <?php endif; ?>
                <ul class="list-unstyled small text-muted mt-4 mb-0">
                    <li><i class="bi bi-truck text-warning"></i> Livraison partout au Maroc — <?= (float)setting('free_shipping_from') > 0 ? 'offerte dès ' . e(money(setting('free_shipping_from'))) : e(money(setting('shipping_fee'))) ?></li>
                    <li><i class="bi bi-credit-card text-warning"></i> Paiement à la livraison ou confirmation par WhatsApp</li>
                </ul>
            </div>
        </div>

        <?php if ($related): ?>
            <div class="mt-5 pt-4">
                <div class="gr-title"><h2>Vous aimerez aussi</h2></div>
                <div class="row g-3 g-lg-4"><?php foreach ($related as $r) echo product_card($r); ?></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script type="application/json" id="gr-products"><?= json_encode(array_map('product_public', array_merge([$p], $related)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script>window.GR_PRODUCT = <?= json_encode(product_public($p), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php page_foot(['scripts' => ['assets/js/produit.js']]);
