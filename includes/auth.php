<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/catalog.php';

if (!defined('UPLOAD_ROOT')) define('UPLOAD_ROOT', GR_ROOT . '/uploads');

const ORDER_STATUSES = [
    'pending' => 'En attente', 'confirmed' => 'Confirmée', 'preparing' => 'En préparation',
    'shipped' => 'Expédiée', 'delivered' => 'Livrée', 'cancelled' => 'Annulée',
];
/** Transitions de statut autorisées (une commande ne revient jamais en arrière ; annulée/livrée = définitif). */
const ORDER_TRANSITIONS = [
    'pending' => ['confirmed', 'cancelled'], 'confirmed' => ['preparing', 'shipped', 'cancelled'],
    'preparing' => ['shipped', 'cancelled'], 'shipped' => ['delivered', 'cancelled'], 'delivered' => [], 'cancelled' => [],
];
const STATUS_BADGE = [
    'pending' => 'warning', 'confirmed' => 'info', 'preparing' => 'primary',
    'shipped' => 'secondary', 'delivered' => 'success', 'cancelled' => 'danger',
];
/** Statuts comptés dans le chiffre d'affaires (commandes réellement engagées). */
const REVENUE_STATUSES = ['confirmed', 'preparing', 'shipped', 'delivered'];
const PAYMENT_STATUSES = ['unpaid' => 'Non payée', 'paid' => 'Payée', 'refunded' => 'Remboursée'];

// --------------------------------------------------------------- auth ----
function admin_count(): int { return (int)qv('SELECT COUNT(*) FROM admin_users'); }

function admin_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    start_session();
    $id = (int)($_SESSION['admin_id'] ?? 0);
    return $u = ($id ? q1('SELECT id, name, email FROM admin_users WHERE id = ?', [$id]) : null);
}

function admin_require(): array
{
    $u = admin_user();
    if (!$u) redirect(url(admin_count() === 0 ? 'admin/setup.php' : 'admin/login.php'));
    admin_post_guard();
    return $u;
}

/** Toute requête POST de l'administration doit porter le jeton CSRF. */
function admin_post_guard(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrf_check($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Session expirée ou requête invalide. Retournez en arrière, rechargez la page puis réessayez.');
    }
}

function password_problem(string $pw): ?string
{
    if (strlen($pw) < 10) return 'Le mot de passe doit contenir au moins 10 caractères.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Le mot de passe doit contenir des lettres et des chiffres.';
    return null;
}

