<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();

const OPT_GROUPS = [
    'robe_color' => 'Couleurs de toge', 'scarf_color' => "Couleurs d'écharpe", 'trim_color' => 'Billets / éléments décoratifs',
    'cap_color' => 'Couleurs de toque', 'cap_style' => 'Styles de toque (gland)', 'size' => 'Tailles',
    'font' => 'Polices de personnalisation', 'text_color' => 'Couleurs de fil (texte)',
];
$tiers = tiers_all(false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'tier') {
        $code = post('code');
        try {
        if (isset($tiers[$code]) && post('name') !== '') {
            $pr = post_money('price_robe', 'Prix de la toge'); $ps = post_money('price_scarf', "Prix de l'écharpe"); $pc = post_money('price_cap', 'Prix de la toque');
            ex('UPDATE tiers SET name=?, description=?, materials=?, price_robe=?, price_scarf=?, price_cap=?, text_scarf=?, text_cap=?, active=? WHERE code=?',
                [mb_substr(post('name'), 0, 80), mb_substr(post('description'), 0, 500), mb_substr(post('materials'), 0, 500),
                 $pr, $ps, $pc,
                 isset($_POST['text_scarf']) ? 1 : 0, isset($_POST['text_cap']) ? 1 : 0, isset($_POST['active']) ? 1 : 0, $code]);
            flash('Gamme « ' . post('name') . ' » enregistrée.');
        } else flash('Gamme invalide.', 'danger');
        } catch (RuntimeException $e) { flash($e->getMessage(), 'danger'); }
    } elseif ($act === 'opt_save' || $act === 'opt_add') {
        $grp = post('grp');
        $label = mb_substr(post('label'), 0, 120);
        $hex = post('hex');
        if (!isset(OPT_GROUPS[$grp]) || $label === '') { flash('Libellé obligatoire.', 'danger'); redirect(url('admin/options.php#g-' . $grp)); }
        if ($hex !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) { flash('Couleur invalide.', 'danger'); redirect(url('admin/options.php#g-' . $grp)); }
        try { $extra = post_money('price_extra', 'Supplément'); } catch (RuntimeException $e) { flash($e->getMessage(), 'danger'); redirect(url('admin/options.php#g-' . $grp)); }
        if ($grp === 'size' && mb_strlen($label) > 10) { flash('Une taille ne dépasse pas 10 caractères (ex. XL).', 'danger'); redirect(url('admin/options.php#g-' . $grp)); }
        $tl = array_values(array_intersect((array)($_POST['tiers'] ?? []), array_keys($tiers)));
        $d = ['label' => $label, 'hex' => $hex ?: null, 'price_extra' => $extra, 'tiers' => implode(',', $tl), 'sort' => post_clamp('sort', 0, 9999), 'active' => isset($_POST['active']) ? 1 : 0];
        if ($grp === 'font') $d['font_css'] = mb_substr(post('font_css'), 0, 200) ?: null;
        if ($act === 'opt_save') { update('options', post_int('id'), $d); flash('Option enregistrée.'); }
        else {
            $code = slugify($label);
            if ($grp === 'size') $code = strtoupper($label);
            if (qv('SELECT 1 FROM options WHERE grp = ? AND code = ?', [$grp, $code])) flash('Cette option existe déjà.', 'danger');
            else { insert('options', $d + ['grp' => $grp, 'code' => $code]); flash('Option ajoutée.'); }
        }
        redirect(url('admin/options.php#g-' . $grp));
    } elseif ($act === 'opt_del') {
        $o = q1('SELECT * FROM options WHERE id = ?', [post_int('id')]);
        if ($o) {
            $used = (int)qv('SELECT COUNT(*) FROM products WHERE colors LIKE ? OR def_robe = ? OR def_scarf = ? OR def_cap = ? OR def_trim = ?', ['%' . $o['code'] . '%', $o['code'], $o['code'], $o['code'], $o['code']]);
            if ($used) flash("Option utilisée par $used produit(s) : désactivez-la plutôt que de la supprimer.", 'warning');
            else { ex('DELETE FROM options WHERE id = ?', [$o['id']]); flash('Option supprimée.'); }
            redirect(url('admin/options.php#g-' . $o['grp']));
        }
    }
    redirect(url('admin/options.php'));
}
$opts = options_all(false);
$tiers = tiers_all(false);
admin_head('Gammes & options', 'options'); ?>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Les prix ci-dessous alimentent le configurateur : <strong>prix d'une tenue = toge + écharpe + toque de la gamme + suppléments d'options + accessoires + textes brodés</strong>. Ils sont recalculés par le serveur à chaque commande. Les valeurs actuelles peuvent être des prix de démonstration.</div>

