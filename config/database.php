<?php
/**
 * HRMS - Database Configuration
 * Handles MySQL database connection using PDO
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'hrms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'HRMS');
define('APP_FULL_NAME', 'Human Resource Management System');
define('APP_URL', 'http://localhost/hrms');
define('APP_VERSION', '1.0.0');

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour

/**
 * Get PDO database connection
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log this error instead of displaying
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2 style="color:#dc2626;">Database Connection Failed</h2>
                <p>Could not connect to the MySQL database. Please ensure:</p>
                <ul style="list-style:none;padding:0;">
                    <li>✓ XAMPP MySQL service is running</li>
                    <li>✓ Database "hrms" has been created</li>
                    <li>✓ Import database/hrms.sql into phpMyAdmin</li>
                </ul>
                <p style="color:#6b7280;font-size:0.85em;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
            </div>');
        }
    }
    
    return $pdo;
}
