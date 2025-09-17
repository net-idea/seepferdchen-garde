<?php
declare(strict_types=1);

// Report all errors
error_reporting(E_ALL);
// Hide errors from output
//ini_set('display_errors', 0);
// Log errors (default)
//ini_set('log_errors', 1);

const CMS_CONTENT_DIR = __DIR__ . '/../content';

const CMS_ADMIN_USER = 'admin';
const CMS_ADMIN_HASH = '$2y$12$M5prMIKnpp.rn5JYkFPvY.ejWtYoB1UQUpzEf3Un5LWksLyoXVGhK';

session_start();

function cms_is_logged_in(): bool
{
    return !empty($_SESSION['cms_auth']);
}

function cms_require_login(): void
{
    if (!cms_is_logged_in()) {
        header('Location: /admin/login.php');

        exit;
    }
}

function cms_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function cms_verify_csrf(string $t): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', $t);
}
