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
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_TIMEOUT            => 5
            ];

            // Host failover list: ensures zero-downtime whether Hostinger connects via remote host or internal localhost
            $candidates = [
                // 1. Primary Hostinger Host
                [
                    'host'   => DB_HOST,
                    'port'   => DB_PORT,
                    'dbname' => DB_NAME,
                    'user'   => DB_USER,
                    'pass'   => DB_PASS
                ]
            ];

            // 2. Localhost fallback for Hostinger internal socket/proxy
            if (DB_HOST !== 'localhost' && DB_HOST !== '127.0.0.1') {
                $candidates[] = [
                    'host'   => 'localhost',
                    'port'   => DB_PORT,
                    'dbname' => DB_NAME,
                    'user'   => DB_USER,
                    'pass'   => DB_PASS
                ];
            }

            // 3. Local development fallback (only if running locally and remote IP is not whitelisted in Hostinger)
            $isLocalEnv = (php_sapi_name() === 'cli')
                || (isset($_SERVER['HTTP_HOST']) && (str_contains($_SERVER['HTTP_HOST'], 'localhost') || str_contains($_SERVER['HTTP_HOST'], '127.0.0.1')));
            if ($isLocalEnv) {
                $candidates[] = [
                    'host'   => '127.0.0.1',
                    'port'   => '3306',
                    'dbname' => 'yatiya_couture',
                    'user'   => 'root',
                    'pass'   => ''
                ];
            }

            $lastException = null;
            foreach ($candidates as $config) {
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                        $config['host'],
                        $config['port'],
                        $config['dbname'],
                        DB_CHARSET
                    );
                    self::$instance = new PDO($dsn, $config['user'], $config['pass'], $options);
                    break;
                } catch (PDOException $e) {
                    $lastException = $e;
                }
            }

            if (self::$instance === null) {
                error_log('[Yatiya DB Error] ' . ($lastException ? $lastException->getMessage() : 'Database connection failed'));

                if (php_sapi_name() === 'cli' || (defined('DEBUG_MODE') && DEBUG_MODE)) {
                    die('Database Connection Error: ' . ($lastException ? $lastException->getMessage() : 'Could not connect to database.'));
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

