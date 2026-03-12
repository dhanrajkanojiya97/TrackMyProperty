<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $password = getenv('DB_PASS');

    $config = [
        'app_env' => getenv('APP_ENV') ?: 'local',
        'db' => [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => getenv('DB_NAME') ?: 'trackmyproperty_db',
            'user' => getenv('DB_USER') ?: 'root',
            'pass' => $password !== false ? $password : '',
            'charset' => 'utf8mb4',
        ],
        'mail' => [
            'host' => getenv('SMTP_HOST') ?: '',
            'port' => (int) (getenv('SMTP_PORT') ?: 587),
            'username' => getenv('SMTP_USER') ?: '',
            'password' => getenv('SMTP_PASS') ?: '',
            'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: '',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'TrackMyProperty',
            'debug' => in_array(strtolower((string) getenv('SMTP_DEBUG')), ['1', 'true', 'yes', 'on'], true),
        ],
        'setup' => [
            'enable_tools' => false,
        ],
    ];

    $localConfigPath = __DIR__ . '/app-config.local.php';
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig)) {
            $config = array_replace_recursive($config, $localConfig);
        }
    }

    return $config;
}

function db_config(): array
{
    $db = app_config()['db'] ?? [];

    return [
        'host' => (string) ($db['host'] ?? '127.0.0.1'),
        'port' => (int) ($db['port'] ?? 3306),
        'name' => (string) ($db['name'] ?? 'trackmyproperty_db'),
        'user' => (string) ($db['user'] ?? 'root'),
        'pass' => (string) ($db['pass'] ?? ''),
        'charset' => (string) ($db['charset'] ?? 'utf8mb4'),
    ];
}

function request_is_local(): bool
{
    $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return in_array($remoteAddr, ['127.0.0.1', '::1'], true);
}

function setup_tools_enabled(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    $flag = getenv('ENABLE_SETUP_TOOLS');
    if ($flag !== false) {
        return in_array(strtolower($flag), ['1', 'true', 'yes', 'on'], true);
    }

    $config = app_config();
    $enabled = $config['setup']['enable_tools'] ?? null;
    if (is_bool($enabled)) {
        return $enabled;
    }

    return (($config['app_env'] ?? 'local') === 'local') && request_is_local();
}
