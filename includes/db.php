<?php
declare(strict_types=1);

/** Connexion PDO (SQLite par défaut, MySQL/MariaDB en option). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $driver = env('DB_DRIVER', 'sqlite');
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $fresh = false;
    if ($driver === 'mysql') {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', 'localhost'), env('DB_NAME', 'gradis'));
        $pdo = new PDO($dsn, env('DB_USER', ''), env('DB_PASS', ''), $opts);
    } else {
        $dir = env('DB_PATH') ?: GR_ROOT . '/database';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $file = rtrim($dir, '/\\') . '/gradis.sqlite';
        $fresh = !is_file($file);
        $pdo = new PDO('sqlite:' . $file, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    }
    if (!db_table_exists($pdo, 'settings')) {
        require_once __DIR__ . '/schema.php';
        gradis_install($pdo, $driver);
    }
    return $pdo;
}

function db_table_exists(PDO $pdo, string $table): bool
{
    try {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** SELECT → toutes les lignes. */
function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
/** SELECT → une ligne ou null. */
function q1(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r === false ? null : $r;
}
/** SELECT → une valeur. */
function qv(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetchColumn();
    return $r === false ? null : $r;
}
/** INSERT/UPDATE/DELETE → nombre de lignes. */
function ex(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}
/** INSERT → id inséré. */
function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    ex($sql, array_values($data));
    return (int)db()->lastInsertId();
}
function update(string $table, int $id, array $data): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    ex("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
}
