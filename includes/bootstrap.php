<?php
/**
 * Gradis — amorçage : configuration, session, base de données, helpers.
 */
declare(strict_types=1);

define('GR_ROOT', dirname(__DIR__));

// ---------------------------------------------------------------- .env ----
function env(string $key, ?string $default = null): ?string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = GR_ROOT . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\"'");
            }
        }
    }
    $v = $vars[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string)$v;
}

define('GR_DEBUG', env('APP_ENV', 'production') === 'development');
error_reporting(E_ALL);
ini_set('display_errors', GR_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('Africa/Casablanca');
mb_internal_encoding('UTF-8');

// ------------------------------------------------------------- base URL ----
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $forced = env('APP_BASE');
    if ($forced !== null) return $base = '/' . trim($forced, '/') === '/' ? '' : '/' . trim($forced, '/');
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root = realpath(GR_ROOT) ?: GR_ROOT;
    if ($doc !== '' && str_starts_with($root, $doc)) {
        $base = str_replace('\\', '/', substr($root, strlen($doc)));
    } else {
        $base = '';
    }
    return $base = rtrim($base, '/');
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function asset(string $path): string
{
    $f = GR_ROOT . '/' . ltrim($path, '/');
    return url($path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

// ------------------------------------------------------------- sessions ----
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('gradis_sid');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => base_path() === '' ? '/' : base_path() . '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(18));
}

function json_for_script(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(mixed $token): bool
{
    start_session();
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// -------------------------------------------------------------- helpers ----
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($v): string
{
    $v = (float)$v;
    $s = (floor($v) == $v) ? number_format($v, 0, ',', ' ') : number_format($v, 2, ',', ' ');
    return $s . ' DH';
}
function now(): string { return date('Y-m-d H:i:s'); }
function slugify(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $s));
    return trim($s, '-') ?: 'item';
}
function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}
function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function security_headers(): void
{
    $nonce = csp_nonce();
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// ----------------------------------------------------------------- i18n ----
function lang(): string
{
    static $l = null;
    if ($l === null) {
        $l = 'fr'; // futur : détecter ar / en via session ou ?lang=
        if (!is_file(GR_ROOT . "/lang/$l.php")) $l = 'fr';
    }
    return $l;
}
function t(string $key, array $vars = []): string
{
    static $dict = null;
    if ($dict === null) $dict = require GR_ROOT . '/lang/' . lang() . '.php';
    $s = $dict[$key] ?? $key;
    foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
    return $s;
}

// ------------------------------------------------------------------- DB ----
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/payment.php';

security_headers();
