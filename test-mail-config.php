#!/usr/bin/env php
<?php

/**
 * Test script to verify mail configuration
 * Run: php test-mail-config.php
 */

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

echo "=== Mail Configuration Test ===\n\n";

// Load .env files
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

echo "Environment: " . ($_ENV['APP_ENV'] ?? 'NOT SET') . "\n\n";

echo "Mail Configuration:\n";
echo "  MAIL_SCHEME: " . ($_ENV['MAIL_SCHEME'] ?? 'NOT SET') . "\n";
echo "  MAIL_HOST: " . ($_ENV['MAIL_HOST'] ?? 'NOT SET') . "\n";
echo "  MAIL_PORT: " . ($_ENV['MAIL_PORT'] ?? 'NOT SET') . "\n";
echo "  MAIL_ENCRYPTION: " . ($_ENV['MAIL_ENCRYPTION'] ?? 'NOT SET') . "\n";
echo "  MAIL_USER: " . ($_ENV['MAIL_USER'] ?? 'NOT SET') . "\n";
echo "  MAIL_PASSWORD: " . (isset($_ENV['MAIL_PASSWORD']) ? '***SET***' : 'NOT SET') . "\n\n";

echo "Composed MAILER_DSN:\n";
$mailerDsn = $_ENV['MAILER_DSN'] ?? 'NOT SET';
if ($mailerDsn !== 'NOT SET') {
    // Hide password for security
    $sanitized = preg_replace('/(:\/\/[^:]+:)([^@]+)(@.*)/', '$1***$3', $mailerDsn);
    echo "  " . $sanitized . "\n\n";
} else {
    echo "  NOT SET\n\n";
}

echo "Mail Addresses:\n";
echo "  FROM: " . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'NOT SET') . " (" . ($_ENV['MAIL_FROM_NAME'] ?? 'NOT SET') . ")\n";
echo "  TO: " . ($_ENV['MAIL_TO_ADDRESS'] ?? 'NOT SET') . " (" . ($_ENV['MAIL_TO_NAME'] ?? 'NOT SET') . ")\n\n";

if ($mailerDsn === 'NOT SET') {
    echo "❌ MAILER_DSN is not set! Emails will fail.\n";
    exit(1);
} else {
    echo "✅ Mail configuration appears to be loaded.\n";
    echo "\nNote: This only checks if variables are set, not if they work.\n";
    echo "To test actual email sending, submit a test booking.\n";
    exit(0);
}
