<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$me = admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');
    if ($act === 'add') {
        $email = strtolower(post('email')); $pw = (string)($_POST['password'] ?? '');
        if (post('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Nom et e-mail valides requis.', 'danger');
        elseif (qv('SELECT 1 FROM admin_users WHERE email = ?', [$email])) flash('Cet e-mail existe déjà.', 'danger');
        elseif ($p = password_problem($pw)) flash($p, 'danger');
        else { admin_create(post('name'), $email, $pw); flash('Administrateur ajouté.'); }
    } elseif ($act === 'password') {
        $id = post_int('id'); $pw = (string)($_POST['password'] ?? '');
        if ($p = password_problem($pw)) flash($p, 'danger');
        else { ex('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]); flash('Mot de passe modifié.'); }
    } elseif ($act === 'del') {
        $id = post_int('id');
        if ($id === (int)$me['id']) flash('Vous ne pouvez pas supprimer votre propre compte.', 'warning');
        elseif (admin_count() <= 1) flash('Il doit rester au moins un administrateur.', 'warning');
        else { ex('DELETE FROM admin_users WHERE id = ?', [$id]); flash('Administrateur supprimé.'); }
    }
    redirect(url('admin/users.php'));
}
admin_head('Administrateurs', 'users'); ?>
<div class="row g-3">
<div class="col-lg-7"><div class="adm-card"><h2>Comptes</h2><div class="table-responsive"><table class="table adm-table"><thead><tr><th>Nom</th><th>E-mail</th><th>Dernière connexion</th><th></th></tr></thead><tbody>
<?php foreach (q('SELECT * FROM admin_users ORDER BY id') as $u): ?>
<tr><td><?= e($u['name']) ?><?= (int)$u['id'] === (int)$me['id'] ? ' <span class="badge text-bg-light border">vous</span>' : '' ?></td><td><?= e($u['email']) ?></td><td class="small"><?= e($u['last_login'] ?: '—') ?></td>
<td class="text-end"><form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
    <input class="form-control form-control-sm" type="password" name="password" placeholder="Nouveau mot de passe" autocomplete="new-password" style="width:170px" aria-label="Nouveau mot de passe">
    <button class="btn btn-sm btn-outline-dark" name="action" value="password">Changer</button>
    <?php if ((int)$u['id'] !== (int)$me['id']): ?><button class="btn btn-sm btn-outline-danger" name="action" value="del" onclick="return confirm('Supprimer ce compte ?')" aria-label="Supprimer"><i class="bi bi-trash"></i></button><?php endif; ?></form></td></tr>
<?php endforeach; ?></tbody></table></div></div></div>
<div class="col-lg-5"><form method="post" class="adm-card"><?= csrf_field() ?><input type="hidden" name="action" value="add"><h2>Ajouter un administrateur</h2>
    <label class="form-label" for="un">Nom</label><input class="form-control mb-2" id="un" name="name" required>
    <label class="form-label" for="ue">E-mail</label><input class="form-control mb-2" id="ue" type="email" name="email" required>
    <label class="form-label" for="up">Mot de passe (10 car. min., lettres + chiffres)</label><input class="form-control mb-3" id="up" type="password" name="password" required autocomplete="new-password">
    <button class="btn btn-gr w-100">Ajouter</button><div class="form-text">Les mots de passe sont stockés hachés (bcrypt/argon2), jamais en clair.</div></form></div>
</div>
<?php admin_foot();
