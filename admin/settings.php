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

const NUM_RANGE = ['shipping_fee' => [0, 10000], 'free_shipping_from' => [0, 1000000], 'max_qty' => [1, 99],
    'text_max_scarf' => [1, 200], 'text_max_cap' => [1, 200], 'text_size_min' => [6, 60], 'text_size_max' => [6, 80],
    'text_price_scarf' => [0, 10000], 'text_price_cap' => [0, 10000]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'hide_demo') {
        setting_set('demo_notice', '0'); flash('Mention « exemple » masquée.'); redirect(url('admin/index.php'));
    }
    try {
        $errs = [];
        // Bornes logiques des champs numériques (rien n'est enregistré si une valeur est incohérente)
        $bad = [];
        foreach (['company_name' => "Nom de l'entreprise", 'hero_title' => 'Titre principal', 'label_trim' => 'Nom commercial des « billets »'] as $rk => $rl)
            if (mb_strlen(trim((string)($_POST[$rk] ?? ''))) < 2) $bad[] = "$rl : ce champ est obligatoire.";
        foreach (NUM_RANGE as $k => [$lo, $hi]) {
            $raw = str_replace(',', '.', trim((string)($_POST[$k] ?? '')));
            $lab = '';
            foreach (FIELDS as $fl) if (isset($fl[$k])) $lab = $fl[$k][0];
            if ($raw === '' || !is_numeric($raw) || (float)$raw < $lo || (float)$raw > $hi) $bad[] = "$lab : valeur entre $lo et $hi attendue.";
        }
        if (!$bad && (int)$_POST['text_size_min'] > (int)$_POST['text_size_max']) $bad[] = 'La taille min. du texte dépasse la taille max.';
        if ($bad) { foreach ($bad as $b) flash($b, 'danger'); redirect(url('admin/settings.php')); }
        foreach (FIELDS as $fields) foreach ($fields as $k => [$label, $type, $max]) {
            $v = mb_substr(post($k), 0, $type === 'number' ? 12 : $max);
            if ($type === 'number') { $v = (string)round(max(0, (float)str_replace(',', '.', $v)), 2); }
            if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $errs[] = 'E-mail de contact invalide.'; continue; }
            if ($type === 'url' && $v !== '' && !preg_match('#^https?://#i', $v)) { $errs[] = "$label : l'adresse doit commencer par http(s)://"; continue; }
            if ($k === 'whatsapp_number') { $v = preg_replace('/\D+/', '', $v); if ($v !== '' && (strlen($v) < 10 || strlen($v) > 15)) { $errs[] = 'Numéro WhatsApp invalide (10 à 15 chiffres, indicatif pays inclus).'; continue; } }
            setting_set($k, $v);
        }
        foreach (['delivery_enabled', 'pay_cod', 'pay_whatsapp', 'pay_online'] as $k) setting_set($k, isset($_POST[$k]) ? '1' : '0');
        setting_set('demo_notice', isset($_POST['demo_notice']) ? '1' : '0');
        // Images
        foreach (['logo_path' => 'brand'] as $k => $sub) {
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
        <?php else: ?><input class="form-control" id="s_<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $type === 'number' ? 'number" step="' . (isset(NUM_RANGE[$k]) && NUM_RANGE[$k][1] <= 200 && $k !== 'text_price_scarf' ? '1' : '0.01') . '" min="' . (NUM_RANGE[$k][0] ?? 0) . '" max="' . (NUM_RANGE[$k][1] ?? 1000000) : e($type) ?>" value="<?= e(setting($k)) ?>"><?php endif; ?></div>
    <?php endforeach; ?>
    </div>
    <?php if ($title === 'Livraison & paiement'): ?>
    <hr><div class="fw-semibold mb-2">Disponibilité de la livraison</div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="delivery_enabled" name="delivery_enabled" <?= delivery_enabled() ? 'checked' : '' ?>><label class="form-check-label" for="delivery_enabled">Proposer la livraison aux clients</label></div>
    <div class="form-text">Si cette option est désactivée, les clients devront convenir des modalités de livraison avec vous sur WhatsApp.</div>
    <hr><div class="fw-semibold mb-2">Modes de commande proposés aux clients</div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="pay_cod" name="pay_cod" <?= setting('pay_cod') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="pay_cod">Paiement à la livraison</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="pay_whatsapp" name="pay_whatsapp" <?= setting('pay_whatsapp') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="pay_whatsapp">Confirmation par WhatsApp</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="pay_online" name="pay_online" <?= setting('pay_online') === '1' ? 'checked' : '' ?> disabled><label class="form-check-label text-muted" for="pay_online">Paiement en ligne — non configuré (aucun prestataire branché ; voir README → Paiement en ligne)</label></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<div class="adm-card"><h2>Logo</h2><p class="small text-muted">Les images du bandeau d'accueil se gèrent dans <a href="<?= e(url('admin/hero.php')) ?>">Bandeau d'accueil</a>.</p>
    <div class="row g-3">
        <?php foreach (['logo_path' => ['Logo (en-tête du site)', 'brand']] as $k => [$label]): ?>
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
