<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = post_int('id');
    try {
        if ($act === 'upload') {
            $files = $_FILES['images'] ?? null; $n = 0;
            if ($files && is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    $path = save_image(['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], 'hero');
                    if ($path) { insert('hero_slides', ['path' => $path, 'caption' => mb_substr(post('caption'), 0, 120), 'sort' => (int)qv('SELECT COALESCE(MAX(sort),0)+1 FROM hero_slides'), 'active' => 1]); $n++; }
                }
            }
            flash($n ? "$n image" . ($n > 1 ? 's ajoutées' : ' ajoutée') . ' au bandeau.' : 'Aucune image sélectionnée.', $n ? 'success' : 'warning');
        } elseif ($act === 'save') {
            ex('UPDATE hero_slides SET caption = ?, sort = ?, active = ? WHERE id = ?', [mb_substr(post('caption'), 0, 120), post_clamp('sort', 0, 9999), isset($_POST['active']) ? 1 : 0, $id]);
            flash('Diapositive enregistrée.');
        } elseif ($act === 'del') {
            $r = q1('SELECT path FROM hero_slides WHERE id = ?', [$id]);
            if ($r) { delete_upload($r['path']); ex('DELETE FROM hero_slides WHERE id = ?', [$id]); flash('Image supprimée.'); }
        }
    } catch (RuntimeException $e) { flash($e->getMessage(), 'danger'); }
    redirect(url('admin/hero.php'));
}
$rows = q('SELECT * FROM hero_slides ORDER BY sort, id');
admin_head("Bandeau d'accueil", 'hero'); ?>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Les images défilent en fond du bandeau d'accueil, sous un voile bleu nuit qui garde le texte lisible. Sans image, le site affiche des illustrations de tenues.
    <strong>Conseil :</strong> photos horizontales (paysage) d'au moins 1600 × 900 px, avec le sujet plutôt à droite (le texte est à gauche). JPG, PNG ou WebP, 5 Mo max chacune.</div>

<form method="post" enctype="multipart/form-data" class="adm-card mb-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload"><h2>Ajouter des images</h2>
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label" for="im">Images (plusieurs possibles)</label><input class="form-control" id="im" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></div>
        <div class="col-md-4"><label class="form-label" for="cp">Légende (facultatif)</label><input class="form-control" id="cp" name="caption" maxlength="120" placeholder="Ex. Collection Prestige"></div>
        <div class="col-md-2 d-grid"><button class="btn btn-gr">Ajouter</button></div>
    </div>
</form>

<div class="row g-3">
<?php if (!$rows): ?><div class="col-12"><div class="adm-card text-muted">Aucune image : le site affiche les illustrations par défaut.</div></div><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <div class="col-md-6 col-xl-4"><form method="post" class="adm-card h-100"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <div class="ratio ratio-16x9 mb-2 rounded overflow-hidden border <?= $r['active'] ? '' : 'opacity-50' ?>"><img src="<?= e(url('uploads/' . $r['path'])) ?>" alt="" style="object-fit:cover"></div>
        <label class="form-label small" for="c<?= $r['id'] ?>">Légende</label><input class="form-control form-control-sm mb-2" id="c<?= $r['id'] ?>" name="caption" value="<?= e($r['caption']) ?>" maxlength="120">
        <div class="d-flex align-items-center gap-3 mb-2">
            <div style="width:90px"><label class="form-label small" for="s<?= $r['id'] ?>">Ordre</label><input class="form-control form-control-sm" id="s<?= $r['id'] ?>" type="number" min="0" max="9999" name="sort" value="<?= (int)$r['sort'] ?>"></div>
            <label class="mt-3"><input type="checkbox" class="form-check-input" name="active" <?= $r['active'] ? 'checked' : '' ?>> Affichée</label>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-sm btn-gr flex-grow-1" name="action" value="save">Enregistrer</button>
            <button class="btn btn-sm btn-outline-danger" name="action" value="del" data-confirm="Supprimer cette image ?" aria-label="Supprimer"><i class="bi bi-trash"></i></button></div>
    </form></div>
<?php endforeach; ?>
</div>
<p class="mt-3"><a class="btn btn-outline-dark" target="_blank" rel="noopener" href="<?= e(url('index.php')) ?>">Voir le résultat sur le site</a></p>
<?php admin_foot();