/** null si connecté, sinon message d'erreur. 5 échecs / 15 min / IP. */
function admin_login(string $email, string $pw): ?string
{
    $bucket = 'login:' . client_ip();
    ex('DELETE FROM throttle WHERE ts < ?', [time() - 900]);
    if ((int)qv('SELECT COUNT(*) FROM throttle WHERE bucket = ?', [$bucket]) >= 5) return 'Trop de tentatives. Réessayez dans 15 minutes.';
    $u = q1('SELECT * FROM admin_users WHERE email = ?', [strtolower(trim($email))]);
    $ok = password_verify($pw, $u['password_hash'] ?? password_hash('dummy', PASSWORD_DEFAULT));
    if (!$u || !$ok) {
        ex('INSERT INTO throttle (bucket, ts) VALUES (?, ?)', [$bucket, time()]);
        return 'Identifiants incorrects.';
    }
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$u['id'];
    unset($_SESSION['csrf']);
    ex('UPDATE admin_users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
    return null;
}

function admin_logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function admin_create(string $name, string $email, string $pw): int
{
    return insert('admin_users', [
        'email' => strtolower(trim($email)), 'name' => trim($name),
        'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'created_at' => now(),
    ]);
}

// -------------------------------------------------------------- flash ----
function flash(string $msg, string $type = 'success'): void
{
    start_session();
    $_SESSION['flash'][] = [$type, $msg];
}
function flash_take(): array
{
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ------------------------------------------------------------ uploads ----
/** Enregistre une image téléversée. Retourne le chemin relatif (dans /uploads) ou null. Lève RuntimeException si invalide. */
function save_image(array $file, string $sub = 'products'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Échec du téléversement (code {$file['error']}).");
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Fichier téléversé invalide.');
    $size = filesize($tmp);
    if ($size === false || $size > 5 * 1024 * 1024) throw new RuntimeException('Image trop lourde (5 Mo maximum).');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!is_string($mime)) throw new RuntimeException('Impossible de vérifier le format de cette image.');
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    $image = @getimagesize($tmp);
    if (!$ext || !$image || ($image['mime'] ?? '') !== $mime) throw new RuntimeException('Format non accepté (JPG, PNG ou WebP uniquement).');
    $width = (int)$image[0];
    $height = (int)$image[1];
    if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 16000000) {
        throw new RuntimeException('Dimensions d’image trop importantes.');
    }
    $dir = UPLOAD_ROOT . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Dossier uploads non inscriptible.');
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) throw new RuntimeException("Impossible d'enregistrer l'image.");
    return "$sub/" . optimize_image("$dir/$name", $mime);
}
/** Réduit les photos lourdes (max 1600 px), supprime les métadonnées (EXIF/GPS) et convertit en WebP si GD le permet. Retourne le nom final. */
function optimize_image(string $file, string $mime): string
{
    if (!function_exists('imagecreatetruecolor')) return basename($file);
    try {
        $src = match ($mime) { 'image/jpeg' => @imagecreatefromjpeg($file), 'image/png' => @imagecreatefrompng($file), 'image/webp' => @imagecreatefromwebp($file), default => false };
        if (!$src) return basename($file);
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $ex = @exif_read_data($file); $o = (int)($ex['Orientation'] ?? 1);
            $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
        }
        $w = imagesx($src); $h = imagesy($src); $max = 1600;
        if (max($w, $h) > $max) {
            $r = $max / max($w, $h);
            $dst = imagescale($src, (int)round($w * $r), (int)round($h * $r));
            if ($dst) $src = $dst;
        }
        imagealphablending($src, false); imagesavealpha($src, true);
        if (function_exists('imagewebp')) {
            $out = preg_replace('/\.[a-z]+$/', '.webp', $file);
            if (imagewebp($src, $out, 82)) { if ($out !== $file) @unlink($file); return basename($out); }
        }
        if ($mime === 'image/jpeg' && imagejpeg($src, $file, 85)) return basename($file);
    } catch (Throwable $e) { /* on garde l'original */ }
    return basename($file);
}
function delete_upload(?string $rel): void
{
    if (!$rel) return;
    $f = realpath(UPLOAD_ROOT . '/' . $rel);
    $root = realpath(UPLOAD_ROOT);
    if ($f && $root && str_starts_with($f, $root . DIRECTORY_SEPARATOR) && is_file($f)) @unlink($f);
}

// ------------------------------------------------------------ helpers ----
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function post_int(string $k, int $d = 0): int { return (int)($_POST[$k] ?? $d); }
function post_num(string $k): float { return (float)str_replace(',', '.', (string)($_POST[$k] ?? 0)); }
/** Montant en DH saisi dans un formulaire : doit être un nombre fini, compris entre $min et $max (sinon RuntimeException). */
function post_money(string $k, string $label, float $min = 0.0, float $max = 100000.0): float
{
    $raw = str_replace([',', ' '], ['.', ''], (string)($_POST[$k] ?? ''));
    if ($raw === '' || !is_numeric($raw)) throw new RuntimeException("$label : saisissez un montant valide.");
    $v = round((float)$raw, 2);
    if (!is_finite($v) || $v < $min) throw new RuntimeException("$label : le montant ne peut pas être négatif" . ($min > 0 ? ' ou nul' : '') . '.');
    if ($v > $max) throw new RuntimeException("$label : le montant ne peut pas dépasser " . number_format($max, 0, ',', ' ') . ' DH.');
    return $v;
}
/** Entier de formulaire borné (hors bornes : ramené dans l'intervalle). */
function post_clamp(string $k, int $min, int $max, int $d = 0): int { return max($min, min($max, (int)($_POST[$k] ?? $d))); }

/** Numéro marocain/international → chiffres pour wa.me (06… → 2126…). */
function wa_phone(string $p): string
{
    $d = preg_replace('/\D+/', '', $p);
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    elseif (str_starts_with($d, '0') && strlen($d) === 10) $d = '212' . substr($d, 1);
    return $d;
}

