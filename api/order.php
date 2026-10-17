<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

/**
 * POST JSON : {csrf, customer:{...}, items:[...], payment_method, note}
 * Valide tout côté serveur, recalcule les prix, enregistre un instantané de la commande.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Méthode non autorisée.'], 405);

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) json_out(['ok' => false, 'error' => 'Requête invalide.'], 400);
if (!csrf_check($in['csrf'] ?? null)) json_out(['ok' => false, 'error' => 'Session expirée. Rechargez la page et réessayez.'], 419);

// Limitation : 8 commandes / heure / IP.
$bucket = 'order:' . client_ip();
ex('DELETE FROM throttle WHERE ts < ?', [time() - 3600]);
if ((int)qv('SELECT COUNT(*) FROM throttle WHERE bucket = ?', [$bucket]) >= 8) {
    json_out(['ok' => false, 'error' => 'Trop de commandes depuis cette connexion. Réessayez plus tard ou contactez-nous sur WhatsApp.'], 429);
}

// ------------------------------------------------------------ client ------
$c = $in['customer'] ?? [];
$f = [
    'first_name' => clean_text((string)($c['first_name'] ?? ''), 100),
    'last_name' => clean_text((string)($c['last_name'] ?? ''), 100),
    'email' => trim((string)($c['email'] ?? '')),
    'phone' => trim((string)($c['phone'] ?? '')),
    'city' => clean_text((string)($c['city'] ?? ''), 100),
    'address' => clean_text((string)($c['address'] ?? ''), 400),
];
$err = [];
if (mb_strlen($f['last_name']) < 2) $err['last_name'] = 'Indiquez votre nom.';
if (mb_strlen($f['first_name']) < 2) $err['first_name'] = 'Indiquez votre prénom.';
if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 190) $err['email'] = 'Adresse e-mail invalide.';
$digits = preg_replace('/\D+/', '', $f['phone']);
if (!preg_match('/^(\+|00)?[0-9 .\-()]{8,20}$/', $f['phone']) || strlen($digits) < 9 || strlen($digits) > 15) $err['phone'] = 'Numéro de téléphone invalide (ex. 06 12 34 56 78).';
if (mb_strlen($f['city']) < 2) $err['city'] = 'Indiquez votre ville.';
if (mb_strlen($f['address']) < 8) $err['address'] = 'Indiquez votre adresse complète de livraison.';

$methods = payment_methods();
$pay = (string)($in['payment_method'] ?? '');
if (!isset($methods[$pay])) $err['payment_method'] = 'Choisissez un mode de commande.';
if ($err) json_out(['ok' => false, 'error' => 'Veuillez corriger le formulaire.', 'fields' => $err], 422);

// ----------------------------------------------------------- panier -------
$items = is_array($in['items'] ?? null) ? $in['items'] : [];
if (!$items) json_out(['ok' => false, 'error' => 'Votre panier est vide.'], 422);
$cart = price_cart($items);
if ($cart['errors'] || !$cart['lines']) {
    json_out(['ok' => false, 'error' => $cart['errors'][0]['message'] ?? 'Panier invalide.', 'cart_errors' => $cart['errors']], 422);
}

// ----------------------------------------------------- enregistrement ------
$pdo = db();
try {
    $pdo->beginTransaction();

    // Revérification du stock dans la transaction + décrément
    foreach ($cart['lines'] as $l) {
        if ($l['product_id']) {
            $size = $l['size'] ?: '*';
            if ($l['size']) {
                $n = ex('UPDATE product_variants SET stock = stock - ? WHERE product_id = ? AND size = ? AND stock >= ?', [$l['qty'], $l['product_id'], $size, $l['qty']]);
            } else {
                // Pièces sans taille : on prélève sur la première variante qui a du stock.
                $v = q1('SELECT id FROM product_variants WHERE product_id = ? AND stock >= ? ORDER BY id LIMIT 1', [$l['product_id'], $l['qty']]);
                $n = $v ? ex('UPDATE product_variants SET stock = stock - ? WHERE id = ?', [$l['qty'], $v['id']]) : 0;
            }
            if (!$n) throw new PricingError("Stock insuffisant pour « {$l['title']} ».");
        } else {
            foreach ($l['config']['accessories'] ?? [] as $aid) {
                $v = q1('SELECT id FROM product_variants WHERE product_id = ? AND stock >= ? ORDER BY id LIMIT 1', [$aid, $l['qty']]);
                if (!$v) throw new PricingError('Stock insuffisant pour un accessoire.');
                ex('UPDATE product_variants SET stock = stock - ? WHERE id = ?', [$l['qty'], $v['id']]);
            }
        }
    }

    $now = now();
    $custId = insert('customers', $f + ['created_at' => $now]);

    $number = '';
    for ($i = 0; $i < 10; $i++) {
        $number = 'GR-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        if (!qv('SELECT 1 FROM orders WHERE number = ?', [$number])) break;
    }
    $token = bin2hex(random_bytes(16));
    $note = clean_text((string)($in['note'] ?? ''), 500);
    $orderId = insert('orders', $f + [
        'number' => $number, 'access_token' => $token, 'customer_id' => $custId, 'status' => 'pending',
        'subtotal' => $cart['subtotal'], 'shipping' => $cart['shipping'], 'total' => $cart['total'],
        'payment_method' => $pay, 'payment_status' => 'unpaid', 'customer_note' => $note, 'admin_note' => '',
        'created_at' => $now, 'updated_at' => $now,
    ]);

    foreach ($cart['lines'] as $l) {
        $itemId = insert('order_items', [
            'order_id' => $orderId, 'product_id' => $l['product_id'], 'kind' => $l['kind'], 'title' => $l['title'],
            'tier' => $l['tier'], 'tier_name' => $l['tier_name'], 'size' => $l['size'], 'qty' => $l['qty'],
            'unit_price' => $l['unit_price'], 'line_total' => $l['line_total'],
            'details' => implode("\n", $l['details']),
            // Instantané complet : l'aperçu reste reconstructible même si le catalogue change.
            'config_json' => json_encode(['render' => $l['render'], 'config' => $l['config']], JSON_UNESCAPED_UNICODE),
        ]);
        foreach ($l['customizations'] as $cu) insert('customizations', ['order_item_id' => $itemId] + $cu);
    }
    insert('order_status_history', ['order_id' => $orderId, 'status' => 'pending', 'note' => 'Commande créée par le client', 'admin_id' => null, 'created_at' => $now]);
    ex('INSERT INTO throttle (bucket, ts) VALUES (?, ?)', [$bucket, time()]);
    $pdo->commit();
} catch (PricingError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(['ok' => false, 'error' => $e->getMessage()], 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[gradis] order failed: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => "Une erreur est survenue. Votre commande n'a pas été enregistrée, réessayez ou écrivez-nous sur WhatsApp."], 500);
}

json_out(['ok' => true, 'number' => $number, 'token' => $token, 'redirect' => url('confirmation.php?n=' . rawurlencode($number) . '&t=' . $token)]);
