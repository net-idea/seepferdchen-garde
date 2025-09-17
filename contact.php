<?php
declare(strict_types=1);

// Simple contact form handler with validation, honeypot and basic throttling.
// Redirects back to '/kontakt' with status flags via proper query strings.

$redirectBase = '/kontakt';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $redirectBase);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper to redirect with query params and optional hash
$redirect = static function (array $params = [], string $hash = '') use ($redirectBase): void {
    $qs = http_build_query($params);
    $url = $redirectBase . ($qs !== '' ? '?' . $qs : '') . $hash;
    header('Location: ' . $url);
    exit;
};

// Basic rate limiting: max 1 submission every 20 seconds, 5 per hour.
$now = time();
$_SESSION['cf_times'] = array_values(
    array_filter($_SESSION['cf_times'] ?? [], fn($t) => ($now - (int)$t) < 3600)
);

if (!empty($_SESSION['cf_times'])) {
    $last = end($_SESSION['cf_times']);
    if (($now - (int)$last) < 20 || count($_SESSION['cf_times']) >= 5) {
        $redirect(['error' => 'rate'], '#contact-error');
    }
}

$input = static function (string $key): string {
    return trim((string)($_POST[$key] ?? ''));
};

$name = mb_substr($input('name'), 0, 120);
$email = mb_substr($input('email'), 0, 200);
$phone = mb_substr($input('phone'), 0, 40);
$message = mb_substr($input('message'), 0, 5000);
$consent = isset($_POST['consent']);
$honey = $input('website'); // honeypot

// Honeypot triggered -> pretend success.
if ($honey !== '') {
    $redirect(['sent' => 1], '#contact-success');
}

// Validate
if ($name ==='' || $message === '' || !$consent) {
    $redirect(['error' => 'invalid'], '#contact-error');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $redirect(['error' => 'email'], '#contact-error');
}
if (mb_strlen($message) < 10) {
    $redirect(['error' => 'short'], '#contact-error');
}

// Prepare mail
$to = 'mail@seepferdchen-garde.de';

// Prevent header injection in header fields
$safe = static function (string $v): string {
    return str_replace(["\r", "\n"], ' ', $v);
};

$subject = 'Neue Kontaktanfrage von ' . ($name !== '' ? $name : 'Website');
$subject = mb_encode_mimeheader($subject, 'UTF-8');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$body = "Neue Kontaktanfrage über das Formular:\n\n"
    . "Name: {$name}\n"
    . "E-Mail: {$email}\n"
    . "Telefon: {$phone}\n"
    . "Nachricht:\n{$message}\n\n"
    . "— Meta —\n"
    . "IP: {$ip}\n"
    . "User-Agent: {$ua}\n"
    . "Zeit: " . date('c') . "\n";

$headers = [];
$headers[] = 'From: mail@' . $safe($host);
$headers[] = 'Reply-To: ' . $safe($email);
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'X-Form-Origin: kontakt';

$ok = @mail($to, $subject, $body, implode("\r\n", $headers));

$_SESSION['cf_times'][] = $now;

if ($ok) {
    $redirect(['sent' => 1], '#contact-success');
} else {
    $redirect(['error' => 'mail'], '#contact-error');
}
