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
        'message' => 'Submission received.',
    ]);
}

$formType = normalizeSingleLine((string) ($_POST['form_type'] ?? ''), 32);
if (!in_array($formType, ['waitlist', 'enquiry'], true)) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Invalid form submission type.',
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
    error_log('Lead form mail config error: missing/invalid ' . implode(', ', $configIssues));
    jsonResponse(500, [
        'ok' => false,
        'message' => 'Email service is not ready. Configure Google App Password first.',
    ]);
}

if ($formType === 'waitlist') {
    handleWaitlistSubmission($config);
}

handleEnquirySubmission($config);

function handleWaitlistSubmission(array $config): void
{
    $email = normalizeSingleLine((string) ($_POST['email'] ?? ''), 120);
    $source = normalizeSingleLine((string) ($_POST['source'] ?? 'website'), 64);

    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid work email address.';
    }

    if ($errors !== []) {
        jsonResponse(422, [
            'ok' => false,
            'message' => 'Please fix the highlighted fields and try again.',
            'errors' => $errors,
        ]);
    }

    $subject = 'New Waitlist Signup - Moodoo';
    $body = buildWaitlistEmailBody($email, $source);

    sendLeadEmail($config, $subject, $body, $email, $email);

    jsonResponse(200, [
        'ok' => true,
        'message' => 'Thanks. You are on the waitlist and our team has been notified.',
    ]);
}

function handleEnquirySubmission(array $config): void
{
    $payload = [
        'workEmail' => normalizeSingleLine((string) ($_POST['workEmail'] ?? ''), 120),
        'companyName' => normalizeSingleLine((string) ($_POST['companyName'] ?? ''), 120),
        'teamSize' => normalizeSingleLine((string) ($_POST['teamSize'] ?? ''), 32),
        'supportNeed' => normalizeMultiLine((string) ($_POST['supportNeed'] ?? ''), 1500),
    ];

    $errors = [];
    if (!filter_var($payload['workEmail'], FILTER_VALIDATE_EMAIL)) {
        $errors['workEmail'] = 'Enter a valid work email address.';
    }

    if (
        stringLength($payload['companyName']) < 2
        || !preg_match('/^[\p{L}\p{M}0-9 .,\'+&()\/-]+$/u', $payload['companyName'])
    ) {
        $errors['companyName'] = 'Enter a valid company name (2-120 characters).';
    }

    $validTeamSizes = ['1-20', '21-50', '51-200', '201+'];
    if (!in_array($payload['teamSize'], $validTeamSizes, true)) {
        $errors['teamSize'] = 'Please select your team size.';
    }

    if (stringLength($payload['supportNeed']) > 1500) {
        $errors['supportNeed'] = 'Your message is too long. Please keep it under 1500 characters.';
    }

    if ($errors !== []) {
        jsonResponse(422, [
            'ok' => false,
            'message' => 'Please fix the highlighted fields and try again.',
            'errors' => $errors,
        ]);
    }

    $subject = 'New Team Enquiry - ' . $payload['companyName'];
    $body = buildEnquiryEmailBody($payload);

    sendLeadEmail($config, $subject, $body, $payload['workEmail'], $payload['companyName']);

    jsonResponse(200, [
        'ok' => true,
        'message' => 'Enquiry submitted and our team has been notified. We will contact you within 1-2 business days.',
    ]);
}

function sendLeadEmail(array $config, string $subject, string $body, string $replyToEmail, string $replyToName): void
{
    require_once __DIR__ . '/contact-smtp-mailer.php';

    try {
        $mailer = new ContactSmtpMailer($config);
        $mailer->send([
            'subject' => $subject,
            'body' => $body,
            'from_name' => (string) $config['from_name'],
            'from_email' => (string) $config['from_email'],
            'to_email' => (string) $config['to_email'],
            'reply_to_name' => normalizeSingleLine($replyToName, 120),
            'reply_to_email' => $replyToEmail,
        ]);
        error_log('Lead form mail sent: ' . $subject . ' -> ' . (string) $config['to_email']);
    } catch (Throwable $exception) {
        error_log('Lead form mail error: ' . $exception->getMessage());
        jsonResponse(500, [
            'ok' => false,
            'message' => 'Unable to send your message right now. Please try again shortly.',
        ]);
    }
}

function buildWaitlistEmailBody(string $email, string $source): string
{
    $submittedAtUtc = gmdate('Y-m-d H:i:s') . ' UTC';
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'Unavailable');
    $userAgent = normalizeSingleLine((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unavailable'), 350);

    return implode("\n", [
        'New waitlist signup received from moodoo website.',
        '',
        'Email: ' . $email,
        'Source: ' . $source,
        '',
        'Submitted At: ' . $submittedAtUtc,
        'IP Address: ' . $remoteAddress,
        'User Agent: ' . $userAgent,
    ]);
}

function buildEnquiryEmailBody(array $payload): string
{
    $submittedAtUtc = gmdate('Y-m-d H:i:s') . ' UTC';
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'Unavailable');
    $userAgent = normalizeSingleLine((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unavailable'), 350);
    $supportNeed = $payload['supportNeed'] !== '' ? $payload['supportNeed'] : 'Not provided';

    return implode("\n", [
        'New team enquiry received from moodoo website.',
        '',
        'Work Email: ' . $payload['workEmail'],
        'Company Name: ' . $payload['companyName'],
        'Team Size: ' . $payload['teamSize'],
        '',
        'Support Need:',
        $supportNeed,
        '',
        'Submitted At: ' . $submittedAtUtc,
        'IP Address: ' . $remoteAddress,
        'User Agent: ' . $userAgent,
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

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
