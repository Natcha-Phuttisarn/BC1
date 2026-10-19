<?php
// Copy to config.local.php and replace values on the HOST only.
// Environment variables take precedence. Never publish real credentials.
return [
    'BC_DB_HOST' => 'localhost',
    'BC_DB_PORT' => '3306',
    'BC_DB_NAME' => 'hosting_database_name',
    'BC_DB_USERNAME' => 'hosting_database_user',
    'BC_DB_PASSWORD' => 'replace-with-database-password',
    // '' for the domain root, '/BC1' for a subfolder. Omit for auto detection.
    'BC_BASE_PATH' => '',
    'BC_SECURE_COOKIES' => '1', // HTTPS production; use 0 for local HTTP.
    'BC_MAIL_HOST' => 'smtp.gmail.com',
    'BC_MAIL_PORT' => '587',
    'BC_MAIL_ENCRYPTION' => 'tls', // tls (587) or ssl (465)
    'BC_MAIL_USERNAME' => '',
    'BC_MAIL_PASSWORD' => '',
    'BC_MAIL_FROM' => '',
    'BC_MAIL_FROM_NAME' => 'Bizcomp Club',
    // Generate using: php -r "echo bin2hex(random_bytes(32));"
    'BC_OTP_HMAC_SECRET' => '',
];
