<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();
$tab = in_array($_GET['tab'] ?? '', ['reviews', 'faqs', 'categories'], true) ? $_GET['tab'] : 'reviews';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = post_int('id');
    $back = url('admin/content.php?tab=' . $tab);
    if ($tab === 'reviews') {
        $author = mb_substr(post('author'), 0, 120); $body = mb_substr(post('body'), 0, 800);
        $d = ['author' => $author, 'city' => mb_substr(post('city'), 0, 100), 'rating' => post_clamp('rating', 1, 5, 5), 'body' => $body,
              'active' => isset($_POST['active']) ? 1 : 0, 'is_demo' => isset($_POST['is_demo']) ? 1 : 0];
        if ($act === 'del') { ex('DELETE FROM reviews WHERE id = ?', [$id]); flash('Avis supprimé.'); }
        elseif ($author === '' || $body === '') flash('Auteur et texte obligatoires.', 'danger');
        elseif ($act === 'save') { update('reviews', $id, $d); flash('Avis enregistré.'); }
        elseif ($act === 'add') { insert('reviews', $d + ['created_at' => now()]); flash('Avis ajouté.'); }
    } elseif ($tab === 'faqs') {
        $d = ['question' => mb_substr(post('question'), 0, 255), 'answer' => mb_substr(post('answer'), 0, 2000), 'sort' => post_clamp('sort', 0, 9999), 'active' => isset($_POST['active']) ? 1 : 0];
        if ($act === 'del') { ex('DELETE FROM faqs WHERE id = ?', [$id]); flash('Question supprimée.'); }
        elseif ($d['question'] === '' || $d['answer'] === '') flash('Question et réponse obligatoires.', 'danger');
        elseif ($act === 'save') { update('faqs', $id, $d); flash('Question enregistrée.'); }
        elseif ($act === 'add') { insert('faqs', $d); flash('Question ajoutée.'); }
    } else {
        $name = mb_substr(post('name'), 0, 120);
        if ($act === 'del') {
            if ((int)qv('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id])) flash('Catégorie utilisée par des produits : déplacez-les d\'abord.', 'warning');
            else { ex('DELETE FROM categories WHERE id = ?', [$id]); flash('Catégorie supprimée.'); }
        } elseif ($name === '') flash('Nom obligatoire.', 'danger');
        elseif ($act === 'save') { ex('UPDATE categories SET name = ?, sort = ? WHERE id = ?', [$name, post_clamp('sort', 0, 9999), $id]); flash('Catégorie enregistrée.'); }
        elseif ($act === 'add') {
            $kind = post('kind');
            if (!in_array($kind, ['outfit', 'robe', 'scarf', 'cap', 'accessory'], true)) flash('Type invalide.', 'danger');
            else { $slug = slugify($name); $n = 2; while (qv('SELECT 1 FROM categories WHERE slug = ?', [$slug])) $slug = slugify($name) . '-' . $n++; insert('categories', ['slug' => $slug, 'name' => $name, 'kind' => $kind, 'sort' => post_clamp('sort', 0, 9999, 9)]); flash('Catégorie ajoutée.'); }
        }
    }
    redirect($back);
}
admin_head('Avis, FAQ, catégories', 'content'); ?>
<ul class="nav nav-pills mb-3">
    <?php foreach (['reviews' => 'Avis clients', 'faqs' => 'Questions fréquentes', 'categories' => 'Catégories'] as $k => $l): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?>
</ul>
<?php if ($tab === 'reviews'): $rows = q('SELECT * FROM reviews ORDER BY id DESC'); ?>
<div class="alert alert-info small">Les avis marqués « exemple » portent une pastille sur le site. Ne cochez pas cette case pour un vrai avis client ; ne saisissez que des avis réels.</div>
<?php foreach ($rows as $r): ?>
<form method="post" class="adm-card mb-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Auteur</label><input class="form-control" name="author" value="<?= e($r['author']) ?>" required></div>
        <div class="col-md-2"><label class="form-label">Ville</label><input class="form-control" name="city" value="<?= e($r['city']) ?>"></div>
        <div class="col-md-1"><label class="form-label">Note</label><input class="form-control" type="number" min="1" max="5" name="rating" value="<?= (int)$r['rating'] ?>"></div>
        <div class="col-md-6"><label class="form-label">Texte</label><textarea class="form-control" name="body" rows="2" required><?= e($r['body']) ?></textarea></div>
        <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
            <label><input type="checkbox" class="form-check-input" name="active" <?= $r['active'] ? 'checked' : '' ?>> Publié</label>
            <label><input type="checkbox" class="form-check-input" name="is_demo" <?= $r['is_demo'] ? 'checked' : '' ?>> Avis d'exemple</label>
            <button class="btn btn-sm btn-gr ms-auto" name="action" value="save">Enregistrer</button>
            <button class="btn btn-sm btn-outline-danger" name="action" value="del" data-confirm="Supprimer cet avis ?">Supprimer</button></div>
    </div></form>
