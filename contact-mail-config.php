<?php
declare(strict_types=1);

$config = [
    'host' => 'smtp.gmail.com',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'moodoo@idealoftstudio.com',
    'password' => '',
    'from_email' => 'moodoo@idealoftstudio.com',
    'from_name' => 'Moodoo Website',
    'to_email' => 'moodoo@idealoftstudio.com',
    'timeout' => 20,
];

$envMap = [
    'host' => 'MOODOO_SMTP_HOST',
    'port' => 'MOODOO_SMTP_PORT',
    'encryption' => 'MOODOO_SMTP_ENCRYPTION',
    'username' => 'MOODOO_SMTP_USERNAME',
    'password' => 'MOODOO_SMTP_APP_PASSWORD',
    'from_email' => 'MOODOO_SMTP_FROM_EMAIL',
    'from_name' => 'MOODOO_SMTP_FROM_NAME',
    'to_email' => 'MOODOO_CONTACT_TO_EMAIL',
    'timeout' => 'MOODOO_SMTP_TIMEOUT',
];

foreach ($envMap as $configKey => $envKey) {
    $envValue = getenv($envKey);
    if ($envValue !== false && $envValue !== '') {
        $config[$configKey] = $envValue;
    }
}

$localPath = __DIR__ . '/contact-mail-config.local.php';
if (is_file($localPath)) {
    $localConfig = require $localPath;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

$config['port'] = (int) ($config['port'] ?? 0);
$config['timeout'] = (int) ($config['timeout'] ?? 20);
$config['encryption'] = strtolower((string) ($config['encryption'] ?? 'ssl'));

if (!in_array($config['encryption'], ['ssl', 'tls'], true)) {
    $config['encryption'] = 'ssl';
}

if ($config['port'] <= 0) {
    $config['port'] = $config['encryption'] === 'tls' ? 587 : 465;
}

if ($config['timeout'] <= 0) {
    $config['timeout'] = 20;
}

return $config;
