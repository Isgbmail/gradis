<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();

$id = (int)($_GET['id'] ?? 0);
$p = $id ? q1('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('Produit introuvable.', 'danger'); redirect(url('admin/products.php')); }
$cats = categories_all(); $tiers = tiers_all(false); $opts = options_all(false);
$sizes = array_column($opts['size'] ?? [], 'code');
$kindOf = fn(int $cid) => (string)qv('SELECT kind FROM categories WHERE id = ?', [$cid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action', 'save');
    try {
        if ($act === 'delimg' && $p) {
            $img = q1('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [post_int('img'), $id]);
            if ($img) { delete_upload($img['path']); ex('DELETE FROM product_images WHERE id = ?', [$img['id']]); flash('Image supprimée.'); }
            redirect(url('admin/product.php?id=' . $id));
        }
        $name = mb_substr(post('name'), 0, 160);
        $cid = post_int('category_id'); $kind = $kindOf($cid);
        $tier = post('tier');
        $price = post_num('price');
        if ($name === '') throw new RuntimeException('Le nom est obligatoire.');
        if (!$kind) throw new RuntimeException('Catégorie invalide.');
        if (!isset($tiers[$tier])) throw new RuntimeException('Gamme invalide.');
        if ($price < 0 || $price > 100000) throw new RuntimeException('Prix invalide.');
        $grp = KIND_COLOR_GROUP[$kind] ?? null;
        $colors = $grp ? array_values(array_intersect((array)($_POST['colors'] ?? []), array_keys($opts[$grp] ?? []))) : [];
        $pick = function (string $k, string $g) use ($opts) { $v = post($k); return isset($opts[$g][$v]) ? $v : null; };
        $allowedTiers = array_values(array_intersect((array)($_POST['tiers_allowed'] ?? []), array_keys($tiers)));
        $data = [
            'category_id' => $cid, 'tier' => $tier, 'name' => $name, 'description' => mb_substr(post('description'), 0, 2000), 'price' => $price,
            'active' => isset($_POST['active']) ? 1 : 0, 'colors' => implode(',', $colors),
            'def_robe' => $pick('def_robe', 'robe_color'), 'def_scarf' => $pick('def_scarf', 'scarf_color'),
            'def_trim' => $pick('def_trim', 'trim_color'), 'def_cap' => $pick('def_cap', 'cap_color'),
            'tiers_allowed' => $kind === 'accessory' ? implode(',', $allowedTiers) : null, 'sort' => post_int('sort'),
        ];
        $pdo = db(); $pdo->beginTransaction();
        try {
            if ($p) { update('products', $id, $data); }
            else {
                $base = slugify($name); $slug = $base; $n = 2;
                while (qv('SELECT 1 FROM products WHERE slug = ?', [$slug])) $slug = $base . '-' . $n++;
                $id = insert('products', $data + ['slug' => $slug, 'created_at' => now()]);
            }
            // Stock : une ligne par taille (toges / tenues) ou une ligne unique « * »
            if (in_array($kind, ['outfit', 'robe'], true)) {
                ex("DELETE FROM product_variants WHERE product_id = ? AND size = '*'", [$id]);
                foreach ($sizes as $s) {
                    $stock = max(0, (int)($_POST['stock'][$s] ?? 0));
                    if (qv('SELECT 1 FROM product_variants WHERE product_id = ? AND size = ?', [$id, $s])) ex('UPDATE product_variants SET stock = ? WHERE product_id = ? AND size = ?', [$stock, $id, $s]);
                    else insert('product_variants', ['product_id' => $id, 'size' => $s, 'stock' => $stock]);
                }
            } else {
                ex("DELETE FROM product_variants WHERE product_id = ? AND size <> '*'", [$id]);
                $stock = max(0, post_int('stock_single'));
                if (qv("SELECT 1 FROM product_variants WHERE product_id = ? AND size = '*'", [$id])) ex("UPDATE product_variants SET stock = ? WHERE product_id = ? AND size = '*'", [$stock, $id]);
                else insert('product_variants', ['product_id' => $id, 'size' => '*', 'stock' => $stock]);
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        // Images (hors transaction : fichiers)
        $files = $_FILES['images'] ?? null; $added = 0;
        if ($files && is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                $path = save_image(['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], 'products');
                if ($path) { insert('product_images', ['product_id' => $id, 'path' => $path, 'sort' => (int)qv('SELECT COALESCE(MAX(sort),0)+1 FROM product_images WHERE product_id = ?', [$id])]); $added++; }
            }
        }
        flash('Produit enregistré' . ($added ? " ($added image" . ($added > 1 ? 's' : '') . ' ajoutée' . ($added > 1 ? 's' : '') . ')' : '') . '.');
        redirect(url('admin/product.php?id=' . $id));
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'danger');
        $p = array_merge($p ?? [], $_POST, ['id' => $id]);
    }
}

$cur = $p ?: ['category_id' => $cats[0]['id'] ?? 0, 'tier' => 'premium', 'name' => '', 'description' => '', 'price' => '', 'active' => 1, 'colors' => '', 'def_robe' => 'noir', 'def_scarf' => 'noir', 'def_trim' => 'or', 'def_cap' => 'noir', 'tiers_allowed' => '', 'sort' => 0];
$curKind = $kindOf((int)$cur['category_id']);
$imgs = $id ? q('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]) : [];
$stock = $id ? array_column(q('SELECT size, stock FROM product_variants WHERE product_id = ?', [$id]), 'stock', 'size') : [];
$selColors = tier_list_csv($cur['colors'] ?? '');
$selTiers = tier_list_csv($cur['tiers_allowed'] ?? '');
$catKinds = array_column($cats, 'kind', 'id');
admin_head($id ? 'Modifier le produit' : 'Nouveau produit', 'products');

$select = function (string $name, array $list, ?string $sel, bool $none = true) {
    $o = '<select class="form-select" name="' . $name . '" id="f_' . $name . '">' . ($none ? '<option value="">—</option>' : '');
    foreach ($list as $c => $l) $o .= '<option value="' . e($c) . '"' . ((string)$sel === (string)$c ? ' selected' : '') . '>' . e($l['label']) . '</option>';
    return $o . '</select>';
};
?>
<p><a href="<?= e(url('admin/products.php')) ?>"><i class="bi bi-arrow-left"></i> Produits</a></p>
<form method="post" enctype="multipart/form-data" class="row g-3" id="pform">
<?= csrf_field() ?>
<div class="col-lg-8 d-flex flex-column gap-3">
    <div class="adm-card"><h2>Informations</h2>
        <div class="row g-3">
            <div class="col-12"><label class="form-label" for="name">Nom du produit</label><input class="form-control" id="name" name="name" required maxlength="160" value="<?= e($cur['name']) ?>"></div>
            <div class="col-md-6"><label class="form-label" for="category_id">Catégorie</label>
                <select class="form-select" id="category_id" name="category_id"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" data-kind="<?= e($c['kind']) ?>" <?= (int)$cur['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 col-6"><label class="form-label" for="tier">Gamme</label>
                <select class="form-select" id="tier" name="tier"><?php foreach ($tiers as $c => $t): ?><option value="<?= e($c) ?>" <?= $cur['tier'] === $c ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 col-6"><label class="form-label" for="price">Prix (DH)</label><input class="form-control" id="price" name="price" type="number" step="0.01" min="0" required value="<?= e($cur['price']) ?>"></div>
            <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="2000"><?= e($cur['description']) ?></textarea></div>
        </div>
    </div>
    <div class="adm-card" data-kinds="outfit robe scarf cap"><h2>Couleurs proposées au client</h2>
        <?php foreach (['robe_color' => ['outfit robe', 'Toge'], 'scarf_color' => ['scarf', 'Écharpe'], 'cap_color' => ['cap', 'Toque']] as $g => [$kinds, $lab]): ?>
        <div class="mb-2" data-kinds="<?= $kinds ?>"><div class="small fw-semibold mb-1"><?= $lab ?></div>
            <?php foreach ($opts[$g] ?? [] as $code => $o): ?>
                <label class="me-3 d-inline-flex align-items-center gap-1"><input type="checkbox" class="form-check-input" name="colors[]" value="<?= e($code) ?>" <?= in_array($code, $selColors, true) ? 'checked' : '' ?>><span class="adm-swatch" style="background:<?= e($o['hex']) ?>"></span> <?= e($o['label']) ?></label>
            <?php endforeach; ?></div>
        <?php endforeach; ?>
        <hr><div class="small fw-semibold mb-2">Aperçu par défaut (couleurs utilisées pour l'illustration si aucune photo)</div>
        <div class="row g-2">
            <div class="col-6 col-md-3" data-kinds="outfit robe"><label class="form-label" for="f_def_robe">Toge</label><?= $select('def_robe', $opts['robe_color'] ?? [], $cur['def_robe'] ?? null) ?></div>
            <div class="col-6 col-md-3" data-kinds="outfit scarf"><label class="form-label" for="f_def_scarf">Écharpe</label><?= $select('def_scarf', $opts['scarf_color'] ?? [], $cur['def_scarf'] ?? null) ?></div>
            <div class="col-6 col-md-3"><label class="form-label" for="f_def_trim"><?= e(setting('label_trim', 'Billets décoratifs')) ?></label><?= $select('def_trim', $opts['trim_color'] ?? [], $cur['def_trim'] ?? null) ?></div>
            <div class="col-6 col-md-3" data-kinds="outfit cap"><label class="form-label" for="f_def_cap">Toque</label><?= $select('def_cap', $opts['cap_color'] ?? [], $cur['def_cap'] ?? null) ?></div>
        </div>
    </div>
    <div class="adm-card" data-kinds="accessory"><h2>Compatibilité (accessoire)</h2>
        <p class="small text-muted">Gammes avec lesquelles cet accessoire peut être ajouté dans le configurateur. Aucune case cochée = toutes les gammes.</p>
        <?php foreach ($tiers as $c => $t): ?><label class="me-3"><input type="checkbox" class="form-check-input" name="tiers_allowed[]" value="<?= e($c) ?>" <?= in_array($c, $selTiers, true) ? 'checked' : '' ?>> <?= e($t['name']) ?></label><?php endforeach; ?>
    </div>
    <div class="adm-card"><h2>Stock</h2>
        <div data-kinds="outfit robe"><div class="row g-2"><?php foreach ($sizes as $s): ?><div class="col-4 col-md-2"><label class="form-label" for="st_<?= e($s) ?>"><?= e($s) ?></label><input class="form-control" id="st_<?= e($s) ?>" type="number" min="0" name="stock[<?= e($s) ?>]" value="<?= (int)($stock[$s] ?? 0) ?>"></div><?php endforeach; ?></div></div>
        <div data-kinds="scarf cap accessory" style="max-width:200px"><label class="form-label" for="stock_single">Quantité en stock</label><input class="form-control" id="stock_single" type="number" min="0" name="stock_single" value="<?= (int)($stock['*'] ?? array_sum($stock)) ?>"></div>
    </div>
</div>
<div class="col-lg-4 d-flex flex-column gap-3">
    <div class="adm-card"><h2>Publication</h2>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="active" name="active" <?= !empty($cur['active']) ? 'checked' : '' ?>><label class="form-check-label" for="active">Visible sur le site</label></div>
        <label class="form-label" for="sort">Ordre d'affichage</label><input class="form-control mb-3" id="sort" name="sort" type="number" value="<?= (int)($cur['sort'] ?? 0) ?>">
        <button class="btn btn-gr w-100">Enregistrer</button>
        <?php if ($id): ?><a class="btn btn-outline-dark w-100 mt-2" target="_blank" href="<?= e(url('produit.php?slug=' . ($p['slug'] ?? ''))) ?>">Voir la fiche</a><?php endif; ?>
    </div>
    <div class="adm-card"><h2>Photos</h2>
        <p class="small text-muted">JPG, PNG ou WebP, 5 Mo max. Sans photo, une illustration générée est affichée.</p>
        <?php if ($imgs): ?><div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($imgs as $im): ?><div class="position-relative"><div class="adm-thumb" style="width:80px;height:100px"><img src="<?= e(product_image_url($im['path'])) ?>" alt=""></div>
                <button class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 px-1" formaction="" name="action" value="delimg" onclick="this.form.querySelector('[name=img]').value=<?= (int)$im['id'] ?>; return confirm('Supprimer cette image ?')" aria-label="Supprimer l'image"><i class="bi bi-x"></i></button></div><?php endforeach; ?></div>
            <input type="hidden" name="img" value=""><?php endif; ?>
        <input class="form-control" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple aria-label="Ajouter des photos">
    </div>
</div>
</form>
<script>
(function(){
  var kinds = <?= json_encode($catKinds) ?>, sel = document.getElementById('category_id');
  function apply(){ var k = kinds[sel.value]; document.querySelectorAll('[data-kinds]').forEach(function(el){ var show = el.getAttribute('data-kinds').split(' ').indexOf(k) > -1; el.hidden = !show; el.querySelectorAll('input,select').forEach(function(i){ i.disabled = !show; }); }); }
  sel.addEventListener('change', apply); apply();
})();
</script>
<?php admin_foot();
