<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(419);
    exit('Session expirée ou requête invalide. Rechargez la page puis réessayez.');
}
admin_logout();
redirect(url('admin/login.php'));
