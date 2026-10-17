<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';

page_head('Contact', ['active' => 'contact', 'desc' => 'Contactez Gradis par WhatsApp, téléphone ou e-mail.']);
$products = catalog_products('', [], 'p.sort, p.id');
?>
<section class="gr-pagehead">
    <div class="container">
        <div class="gr-crumbs"><a href="<?= e(url('index.php')) ?>">Accueil</a> / Contact</div>
        <h1>Contact</h1>
        <p>Une question sur une taille, une couleur, une personnalisation ou un délai ? Écrivez-nous.</p>
    </div>
</section>

<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="gr-panel">
                    <h2 class="h3">Écrivez-nous sur WhatsApp</h2>
                    <p class="text-muted">Remplissez ce court formulaire : il prépare un message que vous n'avez plus qu'à envoyer.</p>
                    <form id="waForm" class="row g-3" novalidate>
                        <div class="col-sm-6"><label class="form-label" for="wName">Votre prénom</label><input class="form-control" id="wName" autocomplete="given-name" maxlength="60"></div>
                        <div class="col-sm-6"><label class="form-label" for="wCity">Ville</label><input class="form-control" id="wCity" autocomplete="address-level2" maxlength="60"></div>
                        <div class="col-12"><label class="form-label" for="wProd">Produit concerné (facultatif)</label>
                            <select class="form-select" id="wProd"><option value="">— Question générale —</option>
                                <?php foreach ($products as $p): ?><option value="<?= e($p['name']) ?>"><?= e($p['name']) ?> (<?= e($p['tier_name']) ?>)</option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label" for="wMsg">Votre message</label><textarea class="form-control" id="wMsg" rows="4" maxlength="600" placeholder="Bonjour, je voudrais savoir…"></textarea></div>
                        <div class="col-12"><button type="submit" class="btn btn-gr btn-lg"><i class="bi bi-whatsapp"></i> Envoyer sur WhatsApp</button></div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="gr-panel mb-3">
                    <h2 class="h4">Nos coordonnées</h2>
                    <ul class="list-unstyled mb-0 mt-3">
                        <li class="mb-2"><i class="bi bi-whatsapp text-success me-2"></i><a href="<?= e(wa_link(wa_greeting())) ?>" target="_blank" rel="noopener">Discuter sur WhatsApp</a></li>
                        <?php if (setting('contact_phone')): ?><li class="mb-2"><i class="bi bi-telephone me-2"></i><a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li><?php endif; ?>
                        <?php if (setting('contact_email')): ?><li class="mb-2"><i class="bi bi-envelope me-2"></i><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li><?php endif; ?>
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i><?= e(setting('contact_address')) ?></li>
                        <li><i class="bi bi-clock me-2"></i><?= e(setting('opening_hours')) ?></li>
                    </ul>
                </div>
                <div class="gr-cta text-start p-4">
                    <h2 class="h4">Besoin d'une tenue sur mesure ?</h2>
                    <p class="mb-3 mx-0">Composez-la vous-même : couleurs, taille, broderie.</p>
                    <a class="btn btn-gr" href="<?= e(url('configurateur.php')) ?>"><i class="bi bi-brush"></i> Ouvrir le configurateur</a>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
document.getElementById('waForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var v = function (id) { return document.getElementById(id).value.trim(); };
  var L = [GRADIS.waGreeting];
  if (v('wName')) L.push('Je m\'appelle ' + v('wName') + (v('wCity') ? ' (' + v('wCity') + ')' : '') + '.');
  if (v('wProd')) L.push('Produit concerné : ' + v('wProd'));
  if (v('wMsg')) L.push('', v('wMsg')); else L.push('', "J'ai une question.");
  window.open(Gradis.waLink(L.join('\n')), '_blank', 'noopener');
});
</script>
<?php page_foot();
