<?php
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
define('DB_NAME', '01164502_inx');
define('DB_USER', '01164502_inx');
define('DB_PASSWORD', 'iN&5X32*8jks^hsdf(JS$e');
define('DB_HOST', 'mysql8');
define('DB_CHARSET', 'utf8');
define('SMTP_HOST', 'hosting2596057.online.pro');
define('SMTP_USER', 'noreply@inbin.pl');
define('SMTP_PASS', 'iN&biN%446Jdh(539)');
define('SMTP_PORT', 587);
// Create a PDO instance
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASSWORD,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}