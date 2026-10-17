<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

/** POST JSON {items:[...]} → lignes tarifées par le serveur (source de vérité des prix). */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Méthode non autorisée.'], 405);

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['items']) || !is_array($body['items'])) json_out(['ok' => false, 'error' => 'Requête invalide.'], 400);

$cart = price_cart($body['items']);
// On ne renvoie au navigateur que ce dont l'affichage a besoin.
$lines = array_map(fn($l) => [
    'id' => $l['id'], 'kind' => $l['kind'], 'title' => $l['title'], 'tier' => $l['tier'], 'tier_name' => $l['tier_name'],
    'size' => $l['size'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'line_total' => $l['line_total'],
    'details' => $l['details'], 'render' => $l['render'], 'slug' => $l['slug'], 'image' => $l['image'] ?? null,
], $cart['lines']);
json_out(['ok' => true, 'lines' => $lines, 'errors' => $cart['errors'], 'subtotal' => $cart['subtotal'],
    'shipping' => $cart['shipping'], 'total' => $cart['total'], 'free_from' => $cart['free_from'], 'fee' => $cart['fee']]);
