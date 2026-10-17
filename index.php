<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';

$outfits = catalog_products("c.kind = 'outfit'", [], 'p.sort, p.id');
$featured = array_slice($outfits, 0, 4);
$tiers = tiers_all();
$reviews = q('SELECT * FROM reviews WHERE active = 1 ORDER BY id DESC LIMIT 6');
$faqs = q('SELECT * FROM faqs WHERE active = 1 ORDER BY sort, id');
$heroImg = setting('hero_image');
$heroCfg = ['view' => 'outfit', 'robe' => 'noir', 'scarf' => 'beige', 'trim' => 'or', 'cap' => 'noir', 'cap_style' => 'long',
    'custom' => ['scarf' => ['text' => 'Promotion 2026', 'zone' => 'both', 'font' => 'script', 'color' => 'or', 'align' => 'center', 'size' => 18]]];

page_head('', ['active' => 'home']);
?>
<section class="gr-hero on-dark">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 order-2 order-lg-1">
                <span class="gr-eyebrow">Tenues de soutenance &amp; graduation</span>
                <h1><?= e(setting('hero_title')) ?></h1>
                <p class="lead"><?= e(setting('hero_text')) ?></p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-gr btn-lg" href="<?= e(url('catalogue.php')) ?>"><?= e(t('btn.discover')) ?></a>
                    <a class="btn btn-gr-outline btn-lg" href="<?= e(wa_link(wa_greeting() . "\nJe souhaite commander une tenue de graduation.")) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> <?= e(t('btn.order_wa')) ?></a>
                </div>
                <p class="mt-4 mb-0 small text-white-50"><i class="bi bi-brush text-warning"></i> Composez votre tenue sur mesure : couleurs, tailles et texte brodé —
                    <a class="text-warning" href="<?= e(url('configurateur.php')) ?>">ouvrir le configurateur</a></p>
            </div>
            <div class="col-lg-6 order-1 order-lg-2">
                <div class="gr-hero__fig">
                    <span class="gr-hero__ring" aria-hidden="true"></span>
                    <?php if ($heroImg): ?>
                        <img src="<?= e(url('uploads/' . $heroImg)) ?>" alt="Tenue de graduation Gradis">
                    <?php else: ?>
                        <div class="gr-svgwrap" style="max-width:380px;height:520px" data-outfit='<?= e(json_encode($heroCfg, JSON_UNESCAPED_UNICODE)) ?>' role="img" aria-label="Illustration d'une tenue de graduation"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="gr-usp on-dark" aria-label="Nos engagements">
    <div class="container">
        <div class="row row-cols-2 row-cols-lg-4 g-0">
            <div class="col"><div class="gr-usp__item"><i class="bi bi-mortarboard"></i><?= e(t('usp.quality')) ?></div></div>
            <div class="col"><div class="gr-usp__item"><i class="bi bi-truck"></i><?= e(t('usp.delivery')) ?></div></div>
            <div class="col"><div class="gr-usp__item"><i class="bi bi-palette"></i><?= e(t('usp.colors')) ?></div></div>
            <div class="col"><div class="gr-usp__item"><i class="bi bi-rulers"></i><?= e(t('usp.sizes')) ?></div></div>
        </div>
    </div>
</section>

<section class="gr-section" id="tenues">
    <div class="container">
        <div class="gr-title reveal"><h2><?= e(t('home.featured')) ?></h2><p>Des tenues complètes prêtes à commander, ou à personnaliser jusqu'au dernier détail.</p></div>
        <?php if ($featured): ?>
            <div class="row g-3 g-lg-4 reveal">
                <?php foreach ($featured as $p) echo product_card($p); ?>
            </div>
            <div class="text-center mt-5"><a class="btn btn-gr-navy btn-lg" href="<?= e(url('catalogue.php')) ?>">Voir tout le catalogue</a></div>
        <?php else: ?>
            <div class="gr-empty"><i class="bi bi-bag"></i>Le catalogue est en cours de préparation. Écrivez-nous sur WhatsApp pour commander.</div>
        <?php endif; ?>
    </div>
</section>

