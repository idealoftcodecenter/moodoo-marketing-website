<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
}

if (!empty($_POST['website'] ?? '')) {
    jsonResponse(200, [
        'ok' => true,
        'message' => 'Message sent successfully.',
    ]);
}

$payload = [
    'full_name' => normalizeSingleLine((string) ($_POST['full_name'] ?? ''), 80),
    'company_name' => normalizeSingleLine((string) ($_POST['company_name'] ?? ''), 120),
    'designation' => normalizeSingleLine((string) ($_POST['designation'] ?? ''), 80),
    'email' => normalizeSingleLine((string) ($_POST['email'] ?? ''), 120),
    'phone' => normalizeSingleLine((string) ($_POST['phone'] ?? ''), 22),
    'message' => normalizeMultiLine((string) ($_POST['message'] ?? ''), 1500),
];

$validationErrors = validatePayload($payload);
if ($validationErrors !== []) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Please fix the highlighted fields and try again.',
        'errors' => $validationErrors,
    ]);
}

$configPath = __DIR__ . '/contact-mail-config.php';
if (!is_file($configPath)) {
    jsonResponse(500, [
        'ok' => false,
        'message' => 'Email service is not configured.',
    ]);
}

$config = require $configPath;
if (!is_array($config)) {
    jsonResponse(500, [
        'ok' => false,
        'message' => 'Email service configuration is invalid.',
    ]);
}

$config['from_email'] = (string) ($config['from_email'] ?? ($config['username'] ?? ''));
$config['from_name'] = (string) ($config['from_name'] ?? 'Moodoo Website');
$config['to_email'] = (string) ($config['to_email'] ?? 'moodoo@idealoftstudio.com');

$configIssues = validateMailerConfig($config);
if ($configIssues !== []) {
    error_log('Contact form mail config error: missing/invalid ' . implode(', ', $configIssues));
    jsonResponse(500, [
        'ok' => false,
        'message' => 'Email service is not ready. Configure Google App Password first.',
    ]);
}

require_once __DIR__ . '/contact-smtp-mailer.php';

$subject = 'New Contact Enquiry - ' . $payload['full_name'];
$messageBody = buildEmailBody($payload);

try {
    $mailer = new ContactSmtpMailer($config);
    $mailer->send([
        'subject' => $subject,
        'body' => $messageBody,
        'from_name' => (string) $config['from_name'],
        'from_email' => (string) $config['from_email'],
        'to_email' => (string) $config['to_email'],
        'reply_to_name' => $payload['full_name'],
        'reply_to_email' => $payload['email'],
    ]);

    jsonResponse(200, [
        'ok' => true,
        'message' => 'Thanks. Your message has been sent successfully.',
    ]);
} catch (Throwable $exception) {
    error_log('Contact form mail error: ' . $exception->getMessage());
    jsonResponse(500, [
        'ok' => false,
        'message' => 'Unable to send your message right now. Please try again shortly.',
    ]);
}

function normalizeSingleLine(string $value, int $maxLength): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    return limitLength($value, $maxLength);
}

function normalizeMultiLine(string $value, int $maxLength): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    $value = preg_replace('/[^\P{C}\n\t]/u', '', $value) ?? '';
    return limitLength($value, $maxLength);
}

function limitLength(string $value, int $maxLength): string
{
    if (stringLength($value) <= $maxLength) {
        return $value;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function stringLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value);
    }

    return strlen($value);
}

function validatePayload(array $payload): array
{
    $errors = [];

    if (
        stringLength($payload['full_name']) < 2
        || !preg_match('/^[\p{L}\p{M} .\'-]+$/u', $payload['full_name'])
    ) {
        $errors['full_name'] = 'Enter your full name (2-80 characters).';
    }

    if ($payload['company_name'] !== '') {
        if (
            stringLength($payload['company_name']) < 2
            || !preg_match('/^[\p{L}\p{M}0-9 .,\'+&()\/-]+$/u', $payload['company_name'])
        ) {
            $errors['company_name'] = 'Enter a valid company name (2-120 characters).';
        }
    }

    if ($payload['designation'] !== '') {
        if (
            stringLength($payload['designation']) < 2
            || !preg_match('/^[\p{L}\p{M}0-9 .,\'+&()\/-]+$/u', $payload['designation'])
        ) {
            $errors['designation'] = 'Please select a valid designation/role.';
        }
    }

    if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    $phoneDigits = preg_replace('/\D+/', '', $payload['phone']) ?? '';
    if (
        !preg_match('/^\+?[0-9()\s.-]{7,22}$/', $payload['phone'])
        || strlen($phoneDigits) < 7
        || strlen($phoneDigits) > 15
    ) {
        $errors['phone'] = 'Enter a valid phone number.';
    }

    $messageLength = stringLength($payload['message']);
    if ($messageLength < 10 || $messageLength > 1500) {
        $errors['message'] = 'Enter a message between 10 and 1500 characters.';
    }

    return $errors;
}

function validateMailerConfig(array $config): array
{
    $issues = [];
    $requiredKeys = ['host', 'port', 'username', 'password', 'from_email', 'to_email'];

    foreach ($requiredKeys as $requiredKey) {
        if (empty($config[$requiredKey])) {
            $issues[] = $requiredKey;
        }
    }

    if (!empty($config['from_email']) && !filter_var((string) $config['from_email'], FILTER_VALIDATE_EMAIL)) {
        $issues[] = 'from_email';
    }

    if (!empty($config['to_email']) && !filter_var((string) $config['to_email'], FILTER_VALIDATE_EMAIL)) {
        $issues[] = 'to_email';
    }

    return array_values(array_unique($issues));
}

function buildEmailBody(array $payload): string
{
    $submittedAtUtc = gmdate('Y-m-d H:i:s') . ' UTC';
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'Unavailable');
    $userAgent = normalizeSingleLine((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unavailable'), 350);

    $companyName = $payload['company_name'] !== '' ? $payload['company_name'] : 'Not provided';
    $designation = $payload['designation'] !== '' ? $payload['designation'] : 'Not provided';

    return implode("\n", [
        'New contact enquiry received from moodoo marketing website.',
        '',
        'Full Name: ' . $payload['full_name'],
        'Company Name: ' . $companyName,
        'Designation/Role: ' . $designation,
        'Email Address: ' . $payload['email'],
        'Phone Number: ' . $payload['phone'],
        '',
        'Message:',
        $payload['message'],
        '',
        'Submitted At: ' . $submittedAtUtc,
        'IP Address: ' . $remoteAddress,
        'User Agent: ' . $userAgent,
    ]);
}

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
