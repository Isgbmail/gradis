<?php
declare(strict_types=1);
require __DIR__ . '/includes/layout.php';

$data = configurator_data();

// Pré-réglage depuis un produit du catalogue (?product=ID) ou une gamme (?tier=code)
$preset = ['tier' => preg_replace('/[^a-z]/', '', (string)($_GET['tier'] ?? ''))];
if (!empty($_GET['product'])) {
    $found = catalog_products('p.id = ?', [(int)$_GET['product']]);
    if ($found) {
        $p = $found[0];
        $preset['tier'] = $p['tier'];
        $preset['product_name'] = $p['name'];
        $preset['parts'] = ['outfit' => ['robe' => 1, 'scarf' => 1, 'cap' => 1], 'robe' => ['robe' => 1, 'scarf' => 0, 'cap' => 0],
            'scarf' => ['robe' => 0, 'scarf' => 1, 'cap' => 0], 'cap' => ['robe' => 0, 'scarf' => 0, 'cap' => 1]][$p['kind']] ?? null;
        foreach (['robe_color' => 'def_robe', 'scarf_color' => 'def_scarf', 'trim_color' => 'def_trim', 'cap_color' => 'def_cap'] as $k => $col) {
            if ($p[$col]) $preset[$k] = $p[$col];
        }
        if (!$preset['parts']) unset($preset['parts']);
    }
}

page_head('Personnalisation', ['active' => 'custom', 'body' => 'has-mobilebar',
    'desc' => 'Composez votre tenue de graduation : gamme, couleurs, taille, broderie du prénom, de l\'année ou de l\'établissement. Prix en temps réel.']);
$trim = $data['label_trim'];
?>
<section class="gr-pagehead">
    <div class="container">
        <div class="gr-crumbs"><a href="<?= e(url('index.php')) ?>">Accueil</a> / Personnalisation</div>
        <h1>Composez votre tenue</h1>
        <p>Choisissez chaque détail — l'aperçu et le prix se mettent à jour en direct.</p>
    </div>
</section>

