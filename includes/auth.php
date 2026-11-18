<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('LP8BOOKSSESSID');
    session_save_path(sys_get_temp_dir());
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function exigirLogin(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
    header('Cache-Control: no-store');
}

