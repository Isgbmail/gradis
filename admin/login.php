<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
if (admin_user()) redirect(url('admin/index.php'));
if (admin_count() === 0) redirect(url('admin/setup.php'));
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Session expirée, réessayez.';
    else {
        $err = admin_login(post('email'), (string)($_POST['password'] ?? ''));
        if (!$err) redirect(url('admin/index.php'));
    }
}
admin_plain_head('Connexion'); ?>
<h1 class="h4 text-center mb-3">Connexion</h1>
<?php if ($err): ?><div class="alert alert-danger" role="alert"><?= e($err) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label class="form-label" for="email">E-mail</label>
    <input class="form-control mb-3" id="email" name="email" type="email" required autofocus autocomplete="username">
    <label class="form-label" for="pw">Mot de passe</label>
    <input class="form-control mb-4" id="pw" name="password" type="password" required autocomplete="current-password">
    <button class="btn btn-gr w-100">Se connecter</button>
</form>
<?php admin_plain_foot();
