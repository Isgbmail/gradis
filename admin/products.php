<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_int('id');
    if (post('action') === 'toggle' && $id) { ex('UPDATE products SET active = 1 - active WHERE id = ?', [$id]); flash('Visibilité modifiée.'); }
    if (post('action') === 'delete' && $id) {
        foreach (q('SELECT path FROM product_images WHERE product_id = ?', [$id]) as $i) delete_upload($i['path']);
        ex('DELETE FROM product_images WHERE product_id = ?', [$id]);
        ex('DELETE FROM product_variants WHERE product_id = ?', [$id]);
        ex('DELETE FROM products WHERE id = ?', [$id]);
        flash('Produit supprimé. Les commandes passées conservent leur instantané.');
    }
    redirect(url('admin/products.php' . (isset($_GET['cat']) ? '?cat=' . (int)$_GET['cat'] : '')));
}
$cat = (int)($_GET['cat'] ?? 0); $qq = trim((string)($_GET['q'] ?? ''));
$w = ['1=1']; $p = [];
if ($cat) { $w[] = 'p.category_id = ?'; $p[] = $cat; }
if ($qq !== '') { $w[] = 'p.name LIKE ?'; $p[] = "%$qq%"; }
$rows = q('SELECT p.*, c.name cat, c.kind, t.name tier_name, (SELECT path FROM product_images WHERE product_id = p.id ORDER BY sort, id LIMIT 1) img
    FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN tiers t ON t.code = p.tier WHERE ' . implode(' AND ', $w) . ' ORDER BY c.sort, p.sort, p.id', $p);
admin_head('Produits', 'products'); ?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <form class="d-flex gap-2" method="get">
        <select class="form-select" name="cat" aria-label="Catégorie" data-submit-on-change><option value="0">Toutes les catégories</option><?php foreach (categories_all() as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <input class="form-control" name="q" value="<?= e($qq) ?>" placeholder="Rechercher" aria-label="Rechercher"><button class="btn btn-outline-dark">OK</button>
    </form>
    <a class="btn btn-gr" href="<?= e(url('admin/product.php')) ?>"><i class="bi bi-plus-lg"></i> Nouveau produit</a>
</div>
<div class="adm-card p-0">
<?php if (!$rows): ?><p class="p-4 mb-0 text-muted">Aucun produit. Cliquez sur « Nouveau produit ».</p><?php else: ?>
<div class="table-responsive"><table class="table adm-table"><thead><tr><th></th><th>Produit</th><th>Catégorie</th><th>Gamme</th><th class="text-end">Prix</th><th>Visible</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $r['images'] = $r['img'] ? [$r['img']] : []; $r['render'] = product_render_config($r + ['kind' => $r['kind']]); ?>
<tr class="<?= $r['active'] ? '' : 'text-muted' ?>">
    <td><div class="adm-thumb"><?= product_visual($r) ?></div></td>
    <td class="fw-semibold"><a href="<?= e(url('admin/product.php?id=' . $r['id'])) ?>"><?= e($r['name']) ?></a></td>
    <td><?= e($r['cat']) ?></td><td><?= e($r['tier_name']) ?></td>
    <td class="text-end text-nowrap"><?= e(money($r['price'])) ?></td>
    <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn btn-sm <?= $r['active'] ? 'btn-success' : 'btn-outline-secondary' ?>"><?= $r['active'] ? 'Actif' : 'Masqué' ?></button></form></td>
    <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/product.php?id=' . $r['id'])) ?>">Modifier</a>
        <form method="post" class="d-inline" data-confirm="Supprimer définitivement « <?= e($r['name']) ?> » ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php admin_foot();
