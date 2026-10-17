<?php
// Usage : php bin/create-admin.php "Nom" email@exemple.com "MotDePasse123456"
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI uniquement.');
require __DIR__ . '/../includes/auth.php';
[$s, $name, $email, $pw] = $argv + [null, null, null, null];
if (!$pw) exit("Usage : php bin/create-admin.php \"Nom\" email \"motdepasse\"\n");
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) exit("E-mail invalide.\n");
if ($p = password_problem($pw)) exit($p . "\n");
if (qv('SELECT 1 FROM admin_users WHERE email = ?', [strtolower($email)])) exit("Cet e-mail existe déjà.\n");
admin_create($name, $email, $pw);
echo "Administrateur créé : $email\n";
