<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (current_user()) {
        logout();
    }
}
redirect('login.php');
