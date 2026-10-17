<?php
declare(strict_types=1);

/**
 * Tarification et validation CÔTÉ SERVEUR.
 * Le navigateur n'envoie que des choix (codes d'options, textes, quantités) :
 * tous les prix sont recalculés ici à partir de la base de données.
 */
class PricingError extends Exception {}

function pricing_ref(): array
{
    static $ref = null;
    if ($ref === null) $ref = ['tiers' => tiers_all(false), 'options' => options_all(false)];
    return $ref;
}

/** Retourne l'option $grp/$code si elle existe, est active et compatible avec la gamme. */
function pricing_option(string $grp, $code, string $tier, string $what): array
{
    $ref = pricing_ref();
    $code = (string)$code;
    $o = $ref['options'][$grp][$code] ?? null;
    if (!$o) throw new PricingError("« $what » : choix invalide.");
    if (!(int)$o['active']) throw new PricingError("« {$o['label']} » n'est plus disponible.");
    if (!in_array($tier, tier_list_csv($o['tiers']), true)) {
        $tn = $ref['tiers'][$tier]['name'] ?? $tier;
        throw new PricingError("« {$o['label']} » n'est pas disponible avec la gamme $tn.");
    }
    return $o;
}

function clean_text(string $s, int $max): string
{
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    $s = trim(preg_replace('/\s{2,}/u', ' ', $s) ?? '');
    return mb_substr($s, 0, $max);
}

function product_stock(int $productId, string $size): int
{
    return (int)qv('SELECT stock FROM product_variants WHERE product_id = ? AND size = ?', [$productId, $size]);
}

/** Tarifie une ligne du panier. Lève PricingError si invalide. */
function price_item(array $it): array
{
    $qty = (int)($it['qty'] ?? 1);
    $maxQty = max(1, (int)setting('max_qty', '20'));
    if ($qty < 1 || $qty > $maxQty) throw new PricingError("Quantité invalide (1 à $maxQty).");
    $type = $it['type'] ?? '';
    return $type === 'product' ? price_product_item($it, $qty)
        : ($type === 'custom' ? price_custom_item($it, $qty) : throw new PricingError('Article inconnu.'));
}

