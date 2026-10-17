<?php
declare(strict_types=1);

/**
 * Architecture de paiement.
 *
 * Modes disponibles aujourd'hui (activables dans Admin → Livraison & paiement) :
 *   - cod      : paiement à la livraison
 *   - whatsapp : confirmation de la commande par WhatsApp
 *
 * Paiement en ligne : NON configuré. Aucun paiement n'est simulé : une commande
 * est toujours enregistrée avec payment_status = "unpaid". Pour brancher un
 * prestataire (CMI, Payzone, Stripe…), implémentez PaymentProvider ci-dessous,
 * enregistrez-le dans payment_providers() puis activez « pay_online ».
 * Le prestataire devra confirmer le paiement via un webhook signé qui met à
 * jour orders.payment_status (jamais depuis le navigateur du client).
 */
interface PaymentProvider
{
    public function code(): string;
    public function label(): string;
    /** Prépare le paiement et retourne l'URL de redirection du prestataire. */
    public function createSession(array $order): string;
}

function payment_providers(): array
{
    return []; // aucun prestataire configuré
}

function payment_methods(): array
{
    $m = [];
    if (setting('pay_cod', '1') === '1') {
        $m['cod'] = ['label' => 'Paiement à la livraison', 'hint' => 'Vous payez à la réception de votre commande.'];
    }
    if (setting('pay_whatsapp', '1') === '1') {
        $m['whatsapp'] = ['label' => 'Confirmation par WhatsApp', 'hint' => 'Nous vous contactons sur WhatsApp pour confirmer et convenir du règlement.'];
    }
    if (setting('pay_online', '0') === '1') {
        foreach (payment_providers() as $p) $m[$p->code()] = ['label' => $p->label(), 'hint' => 'Paiement en ligne sécurisé.'];
    }
    return $m;
}