<?php endforeach; ?>
<form method="post" class="adm-card border-warning"><?= csrf_field() ?><input type="hidden" name="active" value="1"><h2>Ajouter un avis</h2>
    <div class="row g-2"><div class="col-md-3"><input class="form-control" name="author" placeholder="Auteur" required aria-label="Auteur"></div><div class="col-md-2"><input class="form-control" name="city" placeholder="Ville" aria-label="Ville"></div>
    <div class="col-md-1"><input class="form-control" type="number" min="1" max="5" name="rating" value="5" aria-label="Note"></div><div class="col-md-6"><textarea class="form-control" name="body" rows="2" placeholder="Texte de l'avis (réel)" required aria-label="Texte"></textarea></div>
    <div class="col-12"><button class="btn btn-gr" name="action" value="add">Ajouter</button></div></div></form>
<?php elseif ($tab === 'faqs'): ?>
<?php foreach (q('SELECT * FROM faqs ORDER BY sort, id') as $r): ?>
<form method="post" class="adm-card mb-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
    <div class="row g-2"><div class="col-md-9"><label class="form-label">Question</label><input class="form-control" name="question" value="<?= e($r['question']) ?>" required></div>
    <div class="col-md-3"><label class="form-label">Ordre</label><input class="form-control" type="number" min="0" max="9999" name="sort" value="<?= (int)$r['sort'] ?>"></div>
    <div class="col-12"><label class="form-label">Réponse</label><textarea class="form-control" name="answer" rows="2" required><?= e($r['answer']) ?></textarea></div>
    <div class="col-12 d-flex gap-3 align-items-center"><label><input type="checkbox" class="form-check-input" name="active" <?= $r['active'] ? 'checked' : '' ?>> Publiée</label>
        <button class="btn btn-sm btn-gr ms-auto" name="action" value="save">Enregistrer</button><button class="btn btn-sm btn-outline-danger" name="action" value="del" data-confirm="Supprimer cette question ?">Supprimer</button></div></div></form>
<?php endforeach; ?>
<form method="post" class="adm-card border-warning"><?= csrf_field() ?><input type="hidden" name="active" value="1"><h2>Ajouter une question</h2>
    <div class="row g-2"><div class="col-md-9"><input class="form-control" name="question" placeholder="Question" required aria-label="Question"></div><div class="col-md-3"><input class="form-control" type="number" min="0" max="9999" name="sort" value="99" aria-label="Ordre"></div>
    <div class="col-12"><textarea class="form-control" name="answer" rows="2" placeholder="Réponse" required aria-label="Réponse"></textarea></div><div class="col-12"><button class="btn btn-gr" name="action" value="add">Ajouter</button></div></div></form>
<?php else: $kinds = ['outfit' => 'Tenue complète', 'robe' => 'Toge', 'scarf' => 'Écharpe', 'cap' => 'Toque', 'accessory' => 'Accessoire']; ?>
<div class="adm-card"><div class="table-responsive"><table class="table adm-table"><thead><tr><th>Nom</th><th>Type</th><th>Ordre</th><th>Produits</th><th></th></tr></thead><tbody>
<?php foreach (categories_all() as $c): $f = 'c' . $c['id']; ?>
<tr><td><form method="post" id="<?= $f ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"></form><input form="<?= $f ?>" class="form-control form-control-sm" name="name" value="<?= e($c['name']) ?>" aria-label="Nom"></td>
    <td><?= e($kinds[$c['kind']] ?? $c['kind']) ?></td><td style="width:90px"><input form="<?= $f ?>" class="form-control form-control-sm" type="number" min="0" max="9999" name="sort" value="<?= (int)$c['sort'] ?>" aria-label="Ordre"></td>
    <td><?= (int)qv('SELECT COUNT(*) FROM products WHERE category_id = ?', [$c['id']]) ?></td>
    <td class="text-end text-nowrap"><button form="<?= $f ?>" class="btn btn-sm btn-outline-dark" name="action" value="save">Enregistrer</button> <button form="<?= $f ?>" class="btn btn-sm btn-outline-danger" name="action" value="del" data-confirm="Supprimer cette catégorie ?" aria-label="Supprimer"><i class="bi bi-trash"></i></button></td></tr>
<?php endforeach; ?>
<tr class="table-light"><td><form method="post" id="cadd"><?= csrf_field() ?></form><input form="cadd" class="form-control form-control-sm" name="name" placeholder="Nouvelle catégorie" required aria-label="Nom"></td>
    <td><select form="cadd" class="form-select form-select-sm" name="kind" aria-label="Type"><?php foreach ($kinds as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></td>
    <td><input form="cadd" class="form-control form-control-sm" type="number" min="0" max="9999" name="sort" value="9" aria-label="Ordre"></td><td></td><td class="text-end"><button form="cadd" class="btn btn-sm btn-gr" name="action" value="add">Ajouter</button></td></tr>
</tbody></table></div><div class="form-text">Le « type » détermine les options proposées (tailles, couleurs) : tenue complète / toge → taille + couleur de toge ; écharpe ; toque ; accessoire.</div></div>
<?php endif; admin_foot();