function price_product_item(array $it, int $qty): array
{
    $p = q1('SELECT p.*, c.kind, c.name AS cat_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [(int)($it['product_id'] ?? 0)]);
    if (!$p || !(int)$p['active']) throw new PricingError("Ce produit n'est plus disponible.");
    $ref = pricing_ref();
    $kind = $p['kind'];
    $tier = $p['tier'];
    $tierName = $ref['tiers'][$tier]['name'] ?? $tier;
    $details = [];
    $size = null;

    $needsSize = in_array($kind, ['outfit', 'robe'], true);
    if ($needsSize) {
        $size = (string)($it['size'] ?? '');
        $v = q1('SELECT stock FROM product_variants WHERE product_id = ? AND size = ?', [$p['id'], $size]);
        if (!$v) throw new PricingError("Choisissez une taille pour « {$p['name']} ».");
        if ((int)$v['stock'] < $qty) throw new PricingError("Stock insuffisant pour « {$p['name']} » en taille $size.");
        $details[] = "Taille : $size";
    } else {
        $total = (int)qv("SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = ?", [$p['id']]);
        if ($total < $qty) throw new PricingError("Stock insuffisant pour « {$p['name']} ».");
    }

    // Couleur choisie parmi celles proposées par le produit.
    $allowed = tier_list_csv($p['colors']);
    $colorGrp = ['outfit' => 'robe_color', 'robe' => 'robe_color', 'scarf' => 'scarf_color', 'cap' => 'cap_color'][$kind] ?? null;
    $color = null;
    if ($colorGrp && $allowed) {
        $color = (string)($it['color'] ?? '') ?: $allowed[0];
        if (!in_array($color, $allowed, true)) throw new PricingError("Couleur indisponible pour « {$p['name']} ».");
        $co = $ref['options'][$colorGrp][$color] ?? null;
        if (!$co || !(int)$co['active']) throw new PricingError("Couleur indisponible pour « {$p['name']} ».");
        $details[] = ($colorGrp === 'scarf_color' ? 'Couleur' : ($kind === 'cap' ? 'Couleur' : 'Couleur de toge')) . ' : ' . $co['label'];
    }

    $render = product_render_config($p, $color);
    $unit = (float)$p['price'];
    return [
        'kind' => $kind, 'product_id' => (int)$p['id'], 'title' => $p['name'],
        'tier' => $tier, 'tier_name' => $tierName, 'size' => $size, 'qty' => $qty,
        'unit_price' => $unit, 'line_total' => round($unit * $qty, 2),
        'details' => $details, 'customizations' => [], 'config' => ['render' => $render, 'color' => $color],
        'render' => $render, 'slug' => $p['slug'],
        'image' => qv('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort, id LIMIT 1', [$p['id']]),
    ];
}

/** Config de rendu SVG d'un produit (couleurs par défaut + couleur choisie). */
function product_render_config(array $p, ?string $color = null): array
{
    $kind = $p['kind'] ?? 'outfit';
    $r = [
        'view' => $kind === 'outfit' ? 'outfit' : $kind,
        'robe' => $p['def_robe'] ?: 'noir', 'scarf' => $p['def_scarf'] ?: 'noir',
        'trim' => $p['def_trim'] ?: 'or', 'cap' => $p['def_cap'] ?: ($p['def_robe'] ?: 'noir'),
        'tier' => $p['tier'],
    ];
    if ($color) {
        if (in_array($kind, ['outfit', 'robe'], true)) { $r['robe'] = $color; $r['cap'] = $color; }
        elseif ($kind === 'scarf') $r['scarf'] = $color;
        elseif ($kind === 'cap') $r['cap'] = $color;
    }
    if ($kind === 'accessory') $r['icon'] = accessory_icon($p['name']);
    return $r;
}
function accessory_icon(string $name): string
{
    $n = mb_strtolower($name);
    return str_contains($n, 'étui') || str_contains($n, 'etui') ? 'tube'
        : (str_contains($n, 'boîte') || str_contains($n, 'boite') ? 'box'
        : (str_contains($n, 'breloque') ? 'charm' : 'bag'));
}

function price_custom_item(array $it, int $qty): array
{
    $c = $it['config'] ?? null;
    if (!is_array($c)) throw new PricingError('Composition invalide.');
    $ref = pricing_ref();

    $tier = (string)($c['tier'] ?? '');
    $T = $ref['tiers'][$tier] ?? null;
    if (!$T || !(int)$T['active']) throw new PricingError('Gamme invalide.');

    $parts = [
        'robe' => !empty($c['parts']['robe']),
        'scarf' => !empty($c['parts']['scarf']),
        'cap' => !empty($c['parts']['cap']),
    ];
    if (!in_array(true, $parts, true)) throw new PricingError('Sélectionnez au moins une pièce (toge, écharpe ou toque).');

    $price = 0.0;
    $details = [];
    $out = ['tier' => $tier, 'parts' => $parts];

    // Toge
    $size = null;
    if ($parts['robe']) {
        $price += (float)$T['price_robe'];
        $o = pricing_option('robe_color', $c['robe_color'] ?? '', $tier, 'Couleur de la toge');
        $price += (float)$o['price_extra'];
        $out['robe_color'] = $o['code'];
        $details[] = "Toge : {$o['label']}";
        $s = pricing_option('size', $c['size'] ?? '', $tier, 'Taille');
        $price += (float)$s['price_extra'];
        $size = $s['code'];
        $out['size'] = $size;
        $details[] = "Taille : $size";
    }
    // Écharpe
    if ($parts['scarf']) {
        $price += (float)$T['price_scarf'];
        $o = pricing_option('scarf_color', $c['scarf_color'] ?? '', $tier, "Couleur de l'écharpe");
        $price += (float)$o['price_extra'];
        $out['scarf_color'] = $o['code'];
        $details[] = "Écharpe : {$o['label']}";
    }
    // Billets / éléments décoratifs
    $tl = setting('label_trim', 'Billets décoratifs');
    $o = pricing_option('trim_color', $c['trim_color'] ?? '', $tier, $tl);
    $price += (float)$o['price_extra'];
    $out['trim_color'] = $o['code'];
    $details[] = "$tl : {$o['label']}";
    // Toque
    if ($parts['cap']) {
        $price += (float)$T['price_cap'];
        $capColor = $c['cap_color'] ?? null;
        if ($capColor === null || $capColor === '') {
            $capColor = $parts['robe'] ? ($out['robe_color']) : 'noir';
        }
        $o = pricing_option('cap_color', $capColor, $tier, 'Couleur de la toque');
        $price += (float)$o['price_extra'];
        $out['cap_color'] = $o['code'];
        $details[] = "Toque : {$o['label']}";
        $st = pricing_option('cap_style', $c['cap_style'] ?? 'standard', $tier, 'Style de toque');
        $price += (float)$st['price_extra'];
        $out['cap_style'] = $st['code'];
        if ($st['code'] !== 'standard') $details[] = "Gland : {$st['label']}";
    }

    // Accessoires
    $acc = [];
    $accIds = array_values(array_unique(array_map('intval', (array)($c['accessories'] ?? []))));
    foreach (array_slice($accIds, 0, 12) as $aid) {
        $a = q1("SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND c.kind = 'accessory' AND p.active = 1", [$aid]);
        if (!$a) throw new PricingError("Un accessoire n'est plus disponible.");
        $allowed = tier_list_csv($a['tiers_allowed']);
        if ($allowed && !in_array($tier, $allowed, true)) {
            throw new PricingError("« {$a['name']} » n'est pas compatible avec la gamme {$T['name']}.");
        }
        if ((int)qv('SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = ?', [$aid]) < $qty) {
            throw new PricingError("Stock insuffisant pour « {$a['name']} ».");
        }
        $price += (float)$a['price'];
        $acc[] = (int)$a['id'];
        $details[] = "Accessoire : {$a['name']}";
    }
    $out['accessories'] = $acc;

    // Personnalisations texte
    $custs = [];
    $lim = [
        'min' => (int)setting('text_size_min', '10'), 'max' => (int)setting('text_size_max', '22'),
    ];
    $out['custom'] = [];
    foreach (['scarf', 'cap'] as $zoneKey) {
        $cz = $c['custom'][$zoneKey] ?? null;
        if (!is_array($cz)) continue;
        $maxLen = (int)setting($zoneKey === 'scarf' ? 'text_max_scarf' : 'text_max_cap', '20');
        $text = clean_text((string)($cz['text'] ?? ''), 400);
        if ($text === '') continue;
        $label = $zoneKey === 'scarf' ? "l'écharpe" : 'la toque';
        if (!$parts[$zoneKey]) throw new PricingError("Texte sur $label : la pièce n'est pas dans la composition.");
        if (!(int)$T['text_' . $zoneKey]) throw new PricingError("La gamme {$T['name']} ne permet pas de personnaliser $label.");
        if (mb_strlen($text) > $maxLen) throw new PricingError("Texte sur $label : $maxLen caractères maximum.");
        $font = pricing_option('font', $cz['font'] ?? '', $tier, 'Police');
        $color = pricing_option('text_color', $cz['color'] ?? '', $tier, 'Couleur du texte');
        $align = in_array($cz['align'] ?? '', ['left', 'center', 'right'], true) ? $cz['align'] : 'center';
        $size = (int)($cz['size'] ?? $lim['min']);
        if ($size < $lim['min'] || $size > $lim['max']) throw new PricingError("Taille du texte : entre {$lim['min']} et {$lim['max']}.");
        $zones = $zoneKey === 'scarf' ? ['left', 'right', 'both'] : ['top'];
        $zone = in_array($cz['zone'] ?? '', $zones, true) ? $cz['zone'] : $zones[0];
        $price += (float)setting($zoneKey === 'scarf' ? 'text_price_scarf' : 'text_price_cap', '0');
        $price += (float)$font['price_extra'] + (float)$color['price_extra'];
        $zoneLabel = ['left' => 'bande gauche', 'right' => 'bande droite', 'both' => 'les deux bandes', 'top' => 'dessus'][$zone];
        $details[] = ucfirst($zoneKey === 'scarf' ? 'Écharpe' : 'Toque') . " — texte « $text » ($zoneLabel, {$font['label']}, {$color['label']}, $size px)";
        $out['custom'][$zoneKey] = ['text' => $text, 'zone' => $zone, 'font' => $font['code'], 'color' => $color['code'], 'align' => $align, 'size' => $size];
        $custs[] = ['zone' => $zoneKey, 'body' => $text, 'font' => $font['code'], 'color' => $color['code'], 'align' => $align, 'font_size' => $size];
    }

    // Titre lisible
    $names = [];
    if ($parts['robe']) $names[] = 'toge';
    if ($parts['scarf']) $names[] = 'écharpe';
    if ($parts['cap']) $names[] = 'toque';
    $title = count($names) === 3 ? 'Tenue complète personnalisée'
        : mb_strtoupper(mb_substr(implode(' + ', $names), 0, 1)) . mb_substr(implode(' + ', $names), 1)
            . (count($names) > 1 ? ' personnalisées' : ' personnalisée');
    $title .= " — {$T['name']}";

    $render = [
        'view' => count($names) === 3 ? 'outfit' : (count($names) === 1 ? $names[0] === 'toge' ? 'robe' : ($names[0] === 'écharpe' ? 'scarf' : 'cap') : 'outfit'),
        'parts' => $parts, 'tier' => $tier,
        'robe' => $out['robe_color'] ?? 'noir', 'scarf' => $out['scarf_color'] ?? 'noir', 'trim' => $out['trim_color'],
        'cap' => $out['cap_color'] ?? 'noir', 'cap_style' => $out['cap_style'] ?? 'standard', 'custom' => $out['custom'],
    ];

    $unit = round($price, 2);
    return [
        'kind' => 'custom', 'product_id' => null, 'title' => $title, 'tier' => $tier, 'tier_name' => $T['name'],
        'size' => $size, 'qty' => $qty, 'unit_price' => $unit, 'line_total' => round($unit * $qty, 2),
        'details' => $details, 'customizations' => $custs, 'config' => $out, 'render' => $render, 'slug' => null, 'image' => null,
    ];
}

/** Tarifie tout le panier. */
function price_cart(array $items): array
{
    $lines = [];
    $errors = [];
    $subtotal = 0.0;
    foreach (array_slice($items, 0, 50) as $it) {
        $id = (string)($it['id'] ?? '');
        try {
            $l = price_item($it);
            $l['id'] = $id;
            $lines[] = $l;
            $subtotal += $l['line_total'];
        } catch (PricingError $e) {
            $errors[] = ['id' => $id, 'message' => $e->getMessage()];
        }
    }
    $shipping = 0.0;
    $fee = (float)setting('shipping_fee', '0');
    $free = (float)setting('free_shipping_from', '0');
    if ($subtotal > 0 && !($free > 0 && $subtotal >= $free)) $shipping = $fee;
    return [
        'lines' => $lines, 'errors' => $errors,
        'subtotal' => round($subtotal, 2), 'shipping' => round($shipping, 2), 'total' => round($subtotal + $shipping, 2),
        'free_from' => $free, 'fee' => $fee,
    ];
}

/** Données de référence pour le configurateur (prix issus de la base). */
function configurator_data(): array
{
    $ref = pricing_ref();
    $tiers = [];
    foreach ($ref['tiers'] as $c => $t) {
        if (!(int)$t['active']) continue;
        $tiers[$c] = [
            'code' => $c, 'name' => $t['name'], 'description' => $t['description'], 'materials' => $t['materials'],
            'price_robe' => (float)$t['price_robe'], 'price_scarf' => (float)$t['price_scarf'], 'price_cap' => (float)$t['price_cap'],
            'text_scarf' => (int)$t['text_scarf'], 'text_cap' => (int)$t['text_cap'],
        ];
    }
    $opts = [];
    foreach ($ref['options'] as $g => $list) {
        foreach ($list as $o) {
            if (!(int)$o['active']) continue;
            $opts[$g][] = [
                'code' => $o['code'], 'label' => $o['label'], 'hex' => $o['hex'], 'font' => $o['font_css'],
                'extra' => (float)$o['price_extra'], 'tiers' => tier_list_csv($o['tiers']),
            ];
        }
    }
    $acc = [];
    foreach (q("SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id WHERE c.kind='accessory' AND p.active = 1 ORDER BY p.sort, p.id") as $a) {
        $stock = (int)qv('SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = ?', [$a['id']]);
        $acc[] = ['id' => (int)$a['id'], 'name' => $a['name'], 'price' => (float)$a['price'], 'tiers' => tier_list_csv($a['tiers_allowed']),
            'icon' => accessory_icon($a['name']), 'stock' => $stock, 'description' => $a['description']];
    }
    return [
        'tiers' => $tiers, 'options' => $opts, 'accessories' => $acc,
        'limits' => [
            'max_scarf' => (int)setting('text_max_scarf', '28'), 'max_cap' => (int)setting('text_max_cap', '18'),
            'size_min' => (int)setting('text_size_min', '10'), 'size_max' => (int)setting('text_size_max', '22'),
            'price_text_scarf' => (float)setting('text_price_scarf', '0'), 'price_text_cap' => (float)setting('text_price_cap', '0'),
            'max_qty' => (int)setting('max_qty', '20'),
        ],
        'label_trim' => setting('label_trim', 'Billets décoratifs'),
    ];
}

/** Palette (code → hex) pour le rendu SVG, tous groupes confondus. */
function palette_data(): array
{
    $out = [];
    foreach (pricing_ref()['options'] as $g => $list) {
        if (!in_array($g, ['robe_color', 'scarf_color', 'trim_color', 'cap_color', 'text_color'], true)) continue;
        foreach ($list as $o) $out[$g][$o['code']] = $o['hex'];
    }
    $fonts = [];
    foreach (pricing_ref()['options']['font'] ?? [] as $o) $fonts[$o['code']] = $o['font_css'];
    return ['colors' => $out, 'fonts' => $fonts];
}
