<?php
declare(strict_types=1);

/**
 * Création du schéma + données initiales.
 * SQL volontairement portable SQLite / MySQL.
 */
function gradis_install(PDO $pdo, string $driver): void
{
    $mysql = $driver === 'mysql';
    $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $ref = 'INT'; // clés étrangères (FK logiques, contraintes posées ci-dessous)

    $tables = [
        "CREATE TABLE settings (skey VARCHAR(100) PRIMARY KEY, svalue TEXT)$tail",

        "CREATE TABLE admin_users (id $pk, email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL,
            password_hash VARCHAR(255) NOT NULL, created_at VARCHAR(19) NOT NULL, last_login VARCHAR(19))$tail",

        "CREATE TABLE throttle (id $pk, bucket VARCHAR(120) NOT NULL, ts INT NOT NULL)$tail",
        "CREATE INDEX idx_throttle ON throttle (bucket, ts)",

        "CREATE TABLE categories (id $pk, slug VARCHAR(120) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL,
            kind VARCHAR(20) NOT NULL, sort INT NOT NULL DEFAULT 0)$tail",

        "CREATE TABLE tiers (code VARCHAR(30) PRIMARY KEY, name VARCHAR(80) NOT NULL, description TEXT, materials TEXT,
            price_robe DECIMAL(10,2) NOT NULL DEFAULT 0, price_scarf DECIMAL(10,2) NOT NULL DEFAULT 0,
            price_cap DECIMAL(10,2) NOT NULL DEFAULT 0, text_scarf INT NOT NULL DEFAULT 1, text_cap INT NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0, active INT NOT NULL DEFAULT 1)$tail",

        "CREATE TABLE options (id $pk, grp VARCHAR(30) NOT NULL, code VARCHAR(60) NOT NULL, label VARCHAR(120) NOT NULL,
            hex VARCHAR(9), font_css VARCHAR(200), price_extra DECIMAL(10,2) NOT NULL DEFAULT 0,
            tiers VARCHAR(100) NOT NULL DEFAULT 'economie,premium,prestige',
            sort INT NOT NULL DEFAULT 0, active INT NOT NULL DEFAULT 1, UNIQUE (grp, code))$tail",

        "CREATE TABLE products (id $pk, category_id $ref NOT NULL, tier VARCHAR(30) NOT NULL, name VARCHAR(160) NOT NULL,
            slug VARCHAR(190) NOT NULL UNIQUE, description TEXT, price DECIMAL(10,2) NOT NULL DEFAULT 0,
            active INT NOT NULL DEFAULT 1, colors VARCHAR(255), def_robe VARCHAR(60), def_scarf VARCHAR(60),
            def_trim VARCHAR(60), def_cap VARCHAR(60), tiers_allowed VARCHAR(100),
            sort INT NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL)$tail",

        "CREATE TABLE product_images (id $pk, product_id $ref NOT NULL, path VARCHAR(255) NOT NULL, sort INT NOT NULL DEFAULT 0)$tail",
        "CREATE TABLE product_variants (id $pk, product_id $ref NOT NULL, size VARCHAR(10) NOT NULL,
            stock INT NOT NULL DEFAULT 0, UNIQUE (product_id, size))$tail",

        "CREATE TABLE customers (id $pk, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL, city VARCHAR(100) NOT NULL,
            address TEXT NOT NULL, created_at VARCHAR(19) NOT NULL)$tail",
        "CREATE INDEX idx_customers_email ON customers (email)",

        "CREATE TABLE orders (id $pk, number VARCHAR(30) NOT NULL UNIQUE, access_token VARCHAR(64) NOT NULL,
            customer_id $ref NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL, city VARCHAR(100) NOT NULL, address TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending', subtotal DECIMAL(10,2) NOT NULL, shipping DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL, payment_method VARCHAR(20) NOT NULL, payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            customer_note TEXT, admin_note TEXT, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL)$tail",
        "CREATE INDEX idx_orders_status ON orders (status)",
        "CREATE INDEX idx_orders_created ON orders (created_at)",

        "CREATE TABLE order_items (id $pk, order_id $ref NOT NULL, product_id $ref NULL, kind VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL, tier VARCHAR(30), tier_name VARCHAR(80), size VARCHAR(10), qty INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL, line_total DECIMAL(10,2) NOT NULL, details TEXT, config_json TEXT)$tail",
        "CREATE INDEX idx_items_order ON order_items (order_id)",

        "CREATE TABLE customizations (id $pk, order_item_id $ref NOT NULL, zone VARCHAR(20) NOT NULL, body VARCHAR(255) NOT NULL,
            font VARCHAR(60), color VARCHAR(60), align VARCHAR(10), font_size INT)$tail",

        "CREATE TABLE order_status_history (id $pk, order_id $ref NOT NULL, status VARCHAR(20) NOT NULL, note TEXT,
            admin_id $ref NULL, created_at VARCHAR(19) NOT NULL)$tail",

        "CREATE TABLE reviews (id $pk, author VARCHAR(120) NOT NULL, city VARCHAR(100), rating INT NOT NULL DEFAULT 5,
            body TEXT NOT NULL, active INT NOT NULL DEFAULT 1, is_demo INT NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL)$tail",

        "CREATE TABLE faqs (id $pk, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL, sort INT NOT NULL DEFAULT 0,
            active INT NOT NULL DEFAULT 1)$tail",
    ];
    foreach ($tables as $sql) $pdo->exec($sql);

    gradis_seed($pdo, env('SEED_DEMO', '1') === '1');
}

