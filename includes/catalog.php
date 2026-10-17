<?php
declare(strict_types=1);

/** Données catalogue partagées entre accueil, catalogue, fiche produit et configurateur. */

const KIND_COLOR_GROUP = ['outfit' => 'robe_color', 'robe' => 'robe_color', 'scarf' => 'scarf_color', 'cap' => 'cap_color'];

function catalog_products(string $extraWhere = '', array $params = [], string $order = 'p.sort, p.id'): array
{
    $rows = q("SELECT p.*, c.kind, c.name AS cat_name, c.slug AS cat_slug, t.name AS tier_name
        FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN tiers t ON t.code = p.tier
        WHERE p.active = 1 " . ($extraWhere ? "AND $extraWhere " : '') . "ORDER BY $order", $params);
    if (!$rows) return [];
    $ids = array_column($rows, 'id');
    $in = implode(',', array_map('intval', $ids));
    $imgs = [];
    foreach (q("SELECT product_id, path FROM product_images WHERE product_id IN ($in) ORDER BY sort, id") as $i) $imgs[$i['product_id']][] = $i['path'];
    $vars = [];
    foreach (q("SELECT product_id, size, stock FROM product_variants WHERE product_id IN ($in)") as $v) $vars[$v['product_id']][$v['size']] = (int)$v['stock'];
    $opts = options_all();
    $sizeOrder = array_keys(array_column($opts['size'] ?? [], null, 'code'));
    foreach ($rows as &$p) {
        $p['images'] = $imgs[$p['id']] ?? [];
        $p['variants'] = $vars[$p['id']] ?? [];
        $p['stock'] = array_sum($p['variants']);
        $grp = KIND_COLOR_GROUP[$p['kind']] ?? null;
        $p['color_opts'] = [];
        if ($grp) {
            foreach (tier_list_csv($p['colors']) as $code) {
                if (isset($opts[$grp][$code])) $p['color_opts'][] = ['code' => $code, 'label' => $opts[$grp][$code]['label'], 'hex' => $opts[$grp][$code]['hex']];
            }
        }
        $p['sizes'] = [];
        if (in_array($p['kind'], ['outfit', 'robe'], true)) {
            $keys = array_keys($p['variants']);
            usort($keys, fn($a, $b) => array_search($a, $sizeOrder) <=> array_search($b, $sizeOrder));
            foreach ($keys as $k) $p['sizes'][] = ['size' => $k, 'stock' => $p['variants'][$k]];
        }
        $p['render'] = product_render_config($p);
    }
    return $rows;
}

function product_image_url(string $path): string { return url('uploads/' . ltrim($path, '/')); }

/** Visuel d'un produit : photo téléversée sinon aperçu SVG généré. */
function product_visual(array $p, string $alt = ''): string
{
    if (!empty($p['images'][0])) {
        return '<img src="' . e(product_image_url($p['images'][0])) . '" alt="' . e($alt ?: $p['name']) . '" loading="lazy">';
    }
    return '<div class="gr-svgwrap" data-outfit=\'' . e(json_encode($p['render'], JSON_UNESCAPED_UNICODE)) . '\' aria-label="' . e($p['name']) . '"></div>';
}

/** Représentation JSON publique d'un produit (pour le JS). */
function product_public(array $p): array
{
    return [
        'id' => (int)$p['id'], 'slug' => $p['slug'], 'name' => $p['name'], 'price' => (float)$p['price'], 'kind' => $p['kind'],
        'tier' => $p['tier'], 'tier_name' => $p['tier_name'], 'colors' => $p['color_opts'], 'sizes' => $p['sizes'],
        'stock' => (int)$p['stock'], 'render' => $p['render'],
    ];
}

function product_card(array $p): string
{
    $kind = $p['kind'];
    $swatches = '';
    foreach (array_slice($p['color_opts'], 0, 6) as $c) {
        $swatches .= '<span class="gr-swatch" style="--c:' . e($c['hex']) . '" title="' . e($c['label']) . '"></span>';
    }
    $codes = implode(' ', array_column($p['color_opts'], 'code'));
    $sizes = implode(' ', array_map(fn($s) => $s['size'], array_filter($p['sizes'], fn($s) => $s['stock'] > 0)));
    $out = (int)$p['stock'] <= 0;
    $custom = $kind !== 'accessory';
    ob_start(); ?>
    <article class="col-6 col-lg-4 col-xl-3 gr-pcol" data-name="<?= e(mb_strtolower($p['name'])) ?>" data-cat="<?= e($p['cat_slug']) ?>"
             data-tier="<?= e($p['tier']) ?>" data-colors="<?= e($codes) ?>" data-sizes="<?= e($sizes) ?>"
             data-price="<?= e($p['price']) ?>" data-stock="<?= (int)$p['stock'] ?>" data-id="<?= (int)$p['id'] ?>" data-order="<?= (int)$p['sort'] ?>">
        <div class="gr-card h-100">
            <div class="gr-card__media">
                <a href="<?= e(url('produit.php?slug=' . $p['slug'])) ?>" class="gr-card__link" aria-label="<?= e($p['name']) ?>"><?= product_visual($p) ?></a>
                <span class="gr-tier gr-tier--<?= e($p['tier']) ?>"><?= e($p['tier_name']) ?></span>
                <button type="button" class="gr-fav" data-fav="<?= (int)$p['id'] ?>" aria-label="Ajouter aux favoris" aria-pressed="false"><i class="bi bi-heart"></i></button>
                <?php if ($out): ?><span class="gr-oos">Épuisé</span><?php endif; ?>
            </div>
            <div class="gr-card__body">
                <h3 class="gr-card__title"><a href="<?= e(url('produit.php?slug=' . $p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="gr-price"><?= e(money($p['price'])) ?></span>
                    <span class="gr-swatches"><?= $swatches ?></span>
                </div>
                <div class="gr-card__actions">
                    <?php if ($custom): ?>
                        <a class="btn btn-gr-outline btn-sm" href="<?= e(url('configurateur.php?product=' . (int)$p['id'])) ?>"><i class="bi bi-brush"></i> <?= e(t('btn.customize')) ?></a>
                    <?php else: ?>
                        <a class="btn btn-gr-outline btn-sm" href="<?= e(url('produit.php?slug=' . $p['slug'])) ?>">Voir le détail</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-gr btn-sm" data-quickadd="<?= (int)$p['id'] ?>" <?= $out ? 'disabled' : '' ?> aria-label="<?= e(t('btn.add_cart')) ?> : <?= e($p['name']) ?>">
                        <i class="bi bi-bag-plus"></i><span class="d-none d-sm-inline"> <?= e(t('btn.add_cart')) ?></span>
                    </button>
                </div>
            </div>
        </div>
    </article>
    <?php return ob_get_clean();
}
