#!/usr/bin/env php
<?php

/**
 * Booking System Diagnostic Tool
 * This script tests the booking system to identify issues
 */

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

echo "=== BOOKING SYSTEM DIAGNOSTIC ===\n\n";

// 1. Check environment
echo "1. Environment Check:\n";
echo "   APP_ENV: " . ($_ENV['APP_ENV'] ?? 'NOT SET') . "\n";
echo "   Database: var/data_{$_ENV['APP_ENV']}.db\n\n";

// 2. Check database file
$dbFile = __DIR__ . "/var/data_{$_ENV['APP_ENV']}.db";
if (file_exists($dbFile)) {
    echo "2. Database File: ✅ EXISTS\n";
    echo "   Size: " . filesize($dbFile) . " bytes\n";
    echo "   Last modified: " . date('Y-m-d H:i:s', filemtime($dbFile)) . "\n\n";

    // 3. Check tables
    try {
        $db = new SQLite3($dbFile);
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table';");
        echo "3. Database Tables:\n";
        $hasFormBooking = false;
        while ($row = $tables->fetchArray(SQLITE3_ASSOC)) {
            echo "   ✅ " . $row['name'] . "\n";
            if ($row['name'] === 'form_booking') {
                $hasFormBooking = true;
            }
        }

        if (!$hasFormBooking) {
            echo "\n   ❌ ERROR: form_booking table is missing!\n";
            echo "   Run: php bin/console doctrine:migrations:migrate --env=prod\n\n";
            exit(1);
        }

        // 4. Check bookings
        echo "\n4. Bookings in Database:\n";
        $result = $db->query("SELECT COUNT(*) as cnt FROM form_booking");
        $row = $result->fetchArray();
        echo "   Total: " . $row['cnt'] . "\n";

        if ($row['cnt'] > 0) {
            $result = $db->query("SELECT COUNT(*) as cnt FROM form_booking WHERE confirmed_at IS NOT NULL");
            $row = $result->fetchArray();
            echo "   Confirmed: " . $row['cnt'] . "\n";

            $result = $db->query("SELECT COUNT(*) as cnt FROM form_booking WHERE confirmed_at IS NULL");
            $row = $result->fetchArray();
            echo "   Unconfirmed: " . $row['cnt'] . "\n";

            // Show recent bookings
            echo "\n   Recent Bookings:\n";
            $result = $db->query("SELECT id, datetime(created_at) as created, parent_email, child_name, confirmed_at FROM form_booking ORDER BY id DESC LIMIT 5");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $confirmed = $row['confirmed_at'] ? '✅ Confirmed' : '⚠️  Unconfirmed';
                echo "   - ID {$row['id']}: {$row['created']} | {$row['parent_email']} | {$confirmed}\n";
            }
        }

        $db->close();
    } catch (Exception $e) {
        echo "\n   ❌ ERROR: " . $e->getMessage() . "\n\n";
        exit(1);
    }
} else {
    echo "2. Database File: ❌ DOES NOT EXIST\n";
    echo "   Expected: $dbFile\n";
    echo "   Run: php bin/console doctrine:migrations:migrate --env=prod\n\n";
    exit(1);
}

// 5. Check mail configuration
echo "\n5. Mail Configuration:\n";
$mailerDsn = $_ENV['MAILER_DSN'] ?? null;
if ($mailerDsn) {
    $sanitized = preg_replace('/(:\/\/[^:]+:)([^@]+)(@.*)/', '$1***$3', $mailerDsn);
    echo "   MAILER_DSN: ✅ SET\n";
    echo "   DSN: $sanitized\n";
    echo "   FROM: " . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'NOT SET') . "\n";
    echo "   TO: " . ($_ENV['MAIL_TO_ADDRESS'] ?? 'NOT SET') . "\n";
} else {
    echo "   ❌ MAILER_DSN is NOT SET\n";
    echo "   Emails will fail!\n";
}

// 6. Check log files
echo "\n6. Log Files:\n";
$logFile = __DIR__ . "/var/log/{$_ENV['APP_ENV']}.log";
if (file_exists($logFile)) {
    echo "   ✅ Log file exists: $logFile\n";
    echo "   Size: " . filesize($logFile) . " bytes\n";
    echo "   Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "\n";

    // Check for recent errors
    $logContent = file_get_contents($logFile);
    $errorCount = substr_count($logContent, '"level_name":"ERROR"');
    $criticalCount = substr_count($logContent, '"level_name":"CRITICAL"');

    if ($errorCount > 0 || $criticalCount > 0) {
        echo "   ⚠️  Errors found: $errorCount ERROR, $criticalCount CRITICAL\n";
        echo "   Check the log file for details: tail -50 $logFile\n";
    } else {
        echo "   ✅ No errors found in log\n";
    }
} else {
    echo "   ⚠️  Log file doesn't exist yet: $logFile\n";
}

echo "\n7. Permissions:\n";
$varDir = __DIR__ . '/var';
if (is_writable($varDir)) {
    echo "   ✅ var/ directory is writable\n";
} else {
    echo "   ❌ var/ directory is NOT writable\n";
    echo "   Run: chmod -R 777 var/\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n\n";

// Final recommendations
echo "Next Steps:\n";
echo "1. If you just submitted a booking and it's not here, check:\n";
echo "   - Is the web server running? (php -S localhost:8000 -t public)\n";
echo "   - Any browser console errors?\n";
echo "   - Check browser network tab for failed requests\n\n";
echo "2. To test the booking form:\n";
echo "   - Open: http://localhost:8000/anmeldung\n";
echo "   - Submit a test booking\n";
echo "   - Check logs: tail -f var/log/{$_ENV['APP_ENV']}.log\n\n";
echo "3. To view unconfirmed bookings:\n";
echo "   - Run: bash check-unconfirmed-bookings.sh\n\n";
