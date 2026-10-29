# Gradis — boutique de tenues de graduation (modèle 1 : bleu nuit & doré)

Site e-commerce complet : catalogue dynamique, **configurateur de tenue** avec aperçu en direct, panier, commande, WhatsApp, et **panneau d'administration**.

**Technologies** : PHP 8.1+ (PDO), SQLite (aucune installation), HTML / CSS / Bootstrap 5 / JavaScript natif. Bootstrap, Bootstrap Icons, Chart.js et les polices sont **inclus localement** (`assets/vendor`, `assets/fonts`) : aucun CDN.

## Installation

### En local (test)
```bash
php -S localhost:8080
```
Ouvrez http://localhost:8080 — la base SQLite est créée automatiquement au premier accès, avec des **données de démonstration**.
Puis ouvrez http://localhost:8080/admin/ : la page `setup.php` vous fait créer **votre** compte administrateur (aucun mot de passe par défaut).
En ligne de commande : `php bin/create-admin.php "Nom" email@exemple.com "MotDePasse123456"`.

### Sur un hébergement (cPanel / Apache)
1. Envoyez tout le contenu du dossier dans `public_html` (ou un sous-dossier — le chemin est détecté automatiquement).
2. PHP 8.1+ avec l'extension `pdo_sqlite` (activée par défaut chez la plupart des hébergeurs). Les dossiers `database/` et `uploads/` doivent être inscriptibles.
3. Ouvrez `/admin/` et créez votre compte.
4. **Sécurité, important** : les fichiers `.htaccess` fournis interdisent l'accès direct à la base sous **Apache**. Sous **Nginx** (ou avec `php -S`), ils sont ignorés : placez alors la base hors du dossier web via `.env` (`DB_PATH=/chemin/hors/web`) et interdisez `*.sqlite`.
5. Copiez `.env.example` en `.env` si besoin. Mettez `SEED_DEMO=0` **avant le premier accès** pour démarrer sans produits de démonstration.
6. Activez HTTPS (les cookies de session passent automatiquement en « secure »).

