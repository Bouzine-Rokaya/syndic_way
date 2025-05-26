<?php
// Application Configuration
define('APP_NAME', 'Syndicate Management Platform');
define('BASE_URL', 'http://localhost/syndicatePlatform/');
define('PUBLIC_URL', 'http://localhost/syndicatePlatform/public/');
define('ADMIN_URL', 'http://localhost/syndicatePlatform/admin/');
define('UPLOAD_PATH', 'public/assets/uploads/');

// Email Configuration
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'adbouzine2006@gmail.com');
define('MAIL_PASSWORD', 'aaaaazzzzz');
define('MAIL_FROM_NAME', 'Syndicate Platform');
define('MAIL_DEBUG', false); 

// For development/testing - log emails to file instead of sending
define('MAIL_LOG_ONLY', false); 

// Security
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', 3600); 

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_SYNDIC', 'syndic');
define('ROLE_RESIDENT', 'resident');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'syndicate_platform');
define('DB_USER', 'root');
define('DB_PASS', '');
?>