<h2 class="h4 mb-3">Gammes</h2>
<div class="row g-3 mb-4">
<?php foreach ($tiers as $c => $t): ?>
<div class="col-xl-4"><form method="post" class="adm-card h-100"><?= csrf_field() ?><input type="hidden" name="action" value="tier"><input type="hidden" name="code" value="<?= e($c) ?>">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="mb-0"><?= e($t['name']) ?></h2><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="active" id="ta_<?= e($c) ?>" <?= $t['active'] ? 'checked' : '' ?>><label class="form-check-label small" for="ta_<?= e($c) ?>">Active</label></div></div>
    <label class="form-label" for="tn_<?= e($c) ?>">Nom affiché</label><input class="form-control mb-2" id="tn_<?= e($c) ?>" name="name" required value="<?= e($t['name']) ?>">
    <label class="form-label" for="td_<?= e($c) ?>">Description</label><textarea class="form-control mb-2" id="td_<?= e($c) ?>" name="description" rows="2"><?= e($t['description']) ?></textarea>
    <label class="form-label" for="tm_<?= e($c) ?>">Matériaux / finitions</label><textarea class="form-control mb-2" id="tm_<?= e($c) ?>" name="materials" rows="2"><?= e($t['materials']) ?></textarea>
    <div class="row g-2 mb-2">
        <div class="col-4"><label class="form-label" for="pr_<?= e($c) ?>">Toge (DH)</label><input class="form-control" id="pr_<?= e($c) ?>" type="number" step="0.01" min="0" name="price_robe" value="<?= e($t['price_robe']) ?>"></div>
        <div class="col-4"><label class="form-label" for="ps_<?= e($c) ?>">Écharpe</label><input class="form-control" id="ps_<?= e($c) ?>" type="number" step="0.01" min="0" name="price_scarf" value="<?= e($t['price_scarf']) ?>"></div>
        <div class="col-4"><label class="form-label" for="pc_<?= e($c) ?>">Toque</label><input class="form-control" id="pc_<?= e($c) ?>" type="number" step="0.01" min="0" name="price_cap" value="<?= e($t['price_cap']) ?>"></div>
    </div>
    <div class="mb-3 small"><label class="me-3"><input class="form-check-input" type="checkbox" name="text_scarf" <?= $t['text_scarf'] ? 'checked' : '' ?>> Texte sur écharpe</label><label><input class="form-check-input" type="checkbox" name="text_cap" <?= $t['text_cap'] ? 'checked' : '' ?>> Texte sur toque</label></div>
    <button class="btn btn-gr w-100">Enregistrer la gamme</button>
</form></div>
<?php endforeach; ?>
</div>