// ------------------------------------------------------------- layout ----
function admin_head(string $title, string $active = ''): void
{
    $u = admin_user();
    $nav = [
        'dash' => ['speedometer2', 'Tableau de bord', 'admin/index.php'],
        'orders' => ['receipt', 'Commandes', 'admin/orders.php'],
        'products' => ['bag', 'Produits', 'admin/products.php'],
        'options' => ['palette', 'Gammes & options', 'admin/options.php'],
        'hero' => ['images', "Bandeau d'accueil", 'admin/hero.php'],
        'content' => ['chat-quote', 'Avis, FAQ, catégories', 'admin/content.php'],
        'settings' => ['gear', 'Réglages du site', 'admin/settings.php'],
        'users' => ['people', 'Administrateurs', 'admin/users.php'],
    ];
    $pending = (int)qv("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    ?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> — Administration Gradis</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<script nonce="<?= e(csp_nonce()) ?>">window.GRADIS_PALETTE = <?= json_for_script(palette_data()) ?>;</script>
</head><body class="adm">
<div class="adm-shell">
<aside class="adm-side offcanvas-lg offcanvas-start" tabindex="-1" id="admSide">
    <div class="adm-brand"><img src="<?= e(asset('assets/img/logo-mark.png')) ?>" alt="Gradis" height="40"><span>Administration</span></div>
    <nav class="adm-nav" aria-label="Administration">
        <?php foreach ($nav as $k => [$ic, $label, $href]): ?>
            <a href="<?= e(url($href)) ?>" class="<?= $active === $k ? 'active' : '' ?>"><i class="bi bi-<?= $ic ?>"></i> <?= e($label) ?>
                <?php if ($k === 'orders' && $pending): ?><span class="badge text-bg-warning ms-auto"><?= $pending ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="adm-side__foot">
        <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Voir le site</a>
        <div class="small text-white-50 mt-2"><?= e($u['name'] ?? '') ?><br><?= e($u['email'] ?? '') ?></div>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="mt-1"><?= csrf_field() ?><button type="submit" class="adm-logout"><i class="bi bi-box-arrow-left"></i> Déconnexion</button></form>
    </div>
</aside>
<div class="adm-main">
    <div class="adm-top d-lg-none">
        <button class="btn btn-gr btn-sm" data-bs-toggle="offcanvas" data-bs-target="#admSide" aria-label="Menu"><i class="bi bi-list"></i></button>
        <strong><?= e($title) ?></strong>
    </div>
    <div class="adm-content">
        <?php foreach (flash_take() as [$t, $m]): ?>
            <div class="alert alert-<?= e($t) ?> alert-dismissible fade show" role="alert"><?= e($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
        <?php endforeach; ?>
        <h1 class="adm-h1 d-none d-lg-block"><?= e($title) ?></h1>
<?php
}

function admin_foot(array $scripts = []): void
{
    ?>
    </div>
</div>
</div>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/outfit.js')) ?>"></script>
<script nonce="<?= e(csp_nonce()) ?>">document.addEventListener('DOMContentLoaded',function(){ if(window.GradisOutfit) GradisOutfit.hydrate(); document.addEventListener('submit',function(e){ var msg=e.target.getAttribute('data-confirm'); if(msg && !window.confirm(msg)) e.preventDefault(); }); document.addEventListener('click',function(e){ var el=e.target.closest('[data-confirm]'); if(el && el.tagName !== 'FORM' && !window.confirm(el.getAttribute('data-confirm'))) e.preventDefault(); var image=e.target.closest('[data-image-action]'); if(image){ var input=image.form && image.form.querySelector('[name=img]'); if(input) input.value=image.getAttribute('data-image-action'); } }); document.addEventListener('change',function(e){ if(e.target.matches('[data-submit-on-change]') && e.target.form) e.target.form.requestSubmit(); }); });</script>
<?php foreach ($scripts as $s): ?><script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
</body></html>
<?php
}

/** Page minimale (connexion / installation). */
function admin_plain_head(string $title): void
{
    ?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> — Gradis</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>"></head>
<body class="adm-login"><main class="adm-login__box">
<img src="<?= e(asset('assets/img/logo-gradis.png')) ?>" alt="Gradis" class="adm-login__logo">
<?php
}
function admin_plain_foot(): void { echo '</main></body></html>'; }
