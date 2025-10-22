<?php
declare(strict_types=1);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';

function sanitize_identifier(string $name): string {
	if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
		json_response(['error' => 'Invalid identifier'], 400);
	}
	return $name;
}

try {
	$pdo = get_pdo();
	$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
	$table = isset($_GET['table']) ? sanitize_identifier((string) $_GET['table']) : '';
	if ($table === '') {
		json_response(['error' => 'Missing table'], 400);
	}

	$columns = [];
	if ($driver === 'mysql') {
		$db = $pdo->query('SELECT DATABASE()')->fetchColumn();
		if (!$db) { $db = 'alumni_portal'; }
		$stmt = $pdo->prepare("SELECT COLUMN_NAME AS name, DATA_TYPE AS data_type FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl ORDER BY ORDINAL_POSITION");
		$stmt->execute([':db' => $db, ':tbl' => $table]);
		$columns = $stmt->fetchAll();
	} elseif ($driver === 'pgsql') {
		$stmt = $pdo->prepare("SELECT column_name AS name, data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :tbl ORDER BY ordinal_position");
		$stmt->execute([':tbl' => $table]);
		$columns = $stmt->fetchAll();
	} elseif ($driver === 'sqlite') {
		$stmt = $pdo->query("PRAGMA table_info('" . $table . "')");
		$columns = array_map(function($r){ return ['name' => $r['name'], 'data_type' => $r['type']]; }, $stmt->fetchAll());
	} elseif ($driver === 'sqlsrv') {
		$stmt = $pdo->prepare("SELECT COLUMN_NAME AS name, DATA_TYPE AS data_type FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :tbl ORDER BY ORDINAL_POSITION");
		$stmt->execute([':tbl' => $table]);
		$columns = $stmt->fetchAll();
	} else {
		json_response(['error' => 'Unsupported driver'], 400);
	}

	json_response(['columns' => $columns]);
} catch (Throwable $e) {
	json_response(['error' => $e->getMessage()], 500);
}
?>