<h2 class="h4 mb-3">Options du configurateur</h2>
<p class="text-muted small">« Gammes » = gammes avec lesquelles l'option est disponible (les autres la voient grisée avec une explication). « Supplément » = prix ajouté en DH. Pour renommer « billets », modifiez le libellé dans Réglages du site → Personnalisation.</p>
<?php foreach (OPT_GROUPS as $g => $gl): $list = array_values($opts[$g] ?? []); ?>
<div class="adm-card mb-3" id="g-<?= e($g) ?>"><h2><?= e($gl) ?></h2>
    <div class="table-responsive"><table class="table adm-table"><thead><tr><th>Libellé</th><?php if ($g !== 'size' && $g !== 'cap_style' && $g !== 'font'): ?><th>Couleur</th><?php endif; ?><?php if ($g === 'font'): ?><th>CSS font-family</th><?php endif; ?><th>Suppl. (DH)</th><th>Gammes</th><th>Ordre</th><th>Actif</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $o): $tl = tier_list_csv($o['tiers']); $fid = 'f' . $o['id']; ?>
    <tr><td><form method="post" id="<?= $fid ?>"><?= csrf_field() ?><input type="hidden" name="action" value="opt_save"><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="grp" value="<?= e($g) ?>"></form><input form="<?= $fid ?>" class="form-control form-control-sm" name="label" value="<?= e($o['label']) ?>" required aria-label="Libellé"></td>
        <?php if ($g !== 'size' && $g !== 'cap_style' && $g !== 'font'): ?><td><input form="<?= $fid ?>" type="color" class="form-control form-control-color" name="hex" value="<?= e($o['hex'] ?: '#000000') ?>" aria-label="Couleur"></td><?php endif; ?>
        <?php if ($g === 'font'): ?><td><input form="<?= $fid ?>" class="form-control form-control-sm" name="font_css" value="<?= e($o['font_css']) ?>" aria-label="Police"></td><?php endif; ?>
        <td style="width:100px"><input form="<?= $fid ?>" class="form-control form-control-sm" type="number" step="0.01" min="0" max="100000" name="price_extra" value="<?= e($o['price_extra']) ?>" aria-label="Supplément"></td>
        <td class="text-nowrap"><?php foreach ($tiers as $c => $t): ?><label class="me-2 small"><input form="<?= $fid ?>" type="checkbox" class="form-check-input" name="tiers[]" value="<?= e($c) ?>" <?= in_array($c, $tl, true) ? 'checked' : '' ?>> <?= e(mb_substr($t['name'], 0, 4)) ?>.</label><?php endforeach; ?></td>
        <td style="width:80px"><input form="<?= $fid ?>" class="form-control form-control-sm" type="number" min="0" max="9999" name="sort" value="<?= (int)$o['sort'] ?>" aria-label="Ordre"></td>
        <td><input form="<?= $fid ?>" type="checkbox" class="form-check-input" name="active" <?= $o['active'] ? 'checked' : '' ?> aria-label="Actif"></td>
        <td class="text-end text-nowrap"><button form="<?= $fid ?>" class="btn btn-sm btn-outline-dark">Enregistrer</button>
            <form method="post" class="d-inline" data-confirm="Supprimer « <?= e($o['label']) ?> » ?"><?= csrf_field() ?><input type="hidden" name="action" value="opt_del"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="btn btn-sm btn-outline-danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; ?>
    <tr class="table-light"><td><form method="post" id="add_<?= e($g) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="opt_add"><input type="hidden" name="grp" value="<?= e($g) ?>"><input type="hidden" name="active" value="1"><input type="hidden" name="tiers[]" value="economie"><input type="hidden" name="tiers[]" value="premium"><input type="hidden" name="tiers[]" value="prestige"></form><input form="add_<?= e($g) ?>" class="form-control form-control-sm" name="label" placeholder="Nouveau…" required aria-label="Nouveau libellé"></td>
        <?php if ($g !== 'size' && $g !== 'cap_style' && $g !== 'font'): ?><td><input form="add_<?= e($g) ?>" type="color" class="form-control form-control-color" name="hex" value="#888888" aria-label="Couleur"></td><?php endif; ?>
        <?php if ($g === 'font'): ?><td><input form="add_<?= e($g) ?>" class="form-control form-control-sm" name="font_css" placeholder="'Cinzel', serif"></td><?php endif; ?>
        <td><input form="add_<?= e($g) ?>" class="form-control form-control-sm" type="number" step="0.01" min="0" max="100000" name="price_extra" value="0" aria-label="Supplément"></td>
        <td class="small text-muted">toutes</td><td><input form="add_<?= e($g) ?>" class="form-control form-control-sm" type="number" min="0" max="9999" name="sort" value="99" aria-label="Ordre"></td><td></td>
        <td class="text-end"><button form="add_<?= e($g) ?>" class="btn btn-sm btn-gr">Ajouter</button></td></tr>
    </tbody></table></div>
    <?php if ($g === 'font'): ?><div class="form-text">Les polices utilisables sont celles déjà chargées par le site (Cormorant Garamond, Montserrat, Cinzel, Great Vibes) ou des polices système.</div><?php endif; ?>
</div>
<?php endforeach;
admin_foot();
