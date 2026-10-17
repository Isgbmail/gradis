<?php
declare(strict_types=1);

/** Lecture / écriture des réglages (clé → valeur), mis en cache par requête. */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (q('SELECT skey, svalue FROM settings') as $r) $cache[$r['skey']] = (string)$r['svalue'];
    }
    return $cache;
}
function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}
function setting_set(string $key, string $value): void
{
    if (qv('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key])) {
        ex('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
    } else {
        ex('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
    }
    settings_all(true);
}

/** Catalogue des données de référence utilisées partout. */
function tiers_all(bool $onlyActive = true): array
{
    $rows = q('SELECT * FROM tiers ' . ($onlyActive ? 'WHERE active = 1 ' : '') . 'ORDER BY sort');
    $out = [];
    foreach ($rows as $r) $out[$r['code']] = $r;
    return $out;
}
function options_all(bool $onlyActive = true): array
{
    $rows = q('SELECT * FROM options ' . ($onlyActive ? 'WHERE active = 1 ' : '') . 'ORDER BY grp, sort, id');
    $out = [];
    foreach ($rows as $r) $out[$r['grp']][$r['code']] = $r;
    return $out;
}
function categories_all(): array { return q('SELECT * FROM categories ORDER BY sort, id'); }

function tier_list_csv(?string $csv): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)$csv))));
}