function gradis_seed(PDO $pdo, bool $demo): void
{
    $now = date('Y-m-d H:i:s');

    // ---------------------------------------------------------- réglages ----
    $settings = [
        'company_name' => 'Gradis',
        'tagline' => "L'élégance de votre réussite",
        'contact_email' => '',
        'contact_phone' => '',
        'contact_address' => 'Maroc',
        'opening_hours' => 'Lun – Sam : 9h – 19h',
        'social_instagram' => '', 'social_facebook' => '', 'social_tiktok' => '',
        'logo_path' => '',
        'whatsapp_number' => '212600000000',
        'whatsapp_greeting' => 'Bonjour Gradis,',
        'hero_title' => "L'élégance de votre réussite",
        'hero_text' => 'Des tenues de graduation élégantes, personnalisables et conçues pour marquer vos plus grands moments.',
        'hero_image' => '',
        'about_text' => "Gradis habille les diplômés marocains avec des tenues de graduation et de soutenance soignées : toges, écharpes et toques, à composer entièrement selon vos envies.",
        'shipping_fee' => '40',
        'free_shipping_from' => '800',
        'pay_cod' => '1', 'pay_whatsapp' => '1', 'pay_online' => '0',
        'max_qty' => '20',
        'text_max_scarf' => '28', 'text_max_cap' => '18',
        'text_size_min' => '10', 'text_size_max' => '22',
        'text_price_scarf' => '25', 'text_price_cap' => '20',
        'label_trim' => 'Billets décoratifs',
        'demo_notice' => $demo ? '1' : '0',
    ];
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ($settings as $k => $v) $st->execute([$k, $v]);

    // -------------------------------------------------------- catégories ----
    $cats = [
        ['tenues-completes', 'Tenues complètes', 'outfit', 1],
        ['toge-seule', 'Toge seule', 'robe', 2],
        ['echarpes-seules', 'Écharpes seules', 'scarf', 3],
        ['toques-seules', 'Toques seules', 'cap', 4],
        ['accessoires', 'Accessoires', 'accessory', 5],
    ];
    $st = $pdo->prepare('INSERT INTO categories (slug, name, kind, sort) VALUES (?,?,?,?)');
    foreach ($cats as $c) $st->execute($c);

    // ------------------------------------------------------------ gammes ----
    // Prix de DÉMONSTRATION : à remplacer depuis l'administration (Gammes).
    $tiers = [
        ['economie', 'Économie', "L'essentiel pour une cérémonie réussie, à prix doux.",
            'Polyester léger, finitions simples, broderie texte sur l’écharpe.', 180, 60, 40, 1, 0, 1],
        ['premium', 'Premium', 'Le meilleur équilibre entre tenue, confort et personnalisation.',
            'Tissu mat épais, doublure, coutures renforcées, broderie écharpe et toque.', 280, 90, 60, 1, 1, 2],
        ['prestige', 'Prestige', "L'excellence : tissu haut de gamme, finitions dorées et broderie soignée.",
            'Tissu premium fluide, passepoils dorés, toutes les options de broderie.', 400, 140, 90, 1, 1, 3],
    ];
    $st = $pdo->prepare('INSERT INTO tiers (code,name,description,materials,price_robe,price_scarf,price_cap,text_scarf,text_cap,sort) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($tiers as $t) $st->execute($t);

    // ----------------------------------------------------------- options ----
    $all = 'economie,premium,prestige';
    $up = 'premium,prestige';
    $opts = [
        // groupe, code, libellé, hex, font_css, extra, gammes, tri
        ['robe_color', 'blanc', 'Blanc', '#F4F1EA', null, 0, $all, 1],
        ['robe_color', 'noir', 'Noir', '#1A1A1C', null, 0, $all, 2],
        ['robe_color', 'bleu-nuit', 'Bleu nuit', '#0B132B', null, 0, $all, 3],

        ['scarf_color', 'noir', 'Noir', '#1C1C1C', null, 0, $all, 1],
        ['scarf_color', 'bordeaux', 'Bordeaux', '#6B1426', null, 0, $all, 2],
        ['scarf_color', 'bleu-marine', 'Bleu marine', '#14284B', null, 0, $all, 3],
        ['scarf_color', 'bleu-roi', 'Bleu roi', '#1F4FBF', null, 0, $all, 4],
        ['scarf_color', 'beige', 'Beige', '#D9C3A0', null, 0, $all, 5],
        ['scarf_color', 'marron', 'Marron', '#6B4A2F', null, 0, $all, 6],
        ['scarf_color', 'vert', 'Vert', '#14573E', null, 0, $all, 7],

        ['trim_color', 'blanc', 'Blanc', '#F8F6F1', null, 0, $all, 1],
        ['trim_color', 'noir', 'Noir', '#151515', null, 0, $all, 2],
        ['trim_color', 'or', 'Doré', '#D4AF37', null, 20, $up, 3],

        ['cap_color', 'blanc', 'Blanc', '#F4F1EA', null, 0, $all, 1],
        ['cap_color', 'noir', 'Noir', '#1A1A1C', null, 0, $all, 2],
        ['cap_color', 'bleu-nuit', 'Bleu nuit', '#0B132B', null, 0, $all, 3],

        ['cap_style', 'standard', 'Gland standard', null, null, 0, $all, 1],
        ['cap_style', 'long', 'Gland long', null, null, 10, $all, 2],
        ['cap_style', 'breloque', 'Gland + breloque année', null, null, 25, $up, 3],

        ['size', 'XS', 'XS', null, null, 0, $all, 1],
        ['size', 'S', 'S', null, null, 0, $all, 2],
        ['size', 'M', 'M', null, null, 0, $all, 3],
        ['size', 'L', 'L', null, null, 0, $all, 4],
        ['size', 'XL', 'XL', null, null, 0, $all, 5],
        ['size', 'XXL', 'XXL', null, null, 0, $all, 6],

        ['font', 'classique', 'Classique', null, "'Cormorant Garamond', serif", 0, $all, 1],
        ['font', 'moderne', 'Moderne', null, "'Montserrat', sans-serif", 0, $all, 2],
        ['font', 'capitales', 'Capitales', null, "'Cinzel', serif", 0, $up, 3],
        ['font', 'script', 'Manuscrite', null, "'Great Vibes', cursive", 0, $up, 4],

        ['text_color', 'or', 'Fil doré', '#D4AF37', null, 0, $all, 1],
        ['text_color', 'blanc', 'Fil blanc', '#FFFFFF', null, 0, $all, 2],
        ['text_color', 'noir', 'Fil noir', '#111111', null, 0, $all, 3],
        ['text_color', 'argent', 'Fil argent', '#C4C4CC', null, 0, $up, 4],
    ];
    $st = $pdo->prepare('INSERT INTO options (grp,code,label,hex,font_css,price_extra,tiers,sort) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($opts as $o) $st->execute($o);

    if (!$demo) return;

    // ---------------------------------------------------------- produits ----
    $cid = fn(string $slug) => (int)$pdo->query("SELECT id FROM categories WHERE slug='$slug'")->fetchColumn();
    $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    // [catégorie, gamme, nom, prix, description, couleurs principales, déf robe, déf écharpe, déf billets, déf toque, gammes accessoires]
    $products = [
        ['tenues-completes', 'prestige', 'Tenue Prestige Noir & Doré', 590,
            "Toge noire, écharpe beige et toque assortie, finitions dorées. La tenue des grandes occasions.",
            'noir,bleu-nuit,blanc', 'noir', 'beige', 'or', 'noir', null],
        ['tenues-completes', 'prestige', 'Tenue Prestige Bleu Nuit', 590,
            'Toge bleu nuit, écharpe bordeaux et passepoils dorés pour une allure académique affirmée.',
            'bleu-nuit,noir,blanc', 'bleu-nuit', 'bordeaux', 'or', 'bleu-nuit', null],
        ['tenues-completes', 'premium', 'Tenue Premium Classique', 430,
            'Toge noire, écharpe bleu marine, billets blancs : un grand classique, confortable toute la journée.',
            'noir,bleu-nuit,blanc', 'noir', 'bleu-marine', 'blanc', 'noir', null],
        ['tenues-completes', 'premium', 'Tenue Premium Émeraude', 430,
            'Toge noire et écharpe verte profonde, pour se démarquer avec sobriété.',
            'noir,bleu-nuit', 'noir', 'vert', 'or', 'noir', null],
        ['tenues-completes', 'economie', 'Tenue Économie Essentielle', 270,
            'Toge noire, écharpe noire et toque : tout le nécessaire pour la cérémonie.',
            'noir,bleu-nuit,blanc', 'noir', 'noir', 'blanc', 'noir', null],
        ['tenues-completes', 'economie', 'Tenue Économie Blanche', 270,
            'Toge blanche et écharpe bleu roi, une tenue lumineuse et facile à porter.',
            'blanc,noir', 'blanc', 'bleu-roi', 'noir', 'blanc', null],

        ['toge-seule', 'prestige', 'Toge Prestige', 400, 'Toge au tombé impeccable, tissu premium, passepoils soignés.',
            'noir,bleu-nuit,blanc', 'noir', null, 'or', null, null],
        ['toge-seule', 'premium', 'Toge Premium', 280, 'Toge doublée, tissu mat épais, coutures renforcées.',
            'noir,bleu-nuit,blanc', 'bleu-nuit', null, 'blanc', null, null],
        ['toge-seule', 'economie', 'Toge Économie', 180, 'Toge légère et pratique, idéale pour la journée de soutenance.',
            'noir,bleu-nuit,blanc', 'noir', null, 'blanc', null, null],

        ['echarpes-seules', 'prestige', 'Écharpe Prestige brodée', 140, 'Écharpe satinée avec bordures dorées, personnalisable par broderie.',
            'noir,bordeaux,bleu-marine,bleu-roi,beige,marron,vert', null, 'beige', 'or', null, null],
        ['echarpes-seules', 'premium', 'Écharpe Premium', 90, 'Écharpe épaisse, belle tenue, broderie texte disponible.',
            'noir,bordeaux,bleu-marine,bleu-roi,beige,marron,vert', null, 'bordeaux', 'or', null, null],
        ['echarpes-seules', 'economie', 'Écharpe Économie', 60, 'Écharpe simple et élégante.',
            'noir,bordeaux,bleu-marine,bleu-roi,beige,marron,vert', null, 'noir', 'blanc', null, null],

        ['toques-seules', 'prestige', 'Toque Prestige', 90, 'Toque rigide avec gland doré et bouton assorti.',
            'noir,bleu-nuit,blanc', null, null, 'or', 'noir', null],
        ['toques-seules', 'premium', 'Toque Premium', 60, 'Toque structurée, gland épais, broderie possible.',
            'noir,bleu-nuit,blanc', null, null, 'or', 'bleu-nuit', null],
        ['toques-seules', 'economie', 'Toque Économie', 40, 'Toque légère, gland standard.',
            'noir,bleu-nuit,blanc', null, null, 'blanc', 'noir', null],

        ['accessoires', 'premium', 'Étui à diplôme', 45, 'Étui rigide pour protéger et présenter votre diplôme.',
            null, null, null, null, null, 'economie,premium,prestige'],
        ['accessoires', 'prestige', 'Boîte cadeau Gradis', 60, 'Coffret élégant pour offrir la tenue ou la conserver en souvenir.',
            null, null, null, null, null, 'premium,prestige'],
        ['accessoires', 'premium', 'Breloque année dorée', 35, "Breloque dorée gravée de l'année de graduation, à fixer sur le gland.",
            null, null, null, null, null, 'economie,premium,prestige'],
        ['accessoires', 'economie', 'Housse de protection', 25, 'Housse pour transporter et ranger la tenue sans plis.',
            null, null, null, null, null, 'economie,premium,prestige'],
    ];
    $sp = $pdo->prepare('INSERT INTO products (category_id,tier,name,slug,description,price,active,colors,def_robe,def_scarf,def_trim,def_cap,tiers_allowed,sort,created_at) VALUES (?,?,?,?,?,?,1,?,?,?,?,?,?,?,?)');
    $sv = $pdo->prepare('INSERT INTO product_variants (product_id,size,stock) VALUES (?,?,?)');
    $i = 0;
    foreach ($products as $p) {
        $i++;
        $slug = slugify($p[2]);
        $sp->execute([$cid($p[0]), $p[1], $p[2], $slug, $p[4], $p[3], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $i, $now]);
        $pid = (int)$pdo->lastInsertId();
        if (in_array($p[0], ['tenues-completes', 'toge-seule'], true)) {
            foreach ($sizes as $s) $sv->execute([$pid, $s, ($s === 'XXL' ? 3 : 12)]);
        } else {
            $sv->execute([$pid, '*', 40]);
        }
    }

    // ------------------------------------------------------ avis de démo ----
    $st = $pdo->prepare('INSERT INTO reviews (author,city,rating,body,active,is_demo,created_at) VALUES (?,?,?,?,1,1,?)');
    foreach ([
        ['Exemple — Salma', 'Casablanca', 5, "Texte d'exemple : une tenue magnifique et une livraison rapide, merci !"],
        ['Exemple — Youssef', 'Rabat', 5, "Texte d'exemple : la personnalisation de l'écharpe est très réussie."],
        ['Exemple — Imane', 'Marrakech', 4, "Texte d'exemple : très bon rapport qualité-prix, je recommande."],
        ['Exemple — Anas', 'Fès', 5, "Texte d'exemple : équipe réactive sur WhatsApp, tout était clair."],
    ] as $r) $st->execute([...$r, $now]);

    // --------------------------------------------------------------- FAQ ----
    $st = $pdo->prepare('INSERT INTO faqs (question, answer, sort) VALUES (?,?,?)');
    foreach ([
        ['Comment choisir ma taille ?', "Choisissez la taille qui correspond à votre morphologie, de XS à XXL. En cas de doute, écrivez-nous sur WhatsApp avec votre taille et votre poids, nous vous conseillerons.", 1],
        ['Puis-je personnaliser mon écharpe et ma toque ?', "Oui. Dans le configurateur, ajoutez un texte (nom, année, établissement…), choisissez la police, la couleur du fil, l'alignement et la zone. L'aperçu se met à jour en direct.", 2],
        ['Puis-je acheter uniquement la toge, l\'écharpe ou la toque ?', 'Oui. Chaque article est vendu séparément ou en tenue complète.', 3],
        ['Livrez-vous partout au Maroc ?', 'Oui, la livraison est possible dans tout le Maroc. Les frais et le seuil de livraison offerte sont indiqués dans votre panier.', 4],
        ['Comment payer ?', "Selon les options activées : paiement à la livraison ou confirmation de commande par WhatsApp. Aucun paiement en ligne n'est demandé tant qu'il n'est pas activé.", 5],
        ['Quels sont les délais ?', "Les délais dépendent de la personnalisation et de votre ville. Nous vous les confirmons par téléphone ou WhatsApp après votre commande.", 6],
    ] as $f) $st->execute($f);
}