<section class="gr-section gr-section--white" id="collections">
    <div class="container">
        <div class="gr-title reveal"><h2><?= e(t('home.collections')) ?></h2><p>Trois gammes, une même exigence : une tenue élégante qui vous ressemble.</p></div>
        <div class="row g-4">
            <?php foreach ($tiers as $code => $t):
                $from = $t['price_robe'] + $t['price_scarf'] + $t['price_cap']; ?>
                <div class="col-md-4 reveal">
                    <div class="gr-tiercard gr-tiercard--<?= e($code) ?>">
                        <span class="gr-eyebrow mb-2">Gamme</span>
                        <h3><?= e($t['name']) ?></h3>
                        <p class="mb-3"><?= e($t['description']) ?></p>
                        <div class="from">Tenue complète dès</div>
                        <div class="big"><?= e(money($from)) ?></div>
                        <ul>
                            <li><i class="bi bi-check2"></i> Toge dès <?= e(money($t['price_robe'])) ?></li>
                            <li><i class="bi bi-check2"></i> Écharpe dès <?= e(money($t['price_scarf'])) ?></li>
                            <li><i class="bi bi-check2"></i> Toque dès <?= e(money($t['price_cap'])) ?></li>
                            <li><i class="bi bi-check2"></i> Broderie : <?= $t['text_scarf'] ? 'écharpe' : '' ?><?= $t['text_scarf'] && $t['text_cap'] ? ' + ' : '' ?><?= $t['text_cap'] ? 'toque' : '' ?><?= !$t['text_scarf'] && !$t['text_cap'] ? 'non disponible' : '' ?></li>
                        </ul>
                        <p class="mat mb-4"><?= e($t['materials']) ?></p>
                        <a class="btn <?= $code === 'prestige' ? 'btn-gr' : 'btn-gr-outline' ?> mt-auto" href="<?= e(url('configurateur.php?tier=' . $code)) ?>">Composer en <?= e($t['name']) ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="gr-section">
    <div class="container">
        <div class="gr-title reveal"><h2><?= e(t('home.why')) ?></h2></div>
        <div class="row g-4">
            <div class="col-6 col-lg-3 reveal"><div class="gr-feature"><i class="bi bi-gem"></i><h3>Finitions soignées</h3><p>Coutures renforcées, tissus choisis et détails dorés pour une tenue qui tient toute la journée.</p></div></div>
            <div class="col-6 col-lg-3 reveal"><div class="gr-feature"><i class="bi bi-brush"></i><h3>100 % personnalisable</h3><p>Couleurs, taille, broderie du prénom, de l'année ou de l'établissement.</p></div></div>
            <div class="col-6 col-lg-3 reveal"><div class="gr-feature"><i class="bi bi-truck"></i><h3>Livraison au Maroc</h3><p>Nous livrons dans toutes les villes. Frais et livraison offerte indiqués au panier.</p></div></div>
            <div class="col-6 col-lg-3 reveal"><div class="gr-feature"><i class="bi bi-whatsapp"></i><h3>Un conseiller WhatsApp</h3><p>Une question sur une taille, une couleur ou un délai ? Nous répondons directement.</p></div></div>
        </div>
    </div>
</section>

<section class="gr-section gr-section--navy" id="comment-commander">
    <div class="container">
        <div class="gr-title reveal"><h2><?= e(t('home.how')) ?></h2><p>Trois étapes simples, sans compte obligatoire.</p></div>
        <div class="row g-3 g-lg-4">
            <div class="col-md-4 reveal"><div class="gr-step"><span class="gr-step__n">1</span><div><h3>Choisissez</h3><p>Sélectionnez une tenue du catalogue ou composez la vôtre dans le configurateur.</p></div></div></div>
            <div class="col-md-4 reveal"><div class="gr-step"><span class="gr-step__n">2</span><div><h3>Commandez</h3><p>Validez votre panier, ou envoyez-nous votre sélection par WhatsApp en un clic.</p></div></div></div>
            <div class="col-md-4 reveal"><div class="gr-step"><span class="gr-step__n">3</span><div><h3>Recevez</h3><p>Nous confirmons votre commande puis livrons votre tenue partout au Maroc.</p></div></div></div>
        </div>
        <div class="text-center mt-5 on-dark">
            <a class="btn btn-gr btn-lg" href="<?= e(url('configurateur.php')) ?>"><i class="bi bi-brush"></i> Composer ma tenue</a>
        </div>
    </div>
</section>

<?php if ($reviews): ?>
<section class="gr-section" id="avis">
    <div class="container">
        <div class="gr-title reveal"><h2><?= e(t('home.reviews')) ?></h2></div>
        <div class="row g-4">
            <?php foreach ($reviews as $r): ?>
                <div class="col-md-6 col-lg-4 reveal">
                    <figure class="gr-review mb-0">
                        <?= stars((int)$r['rating']) ?>
                        <blockquote>« <?= e($r['body']) ?> »</blockquote>
                        <figcaption class="small text-muted"><strong class="text-dark"><?= e($r['author']) ?></strong><?= $r['city'] ? ' · ' . e($r['city']) : '' ?>
                            <?php if ((int)$r['is_demo']): ?><span class="gr-demo-tag">Exemple</span><?php endif; ?></figcaption>
                    </figure>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($faqs): ?>
<section class="gr-section gr-section--white" id="faq">
    <div class="container" style="max-width:820px">
        <div class="gr-title reveal"><h2><?= e(t('home.faq')) ?></h2></div>
        <div class="accordion reveal" id="faqAcc">
            <?php foreach ($faqs as $i => $f): ?>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= (int)$f['id'] ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq<?= (int)$f['id'] ?>"><?= e($f['question']) ?></button></h3>
                    <div id="faq<?= (int)$f['id'] ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faqAcc"><div class="accordion-body text-muted"><?= nl2br(e($f['answer'])) ?></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="gr-section" id="contact">
    <div class="container">
        <div class="gr-cta reveal">
            <h2><?= e(t('home.contact')) ?></h2>
            <p>Taille, couleur, délai, personnalisation… Notre équipe vous répond directement sur WhatsApp.</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center on-dark">
                <a class="btn btn-gr btn-lg" href="<?= e(wa_link(wa_greeting() . "\nJ'ai une question.")) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Écrire sur WhatsApp</a>
                <a class="btn btn-gr-outline btn-lg" href="<?= e(url('contact.php')) ?>">Page contact</a>
            </div>
        </div>
    </div>
</section>

<script type="application/json" id="gr-products"><?= json_encode(array_map('product_public', $featured), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php page_foot();
