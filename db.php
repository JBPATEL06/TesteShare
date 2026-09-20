<?php
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'testshare');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Error 1049: Unknown database - automatically create database 'testshare'
            if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                try {
                    $tmpConn = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $tmpConn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    $tmpConn = null;

                    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                } catch (Exception $ex) {
                    die("<div style='color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; padding:20px; border-radius:8px; font-family:sans-serif; max-w:600px; margin:50px auto; text-align:center;'>
                        <h2 style='margin-top:0;'>⚠️ Database Connection Failed</h2>
                        <p>Could not connect to MySQL server or auto-create database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>
                        <p><strong>Error details:</strong> " . htmlspecialchars($ex->getMessage()) . "</p>
                        <hr style='border:0; border-top:1px solid #f5c6cb; margin:15px 0;'>
                        <p style='margin-bottom:0;'>Please make sure <strong>MySQL Service</strong> is started in <strong>XAMPP Control Panel</strong>.</p>
                    </div>");
                }
            } else {
                die("<div style='color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; padding:20px; border-radius:8px; font-family:sans-serif; max-w:600px; margin:50px auto; text-align:center;'>
                    <h2 style='margin-top:0;'>⚠️ Database Connection Failed</h2>
                    <p><strong>Error details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <hr style='border:0; border-top:1px solid #f5c6cb; margin:15px 0;'>
                    <p style='margin-bottom:0;'>Please make sure <strong>MySQL Service</strong> is started in <strong>XAMPP Control Panel</strong>.</p>
                </div>");
            }
        }

        // Auto-initialize tables if users table is missing
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'users'")->fetch();
            if (!$tableCheck) {
                $sqlPath = __DIR__ . '/testshare.sql';
                if (file_exists($sqlPath)) {
                    $sqlContent = file_get_contents($sqlPath);
                    $conn->exec($sqlContent);
                }
            }
        } catch (Exception $ex) {
            // Ignore auto-init errors if already created
        }
    }
    return $conn;
}

