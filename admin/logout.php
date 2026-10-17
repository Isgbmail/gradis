<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
admin_logout();
redirect(url('admin/login.php'));
