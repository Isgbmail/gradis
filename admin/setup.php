<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
// Création du PREMIER administrateur uniquement. Ensuite cette page redirige.
if (admin_count() > 0) redirect(url('admin/login.php'));
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = post('name'); $email = post('email'); $pw = (string)($_POST['password'] ?? '');
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Session expirée, réessayez.';
    elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Nom et e-mail valides requis.';
    elseif ($pw !== (string)($_POST['password2'] ?? '')) $err = 'Les mots de passe ne correspondent pas.';
    elseif ($p = password_problem($pw)) $err = $p;
    else { admin_create($name, $email, $pw); flash('Compte créé. Connectez-vous.'); redirect(url('admin/login.php')); }
}
admin_plain_head('Installation'); ?>
<h1 class="h4 text-center mb-1">Première installation</h1>
<p class="text-center text-muted small mb-3">Créez le compte administrateur principal.</p>
<?php if ($err): ?><div class="alert alert-danger" role="alert"><?= e($err) ?></div><?php endif; ?>
<form method="post">
    <?= csrf_field() ?>
    <label class="form-label" for="n">Nom</label><input class="form-control mb-3" id="n" name="name" required value="<?= e(post('name')) ?>">
    <label class="form-label" for="e">E-mail</label><input class="form-control mb-3" id="e" name="email" type="email" required value="<?= e(post('email')) ?>">
    <label class="form-label" for="p">Mot de passe (10 caractères min., lettres + chiffres)</label><input class="form-control mb-3" id="p" name="password" type="password" required autocomplete="new-password">
    <label class="form-label" for="p2">Confirmation</label><input class="form-control mb-4" id="p2" name="password2" type="password" required autocomplete="new-password">
    <button class="btn btn-gr w-100">Créer le compte</button>
</form>
<?php admin_plain_foot();
