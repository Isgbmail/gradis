<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/catalog.php';

/** Début de page publique. $opts : active (clé de menu), desc, body (classe), og image. */
function page_head(string $title, array $opts = []): void
{
    $company = setting('company_name', 'Gradis');
    $full = $title === '' ? $company . ' — ' . setting('tagline') : $title . ' — ' . $company;
    $desc = $opts['desc'] ?? "Tenues de graduation et de soutenance au Maroc : toges, écharpes et toques personnalisables. Livraison partout au Maroc.";
    $active = $opts['active'] ?? '';
    $nav = [
        'home' => [t('nav.home'), url('index.php')],
        'shop' => [t('nav.shop'), url('catalogue.php')],
        'custom' => [t('nav.custom'), url('configurateur.php')],
        'how' => [t('nav.how'), url('index.php#comment-commander')],
        'contact' => [t('nav.contact'), url('contact.php')],
    ];
    $g = ['base' => base_path(), 'csrf' => csrf_token(), 'waNumber' => wa_number(), 'waGreeting' => wa_greeting(), 'maxQty' => (int)setting('max_qty', '20')];
    ?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= e(t('dir')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($full) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="theme-color" content="#0B132B">
    <meta property="og:title" content="<?= e($full) ?>"><meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:image" content="<?= e(url('assets/img/logo-gradis-original.jpg')) ?>">
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <script nonce="<?= e(csp_nonce()) ?>">window.GRADIS = <?= json_for_script($g) ?>; window.GRADIS_PALETTE = <?= json_for_script(palette_data()) ?>;</script>
</head>
<body class="<?= e($opts['body'] ?? '') ?>">
<a class="visually-hidden-focusable gr-skip" href="#main">Aller au contenu</a>
<header class="gr-header">
    <nav class="navbar navbar-expand-lg navbar-dark" aria-label="Navigation principale">
        <div class="container">
            <a class="navbar-brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e($company) ?> — accueil">
                <img src="<?= e(setting('logo_path') ? url('uploads/' . setting('logo_path')) : asset('assets/img/logo-mark.png')) ?>" alt="<?= e($company) ?>" height="46">
            </a>
            <div class="d-flex align-items-center gap-1 order-lg-3">
                <button class="gr-iconbtn" type="button" data-search-open aria-label="<?= e(t('nav.search')) ?>"><i class="bi bi-search"></i></button>
                <a class="gr-iconbtn position-relative" href="<?= e(url('panier.php')) ?>" aria-label="<?= e(t('nav.cart')) ?>">
                    <i class="bi bi-bag"></i><span class="gr-badge" data-cart-count hidden>0</span>
                </a>
                <a class="btn btn-gr-wa d-none d-md-inline-flex" href="<?= e(wa_link(wa_greeting())) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> <?= e(t('nav.whatsapp')) ?></a>
                <button class="navbar-toggler ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="<?= e(t('nav.menu')) ?>">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <div class="collapse navbar-collapse order-lg-2" id="mainNav">
                <ul class="navbar-nav mx-lg-auto gap-lg-3 pt-3 pt-lg-0">
                    <?php foreach ($nav as $k => [$label, $href]): ?>
                        <li class="nav-item"><a class="nav-link<?= $active === $k ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <li class="nav-item d-md-none mt-2"><a class="btn btn-gr-wa" href="<?= e(wa_link(wa_greeting())) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> <?= e(t('nav.whatsapp')) ?></a></li>
                </ul>
            </div>
        </div>
    </nav>
    <form class="gr-search" action="<?= e(url('catalogue.php')) ?>" method="get" role="search" hidden>
        <div class="container d-flex gap-2">
            <input type="search" name="q" class="form-control" placeholder="Rechercher une tenue, une toge, une écharpe…" aria-label="Rechercher" autocomplete="off">
            <button class="btn btn-gr" type="submit">Rechercher</button>
            <button class="gr-iconbtn" type="button" data-search-close aria-label="Fermer la recherche"><i class="bi bi-x-lg"></i></button>
        </div>
    </form>
</header>
<main id="main">
<?php
}

function page_foot(array $opts = []): void
{
    $company = setting('company_name', 'Gradis');
    ?>
</main>
<footer class="gr-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <img src="<?= e(asset('assets/img/logo-gradis.png')) ?>" alt="<?= e($company) ?>" class="gr-footer__logo">
                <p class="mt-3 mb-0 text-white-50"><?= e(setting('about_text')) ?></p>
            </div>
            <div class="col-6 col-lg-2 offset-lg-1">
                <h2 class="gr-footer__h"><?= e(t('footer.links')) ?></h2>
                <ul class="list-unstyled gr-footer__list">
                    <li><a href="<?= e(url('catalogue.php')) ?>">Nos tenues</a></li>
                    <li><a href="<?= e(url('configurateur.php')) ?>">Personnalisation</a></li>
                    <li><a href="<?= e(url('index.php#comment-commander')) ?>">Comment commander ?</a></li>
                    <li><a href="<?= e(url('index.php#faq')) ?>">Questions fréquentes</a></li>
                    <li><a href="<?= e(url('panier.php')) ?>">Panier</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="gr-footer__h"><?= e(t('footer.contact')) ?></h2>
                <ul class="list-unstyled gr-footer__list">
                    <li><i class="bi bi-whatsapp"></i> <a href="<?= e(wa_link(wa_greeting())) ?>" target="_blank" rel="noopener">WhatsApp</a></li>
                    <?php if (setting('contact_phone')): ?><li><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li><?php endif; ?>
                    <?php if (setting('contact_email')): ?><li><i class="bi bi-envelope"></i> <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li><?php endif; ?>
                    <li><i class="bi bi-geo-alt"></i> <?= e(setting('contact_address')) ?></li>
                    <li><i class="bi bi-clock"></i> <?= e(setting('opening_hours')) ?></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h2 class="gr-footer__h"><?= e(t('footer.follow')) ?></h2>
                <div class="d-flex gap-2">
                    <?php foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'] as $k => $n): if (setting('social_' . $k)): ?>
                        <a class="gr-social" href="<?= e(setting('social_' . $k)) ?>" target="_blank" rel="noopener" aria-label="<?= e($n) ?>"><i class="bi bi-<?= e($k) ?>"></i></a>
                    <?php endif; endforeach; ?>
                    <a class="gr-social" href="<?= e(wa_link(wa_greeting())) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
        </div>
        <div class="gr-footer__bottom">
            <span>© <?= date('Y') ?> <?= e($company) ?>. <?= e(t('footer.rights')) ?></span>
            <?php if (setting('demo_notice') === '1'): ?><span>Prix, produits et avis affichés à titre d'exemple.</span><?php endif; ?>
        </div>
    </div>
</footer>

<a class="gr-fab" href="<?= e(wa_link(wa_greeting())) ?>" target="_blank" rel="noopener" aria-label="Discuter sur WhatsApp"><i class="bi bi-whatsapp"></i></a>

<!-- Ajout rapide au panier (taille / couleur) -->
<div class="modal fade" id="quickAdd" tabindex="-1" aria-labelledby="quickAddTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content gr-modal">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h4" id="quickAddTitle">Ajouter au panier</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-center mb-3">
                    <div class="gr-qa-thumb" data-qa-thumb></div>
                    <div><div class="fw-semibold" data-qa-name></div><div class="gr-price" data-qa-price></div></div>
                </div>
                <div data-qa-colors-wrap class="mb-3"><div class="gr-label">Couleur</div><div class="gr-chips" data-qa-colors role="radiogroup" aria-label="Couleur"></div></div>
                <div data-qa-sizes-wrap class="mb-3"><div class="gr-label">Taille</div><div class="gr-chips" data-qa-sizes role="radiogroup" aria-label="Taille"></div></div>
                <div class="gr-label">Quantité</div>
                <div class="gr-qty" data-qa-qty><button type="button" data-q="-1" aria-label="Moins">−</button><input type="number" min="1" value="1" aria-label="Quantité"><button type="button" data-q="1" aria-label="Plus">+</button></div>
                <div class="text-danger small mt-3" data-qa-error role="alert" hidden></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-gr w-100" data-qa-confirm><i class="bi bi-bag-plus"></i> Ajouter au panier</button>
            </div>
        </div>
    </div>
</div>

<div class="gr-toasts" aria-live="polite" aria-atomic="true" id="toasts"></div>

<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/outfit.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?php foreach ($opts['scripts'] ?? [] as $s): ?><script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** Section d'étoiles ★ pour les avis. */
function stars(int $n): string
{
    $o = '';
    for ($i = 1; $i <= 5; $i++) $o .= '<i class="bi bi-star' . ($i <= $n ? '-fill' : '') . '"></i>';
    return '<span class="gr-stars" aria-label="' . $n . ' sur 5">' . $o . '</span>';
}
