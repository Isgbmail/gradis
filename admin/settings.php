<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_require();

// Champs texte/numériques autorisés : clé => [libellé, type, max]
const FIELDS = [
    'Entreprise' => [
        'company_name' => ['Nom de l\'entreprise', 'text', 80], 'tagline' => ['Slogan', 'text', 120],
        'about_text' => ['Texte « À propos » (pied de page)', 'textarea', 500],
        'contact_phone' => ['Téléphone', 'text', 40], 'contact_email' => ['E-mail de contact', 'email', 120],
        'contact_address' => ['Adresse', 'text', 200], 'opening_hours' => ['Horaires', 'text', 120],
        'social_instagram' => ['Instagram (URL)', 'url', 200], 'social_facebook' => ['Facebook (URL)', 'url', 200], 'social_tiktok' => ['TikTok (URL)', 'url', 200],
    ],
    'WhatsApp' => [
        'whatsapp_number' => ['Numéro WhatsApp (format international, ex. 212612345678)', 'tel', 20],
        'whatsapp_greeting' => ['Début des messages préremplis', 'text', 120],
    ],
    "Page d'accueil" => [
        'hero_title' => ['Titre principal', 'text', 120], 'hero_text' => ['Texte sous le titre', 'textarea', 300],
    ],
    'Livraison & paiement' => [
        'shipping_fee' => ['Frais de livraison (DH)', 'number', 10], 'free_shipping_from' => ['Livraison offerte à partir de (DH, 0 = jamais)', 'number', 10],
        'max_qty' => ['Quantité maximale par article', 'number', 3],
    ],
    'Personnalisation' => [
        'label_trim' => ['Nom commercial des « billets » / éléments décoratifs', 'text', 60],
        'text_max_scarf' => ['Caractères max. sur écharpe', 'number', 3], 'text_max_cap' => ['Caractères max. sur toque', 'number', 3],
        'text_size_min' => ['Taille de texte min. (px)', 'number', 3], 'text_size_max' => ['Taille de texte max. (px)', 'number', 3],
        'text_price_scarf' => ['Supplément texte écharpe (DH)', 'number', 8], 'text_price_cap' => ['Supplément texte toque (DH)', 'number', 8],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'hide_demo') {
        setting_set('demo_notice', '0'); flash('Mention « exemple » masquée.'); redirect(url('admin/index.php'));
    }
    try {
        $errs = [];
        foreach (FIELDS as $fields) foreach ($fields as $k => [$label, $type, $max]) {
            $v = mb_substr(post($k), 0, $max + ($type === 'number' ? 0 : 0));
            if ($type === 'number') { $v = (string)max(0, (float)str_replace(',', '.', $v)); }
            if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $errs[] = 'E-mail de contact invalide.'; continue; }
            if ($type === 'url' && $v !== '' && !preg_match('#^https?://#i', $v)) { $errs[] = "$label : l'adresse doit commencer par http(s)://"; continue; }
            if ($k === 'whatsapp_number') { $v = preg_replace('/\D+/', '', $v); if ($v !== '' && (strlen($v) < 10 || strlen($v) > 15)) { $errs[] = 'Numéro WhatsApp invalide (10 à 15 chiffres, indicatif pays inclus).'; continue; } }
            setting_set($k, $v);
        }
        if ((int)setting('text_size_min') > (int)setting('text_size_max')) $errs[] = 'La taille min. du texte dépasse la taille max.';
        foreach (['pay_cod', 'pay_whatsapp', 'pay_online'] as $k) setting_set($k, isset($_POST[$k]) ? '1' : '0');
        setting_set('demo_notice', isset($_POST['demo_notice']) ? '1' : '0');
        // Images
        foreach (['hero_image' => 'hero', 'logo_path' => 'brand'] as $k => $sub) {
            if (isset($_POST['remove_' . $k])) { delete_upload(setting($k)); setting_set($k, ''); }
            $path = save_image($_FILES[$k] ?? ['error' => UPLOAD_ERR_NO_FILE], $sub);
            if ($path) { delete_upload(setting($k)); setting_set($k, $path); }
        }
        if (!payment_methods()) $errs[] = 'Attention : aucun mode de commande n\'est activé, les clients ne pourront pas valider leur commande.';
        flash('Réglages enregistrés.');
        foreach ($errs as $e) flash($e, 'warning');
    } catch (RuntimeException $e) { flash($e->getMessage(), 'danger'); }
    redirect(url('admin/settings.php'));
}
admin_head('Réglages du site', 'settings'); ?>
<form method="post" enctype="multipart/form-data" class="d-flex flex-column gap-3" style="max-width:980px">
<?= csrf_field() ?>
<?php foreach (FIELDS as $title => $fields): ?>
<div class="adm-card"><h2><?= e($title) ?></h2>
    <div class="row g-3">
    <?php foreach ($fields as $k => [$label, $type, $max]): $wide = in_array($type, ['textarea'], true) || in_array($k, ['contact_address', 'about_text', 'hero_text', 'whatsapp_number'], true); ?>
        <div class="<?= $wide ? 'col-12' : 'col-md-6' ?>"><label class="form-label" for="s_<?= e($k) ?>"><?= e($label) ?></label>
        <?php if ($type === 'textarea'): ?><textarea class="form-control" id="s_<?= e($k) ?>" name="<?= e($k) ?>" rows="2" maxlength="<?= $max ?>"><?= e(setting($k)) ?></textarea>
        <?php else: ?><input class="form-control" id="s_<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $type === 'number' ? 'number" step="0.01" min="0' : e($type) ?>" value="<?= e(setting($k)) ?>"><?php endif; ?></div>
    <?php endforeach; ?>
    </div>
    <?php if ($title === 'Livraison & paiement'): ?>
    <hr><div class="fw-semibold mb-2">Modes de commande proposés aux clients</div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="pay_cod" name="pay_cod" <?= setting('pay_cod') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="pay_cod">Paiement à la livraison</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="pay_whatsapp" name="pay_whatsapp" <?= setting('pay_whatsapp') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="pay_whatsapp">Confirmation par WhatsApp</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="pay_online" name="pay_online" <?= setting('pay_online') === '1' ? 'checked' : '' ?> disabled><label class="form-check-label text-muted" for="pay_online">Paiement en ligne — non configuré (aucun prestataire branché ; voir README → Paiement en ligne)</label></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<div class="adm-card"><h2>Images</h2>
    <div class="row g-3">
        <?php foreach (['hero_image' => ["Image du bandeau d'accueil (photo de diplômé conseillée, format portrait)", 'hero'], 'logo_path' => ['Logo (en-tête du site)', 'brand']] as $k => [$label]): ?>
        <div class="col-md-6"><label class="form-label" for="i_<?= $k ?>"><?= e($label) ?></label>
            <?php if (setting($k)): ?><div class="mb-2"><img src="<?= e(url('uploads/' . setting($k))) ?>" alt="" style="max-height:110px;border-radius:8px;border:1px solid var(--line);background:var(--navy)"><label class="d-block small mt-1"><input type="checkbox" class="form-check-input" name="remove_<?= $k ?>"> Retirer (revenir à l'illustration / au logo par défaut)</label></div><?php endif; ?>
            <input class="form-control" type="file" id="i_<?= $k ?>" name="<?= $k ?>" accept="image/jpeg,image/png,image/webp"></div>
        <?php endforeach; ?>
    </div>
</div>
<div class="adm-card"><h2>Données de démonstration</h2>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="demo_notice" name="demo_notice" <?= setting('demo_notice') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="demo_notice">Afficher « Prix, produits et avis affichés à titre d'exemple » en bas du site</label></div>
    <div class="form-text">Décochez une fois vos vrais produits, prix et avis saisis.</div></div>
<div><button class="btn btn-gr btn-lg">Enregistrer les réglages</button></div>
</form>
<?php admin_foot();