## Avant la mise en ligne : à remplacer
Tout ce qui suit est **de démonstration** et modifiable depuis l'administration :
- **Prix** des gammes, produits et options (Gammes & options, Produits) — aucun tarif définitif n'est fourni.
- **Numéro WhatsApp** (`212600000000` factice) : Réglages du site → WhatsApp.
- **Avis clients** (marqués « exemple ») : Avis, FAQ, catégories. Ne saisissez que des avis réels.
- **Photos** : sans photo, le site affiche une illustration SVG générée avec les couleurs du produit. Ajoutez vos photos (fiche produit → Photos, et image du bandeau d'accueil dans Réglages → Images).
- Coordonnées, adresse, horaires, réseaux sociaux, frais et activation de la livraison.
- Décochez « Afficher la mention exemple » dans Réglages une fois terminé.

## Fonctionnalités
**Public** : accueil (hero, avantages, produits, gammes, avis, FAQ, WhatsApp), catalogue (recherche instantanée, filtres type / gamme / couleur / taille / prix / disponibilité, tri par prix), fiche produit, configurateur, panier, commande sans compte, confirmation avec numéro unique, page contact, « Comment commander ». La livraison en ligne et ses frais peuvent être activés ou désactivés dans les réglages ; si elle est désactivée, les clients sont orientés vers WhatsApp.

**Configurateur** : gamme → pièces (tenue complète ou pièces séparées) → couleur de toge, taille XS–XXL, couleur d'écharpe, « billets décoratifs », toque et gland, accessoires, texte brodé sur l'écharpe (zones gauche / droite / les deux) et sur la toque : police, couleur du fil, alignement, taille, limite de caractères. Aperçu et prix **mis à jour en direct**. Une option incompatible avec la gamme est grisée avec l'explication. Chaque composition différente est une ligne distincte du panier ; elle peut être modifiée avant validation.

**Prix** (recalculés par le serveur à chaque commande, jamais lus depuis le navigateur) :
`toge + écharpe + toque de la gamme + suppléments des options + accessoires + textes brodés`.

**Commande** : validation côté navigateur **et** serveur (montants, quantités, noms, e-mail, téléphone), instantané des prix/options/personnalisations conservé même si le catalogue change, limitation à 8 commandes/heure/IP.
Modes proposés selon Réglages : **paiement à la livraison** et **confirmation par WhatsApp**. Aucun paiement n'est simulé : toute commande est enregistrée « non payée ».

**WhatsApp** (numéro configurable) : barre de navigation, bouton flottant, accueil, fiche produit, configurateur (« Poser une question sur ma composition »), panier, confirmation, contact, et depuis l'admin vers le client. Les messages préremplis contiennent produits, quantités, tailles, couleurs, gamme, textes personnalisés, prix et ville, correctement encodés.

**Administration** (`/admin/`) : tableau de bord (commandes par statut, chiffre d'affaires réel, graphiques 30 jours, top articles, villes) · commandes (recherche, filtres statut / ville / dates, détail avec aperçu visuel de la personnalisation, changement de statut avec historique, note interne, statut de paiement manuel, WhatsApp client, export CSV ; transitions de statut contrôlées (une commande ne revient pas en arrière ; annulée ou livrée = définitif) · produits (CRUD, photos, couleurs, tailles proposées, compatibilité accessoires, activer/masquer) · gammes (prix, description, matériaux) · options (couleurs, tailles, polices, glands… avec supplément et gammes compatibles) · avis, FAQ, catégories · réglages (entreprise, WhatsApp, textes d'accueil, livraison, modes de commande, paramètres de texte brodé, logo, image du hero) · administrateurs.
Le chiffre d'affaires compte les commandes confirmées, en préparation, expédiées et livrées (ni en attente, ni annulées).

## Sécurité
Requêtes préparées (PDO), jetons CSRF sur les actions modificatrices, mots de passe hachés (`password_hash`), limitation des tentatives de connexion (5 / 15 min / IP), sessions strictes et cookies `HttpOnly` / `SameSite` / `Secure` sous HTTPS, régénération de session à la connexion, Content Security Policy avec nonce pour les scripts, HSTS sous HTTPS, protections anti-cadrage et MIME, échappement des données intégrées aux scripts, téléversements JPG/PNG/WebP vérifiés par contenu et dimensions, renommés aléatoirement et sans exécution PHP dans `uploads/`, validation des options contre la base (gamme, actif, compatibilité), quantités et montants vérifiés côté serveur, secrets uniquement dans `.env`.

## Structure
```
index.php catalogue.php produit.php configurateur.php panier.php commande.php confirmation.php contact.php
api/        price.php (tarification du panier), order.php (création de commande)
admin/      login, setup, index (tableau de bord), orders, order, products, product, options, settings, content, users
includes/   bootstrap, db, schema (+ données initiales), pricing, catalog, layout, auth, settings, whatsapp, payment
assets/     css, js (outfit.js = rendu SVG, configurateur.js, app.js…), vendor, fonts, img
lang/fr.php textes (prêt pour ar.php / en.php)
bin/create-admin.php   database/  uploads/
```
Tables : `admin_users, customers, categories, products, product_images, product_variants (tailles proposées par produit), options, tiers (gammes), orders, order_items, customizations, order_status_history, reviews, faqs, settings`.

## Évolutions prévues dans le code
- **Paiement en ligne** : implémenter `PaymentProvider` (`includes/payment.php`), l'enregistrer dans `payment_providers()`. Le prestataire (CMI, Payzone, Stripe…) doit confirmer le paiement par **webhook signé** qui met à jour `orders.payment_status`.
- **Arabe / anglais** : copier `lang/fr.php`, traduire, adapter `lang()` dans `includes/bootstrap.php` (`dir` = `rtl` pour l'arabe). Les textes saisis en admin (produits, FAQ…) restent en une langue.

## Limites connues (à connaître honnêtement)
- Aucun e-mail n'est envoyé (ni au client, ni à l'admin) : le suivi se fait par l'admin et WhatsApp.
- Compte client optionnel **non implémenté** (commande sans compte uniquement).
- Testé avec SQLite. Le support MySQL est prévu (`DB_DRIVER=mysql`) mais **non testé** .
- Les aperçus de tenue sont des illustrations SVG stylisées, pas des photos réalistes.
- Tests réalisés : parcours complet configurateur → panier → commande → confirmation → admin dans un navigateur automatisé, plus tests d'API (CSRF, prix falsifié, option interdite, quantité). Pas de test de charge ni de test sur mobiles réels.

## Déploiement avec Docker (Render, Railway, Fly.io)
Fichiers fournis : `Dockerfile`, `docker-entrypoint.sh`, `render.yaml`, `.gitignore`, `.dockerignore`.
1. Poussez le dossier sur GitHub (dépôt **privé** conseillé). Le `.gitignore` exclut la base, les photos et `.env`.
2. Sur Render : **New → Blueprint**, choisissez le dépôt. Le disque persistant monté sur `/data` conserve la base et les photos (sans disque, tout est perdu à chaque redéploiement ; Render le réserve aux offres payantes).
3. Ouvrez l'adresse fournie, puis `/admin/` et **créez votre compte immédiatement**.
Netlify et Vercel n'exécutent pas PHP : ils ne conviennent pas à ce site.
Ces fichiers Docker n'ont **pas été testés** dans mon environnement (pas de Docker disponible).


## Pas de gestion de stock

Gradis ne gère pas de stock : un produit actif est toujours commandable. Dans l'administration, vous cochez simplement les **tailles proposées** pour chaque tenue ou toge ; pour masquer un produit, désactivez-le (« Visible sur le site »).
