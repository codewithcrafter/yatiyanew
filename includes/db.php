<?php
/**
 * YATIYA COUTURE - Secure Database Connection Wrapper using PHP PDO
 * Uses Singleton pattern to maintain a single efficient connection pool per request.
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}

    /**
     * Get the PDO database connection instance
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log detailed error securely
                error_log('[Yatiya DB Error] ' . $e->getMessage());

                // If running in CLI / debug, show readable error; else show generic maintenance message
                if (php_sapi_name() === 'cli' || (defined('DEBUG_MODE') && DEBUG_MODE)) {
                    die('Database Connection Error: ' . $e->getMessage());
                } else {
                    http_response_code(500);
                    die('The Yatiya Couture luxury portal is temporarily undergoing concierge maintenance. Please try again in a few moments.');
                }
            }
        }

        return self::$instance;
    }

    /**
     * Test connection explicitly
     * 
     * @return array Status and details
     */
    public static function testConnection(): array {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query('SELECT DATABASE() as db_name, VERSION() as version, CURRENT_TIMESTAMP() as server_time');
            $info = $stmt->fetch();
            return [
                'status' => 'connected',
                'database' => $info['db_name'] ?? 'unknown',
                'version' => $info['version'] ?? 'unknown',
                'server_time' => $info['server_time'] ?? 'unknown'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

/**
 * Global helper function for concise PDO access
 * 
 * @return PDO
 */
function db(): PDO {
    return Database::getConnection();
}

