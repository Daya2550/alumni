<?php
declare(strict_types=1);

// Simple configuration file for PDO connection.
// Set environment variables in your web server or system to avoid hardcoding secrets.
// Supported drivers: mysql, pgsql, sqlsrv, sqlite

// Make sure errors don't break JSON responses
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function getenv_or_default(string $key, ?string $default = null): ?string {
	$val = getenv($key);
	return $val !== false ? $val : $default;
}

function get_database_dsn(): string {
	$driver = strtolower((string) getenv_or_default('DB_DRIVER', 'mysql'));
	if ($driver === 'sqlite') {
		$dbPath = (string) getenv_or_default('SQLITE_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'database.sqlite');
		return "sqlite:" . $dbPath;
	}

	$host = (string) getenv_or_default('DB_HOST', 'localhost');
	$port = (string) getenv_or_default('DB_PORT', $driver === 'pgsql' ? '5432' : ($driver === 'sqlsrv' ? '1433' : '3306'));
	$db   = (string) getenv_or_default('DB_NAME', 'alumni_portal');

	if ($driver === 'pgsql') {
		return "pgsql:host={$host};port={$port};dbname={$db}";
	}
	if ($driver === 'sqlsrv') {
		return "sqlsrv:Server={$host},{$port};Database={$db}";
	}
	// default mysql
	$charset = (string) getenv_or_default('DB_CHARSET', 'utf8mb4');
	return "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
}

function get_pdo(): PDO {
	$driver = strtolower((string) getenv_or_default('DB_DRIVER', 'mysql'));
    $user = (string) getenv_or_default('DB_USER', 'root');
    $pass = (string) getenv_or_default('DB_PASS', '2550');
	$dsn  = get_database_dsn();

	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];

	if ($driver === 'sqlite') {
		return new PDO($dsn, null, null, $options);
	}

	return new PDO($dsn, $user, $pass, $options);
}

function json_response($data, int $status = 200): void {
    // Ensure no stray output corrupts JSON
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($status);
    }
    echo json_encode($data);
    exit;
}

?>

