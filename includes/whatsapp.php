<?php
declare(strict_types=1);

/** Numéro WhatsApp (chiffres uniquement, format international sans « + »). */
function wa_number(): string
{
    return preg_replace('/\D+/', '', setting('whatsapp_number', ''));
}
/** Lien WhatsApp avec message correctement encodé. */
function wa_link(string $message = ''): string
{
    $base = 'https://wa.me/' . wa_number();
    return $message === '' ? $base : $base . '?text=' . rawurlencode($message);
}
function wa_greeting(): string { return setting('whatsapp_greeting', 'Bonjour Gradis,'); }

/** Message de commande (confirmation, admin → client, etc.). */
function wa_order_message(array $order, array $items): string
{
    $lines = [wa_greeting(), '', "Commande n° {$order['number']}"];
    foreach ($items as $it) {
        $lines[] = '';
        $lines[] = "• {$it['qty']} × {$it['title']} ({$it['tier_name']}) — " . money($it['line_total']);
        foreach (preg_split('/\R/', (string)$it['details']) ?: [] as $d) if (trim($d) !== '') $lines[] = '   ' . trim($d);
    }
    $lines[] = '';
    $lines[] = 'Total : ' . money($order['total']) . ' (livraison : ' . ($order['shipping'] > 0 ? money($order['shipping']) : 'offerte') . ')';
    $lines[] = "Ville : {$order['city']}";
    return implode("\n", $lines);
}