<section class="py-4 py-lg-5">
    <div class="container">
        <noscript><div class="alert alert-warning">Le configurateur nécessite JavaScript. Vous pouvez aussi nous écrire sur WhatsApp pour composer votre tenue.</div></noscript>
        <div class="gr-conf" id="conf" data-loading>
            <!-- ======================== Aperçu & total ======================== -->
            <div class="gr-conf__preview order-lg-2">
                <div class="gr-stage"><span class="gr-stage__tag" id="stageTag">Aperçu</span><div id="stage" class="d-flex w-100 h-100" role="img" aria-label="Aperçu de votre tenue"></div></div>
                <div class="gr-total" aria-live="polite">
                    <div id="breakdown"></div>
                    <div class="gr-total__row mt-2 align-items-center"><label for="cQty" class="mb-0">Quantité</label>
                        <div class="gr-qty"><button type="button" data-q="-1" aria-label="Moins">−</button><input type="number" id="cQty" min="1" max="<?= (int)$data['limits']['max_qty'] ?>" value="1"><button type="button" data-q="1" aria-label="Plus">+</button></div></div>
                    <div class="gr-total__big"><span class="gr-label mb-0">Total</span><strong id="total">—</strong></div>
                    <div class="text-danger small mt-2" id="confError" role="alert" hidden></div>
                    <div class="d-grid gap-2 mt-3">
                        <button type="button" class="btn btn-gr btn-lg" id="addBtn"><i class="bi bi-bag-plus"></i> <span>Ajouter au panier</span></button>
                        <a class="btn btn-gr-navy" id="waBtn" href="#" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Commander sur WhatsApp</a>
                        <a class="btn btn-link btn-sm text-muted" id="askBtn" href="#" target="_blank" rel="noopener">Poser une question sur ma composition</a>
                    </div>
                </div>
            </div>

            <!-- ============================ Étapes ============================ -->
            <div class="gr-conf__steps order-lg-1">
                <div class="gr-step-card" id="stPreset" hidden><div class="gr-note gr-note--warn mt-0" id="presetNote"></div></div>

                <div class="gr-step-card" id="stTier">
                    <h2><span class="n"></span>Choisissez votre gamme</h2>
                    <div class="gr-tierpick" id="tierPick" role="radiogroup" aria-label="Gamme"></div>
                    <div class="gr-note" id="tierMat"></div>
                    <span class="gr-label mt-4">Pièces incluses</span>
                    <div class="gr-chips" id="partsPick"></div>
                    <div class="gr-note">Tenue complète, ou uniquement une ou deux pièces : le prix s'adapte.</div>
                </div>

                <div class="gr-step-card" data-needs="robe">
                    <h2><span class="n"></span>La toge</h2>
                    <span class="gr-label">Couleur de la toge</span>
                    <div class="gr-chips" data-chips="robe_color" role="radiogroup" aria-label="Couleur de la toge"></div>
                    <div class="gr-note" data-note="robe_color"></div>
                    <span class="gr-label mt-4">Taille</span>
                    <div class="gr-chips" data-chips="size" role="radiogroup" aria-label="Taille"></div>
                    <div class="gr-note" data-note="size">Choisissez la taille qui correspond à votre morphologie. Un doute ? <a id="sizeHelp" href="#" target="_blank" rel="noopener">Demandez conseil sur WhatsApp</a>.</div>
                </div>

                <div class="gr-step-card" data-needs="scarf">
                    <h2><span class="n"></span>L'écharpe</h2>
                    <span class="gr-label">Couleur de l'écharpe</span>
                    <div class="gr-chips" data-chips="scarf_color" role="radiogroup" aria-label="Couleur de l'écharpe"></div>
                    <div class="gr-note" data-note="scarf_color"></div>
                </div>

                <div class="gr-step-card" id="stTrim">
                    <h2><span class="n"></span><?= e($trim) ?></h2>
                    <span class="gr-label">Couleur des éléments décoratifs</span>
                    <div class="gr-chips" data-chips="trim_color" role="radiogroup" aria-label="<?= e($trim) ?>"></div>
                    <div class="gr-note" data-note="trim_color">Passepoils de la toge, bordures de l'écharpe, gland de la toque.</div>
                </div>

                <div class="gr-step-card" data-needs="cap">
                    <h2><span class="n"></span>La toque</h2>
                    <span class="gr-label">Couleur de la toque</span>
                    <div class="gr-chips" data-chips="cap_color" role="radiogroup" aria-label="Couleur de la toque"></div>
                    <div class="gr-note" data-note="cap_color"></div>
                    <span class="gr-label mt-4">Gland</span>
                    <div class="gr-chips" data-chips="cap_style" role="radiogroup" aria-label="Style de gland"></div>
                    <div class="gr-note" data-note="cap_style"></div>
                </div>

                <div class="gr-step-card" id="stAcc" hidden>
                    <h2><span class="n"></span>Accessoires <small class="text-muted fs-6 fw-normal">(facultatif)</small></h2>
                    <div id="accList"></div>
                    <div class="gr-note" id="accNote"></div>
                </div>

                <div class="gr-step-card" data-needs="scarf" id="stTextScarf">
                    <h2><span class="n"></span>Personnaliser l'écharpe</h2>
                    <div class="gr-note gr-note--warn mt-0 mb-3" data-lock="scarf" hidden></div>
                    <div class="gr-zone" data-zone="scarf">
                        <span class="gr-label">Zone</span>
                        <div class="gr-chips mb-3" data-zonepick="scarf" role="radiogroup" aria-label="Zone de l'écharpe">
                            <label class="gr-chip"><input type="radio" name="zone_scarf" value="both" checked><span>Les deux bandes</span></label>
                            <label class="gr-chip"><input type="radio" name="zone_scarf" value="left"><span>Bande gauche</span></label>
                            <label class="gr-chip"><input type="radio" name="zone_scarf" value="right"><span>Bande droite</span></label>
                        </div>
                        <label class="gr-label" for="txt_scarf">Votre texte <span class="text-muted text-lowercase fw-normal" id="cnt_scarf"></span></label>
                        <input class="form-control" id="txt_scarf" type="text" autocomplete="off" placeholder="Ex. : Prénom NOM · Promotion 2026" data-text="scarf">
                        <div class="d-flex flex-wrap gap-1 mt-2" data-suggest="scarf"></div>
                        <div class="row g-3 mt-1">
                            <div class="col-12"><span class="gr-label">Police</span><div class="gr-chips" data-fonts="scarf" role="radiogroup" aria-label="Police de l'écharpe"></div></div>
                            <div class="col-12"><span class="gr-label">Couleur du fil</span><div class="gr-chips" data-tcolors="scarf" role="radiogroup" aria-label="Couleur du texte de l'écharpe"></div></div>
                            <div class="col-sm-7"><span class="gr-label">Alignement</span>
                                <div class="gr-chips" data-aligns="scarf" role="radiogroup" aria-label="Alignement">
                                    <label class="gr-chip"><input type="radio" name="align_scarf" value="left"><span>Début</span></label>
                                    <label class="gr-chip"><input type="radio" name="align_scarf" value="center" checked><span>Centré</span></label>
                                    <label class="gr-chip"><input type="radio" name="align_scarf" value="right"><span>Fin</span></label>
                                </div></div>
                            <div class="col-sm-5"><label class="gr-label" for="sz_scarf">Taille : <span id="szv_scarf"></span></label><input type="range" class="form-range" id="sz_scarf" data-size="scarf"></div>
                        </div>
                        <div class="gr-note" id="price_scarf"></div>
                    </div>
                </div>

                <div class="gr-step-card" data-needs="cap" id="stTextCap">
                    <h2><span class="n"></span>Personnaliser la toque</h2>
                    <div class="gr-note gr-note--warn mt-0 mb-3" data-lock="cap" hidden></div>
                    <div class="gr-zone" data-zone="cap">
                        <span class="gr-label">Zone</span>
                        <div class="gr-chips mb-3"><span class="gr-chip"><span style="min-width:0">Dessus de la toque</span></span></div>
                        <label class="gr-label" for="txt_cap">Votre texte <span class="text-muted text-lowercase fw-normal" id="cnt_cap"></span></label>
                        <input class="form-control" id="txt_cap" type="text" autocomplete="off" placeholder="Ex. : Promotion 2026" data-text="cap">
                        <div class="d-flex flex-wrap gap-1 mt-2" data-suggest="cap"></div>
                        <div class="row g-3 mt-1">
                            <div class="col-12"><span class="gr-label">Police</span><div class="gr-chips" data-fonts="cap" role="radiogroup" aria-label="Police de la toque"></div></div>
                            <div class="col-12"><span class="gr-label">Couleur du fil</span><div class="gr-chips" data-tcolors="cap" role="radiogroup" aria-label="Couleur du texte de la toque"></div></div>
                            <div class="col-sm-7"><span class="gr-label">Alignement</span>
                                <div class="gr-chips" data-aligns="cap" role="radiogroup" aria-label="Alignement">
                                    <label class="gr-chip"><input type="radio" name="align_cap" value="left"><span>Gauche</span></label>
                                    <label class="gr-chip"><input type="radio" name="align_cap" value="center" checked><span>Centré</span></label>
                                    <label class="gr-chip"><input type="radio" name="align_cap" value="right"><span>Droite</span></label>
                                </div></div>
                            <div class="col-sm-5"><label class="gr-label" for="sz_cap">Taille : <span id="szv_cap"></span></label><input type="range" class="form-range" id="sz_cap" data-size="cap"></div>
                        </div>
                        <div class="gr-note" id="price_cap"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="gr-mobilebar">
    <div class="flex-grow-1"><small>Total</small><strong id="totalM">—</strong></div>
    <button type="button" class="btn btn-gr" id="addBtnM"><i class="bi bi-bag-plus"></i> <span>Ajouter</span></button>
</div>

<script>window.GR_CONF = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>; window.GR_PRESET = <?= json_encode($preset, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php page_foot(['scripts' => ['assets/js/configurateur.js']]);
