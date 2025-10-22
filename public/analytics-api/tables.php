<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

try {
    $pdo = get_pdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tables = [];

    if ($driver === 'mysql') {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) { $db = 'alumni_portal'; }
        $stmt = $pdo->prepare("SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME");
        $stmt->execute([':db' => $db]);
        $tables = array_map(fn($r) => $r['name'], $stmt->fetchAll());
    } elseif ($driver === 'pgsql') {
        $stmt = $pdo->query("SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema') ORDER BY tablename");
        $tables = array_map(fn($r) => $r['name'], $stmt->fetchAll());
    } elseif ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT name AS name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = array_map(fn($r) => $r['name'], $stmt->fetchAll());
    } elseif ($driver === 'sqlsrv') {
        $stmt = $pdo->query("SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
        $tables = array_map(fn($r) => $r['name'], $stmt->fetchAll());
    } else {
        json_response(['error' => 'Unsupported driver'], 400);
    }

    json_response(['tables' => $tables]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
?>

