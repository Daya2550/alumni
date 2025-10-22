<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function sanitize_identifier(string $name): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        json_response(['error' => 'Invalid identifier'], 400);
    }
    return $name;
}

function build_aggregation_sql(string $driver, string $table, array $groupBys, array $metrics, ?string $where): array {
    $quotedTable = $driver === 'mysql' || $driver === 'sqlite' ? "`{$table}`" : '"' . $table . '"';

    $selects = [];
    $groupClauses = [];
    $params = [];

    foreach ($groupBys as $g) {
        $col = sanitize_identifier($g);
        $quoted = $driver === 'mysql' || $driver === 'sqlite' ? "`{$col}`" : '"' . $col . '"';
        $selects[] = "$quoted AS $col";
        $groupClauses[] = $quoted;
    }

    foreach ($metrics as $m) {
        $fn = strtoupper((string)($m['fn'] ?? 'COUNT'));
        $col = isset($m['column']) && $m['column'] !== '*' ? sanitize_identifier((string)$m['column']) : '*';
        $allowed = ['COUNT','SUM','AVG','MIN','MAX'];
        if (!in_array($fn, $allowed, true)) {
            json_response(['error' => 'Unsupported aggregation'], 400);
        }
        if ($col === '*' && $fn !== 'COUNT') {
            json_response(['error' => $fn . ' requires a specific column (not *)'], 400);
        }
        $colExpr = $col === '*' ? '*' : (($driver === 'mysql' || $driver === 'sqlite') ? "`{$col}`" : '"' . $col . '"');
        $alias = strtolower($fn) . '_' . ($col === '*' ? 'all' : $col);
        $selects[] = "$fn($colExpr) AS $alias";
    }

    $sql = 'SELECT ' . implode(', ', $selects) . ' FROM ' . $quotedTable;
    if ($where) {
        $sql .= ' WHERE ' . $where;
    }
    if ($groupClauses) {
        $sql .= ' GROUP BY ' . implode(', ', $groupClauses);
    }
    return [$sql, $params];
}

try {
    $pdo = get_pdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $input = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($input)) { $input = []; }

    $table = isset($input['table']) ? sanitize_identifier((string)$input['table']) : '';
    $groupBys = isset($input['groupBy']) && is_array($input['groupBy']) ? $input['groupBy'] : [];
    $metrics = isset($input['metrics']) && is_array($input['metrics']) ? $input['metrics'] : [['fn' => 'COUNT', 'column' => '*']];
    $where = isset($input['where']) && is_string($input['where']) ? $input['where'] : null; // Note: optional, use carefully
    $filters = isset($input['filters']) && is_array($input['filters']) ? $input['filters'] : [];
    $raw = isset($input['raw']) ? (bool)$input['raw'] : false;
    $columns = isset($input['columns']) && is_array($input['columns']) ? $input['columns'] : [];
    $limit = isset($input['limit']) ? max(0, (int)$input['limit']) : 100;
    $offset = isset($input['offset']) ? max(0, (int)$input['offset']) : 0;
    $sortCol = isset($input['sortCol']) && is_string($input['sortCol']) ? (string)$input['sortCol'] : '';
    $sortDir = strtoupper(isset($input['sortDir']) && is_string($input['sortDir']) ? (string)$input['sortDir'] : 'ASC');

    if ($table === '') {
        json_response(['error' => 'Missing table'], 400);
    }
    // Build WHERE from structured filters (AND-combined)
    $whereParts = [];
    foreach ($filters as $f) {
        if (!isset($f['col']) || !isset($f['op'])) { continue; }
        $col = sanitize_identifier((string)$f['col']);
        $op  = (string)$f['op'];
        $qCol = ($driver === 'mysql' || $driver === 'sqlite') ? "`{$col}`" : '"' . $col . '"';
        switch ($op) {
            case 'eq': $whereParts[] = $qCol . ' = ' . $pdo->quote((string)($f['value'] ?? '')); break;
            case 'neq': $whereParts[] = $qCol . ' != ' . $pdo->quote((string)($f['value'] ?? '')); break;
            case 'contains': $whereParts[] = $qCol . ' LIKE ' . $pdo->quote('%' . ((string)($f['value'] ?? '')) . '%'); break;
            case 'gt': $whereParts[] = $qCol . ' > ' . $pdo->quote((string)($f['value'] ?? '')); break;
            case 'lt': $whereParts[] = $qCol . ' < ' . $pdo->quote((string)($f['value'] ?? '')); break;
            case 'between': $whereParts[] = '(' . $qCol . ' BETWEEN ' . $pdo->quote((string)($f['value'] ?? '')) . ' AND ' . $pdo->quote((string)($f['value2'] ?? '')) . ')'; break;
            case 'isnull': $whereParts[] = $qCol . ' IS NULL'; break;
            case 'notnull': $whereParts[] = $qCol . ' IS NOT NULL'; break;
        }
    }
    $filtersWhere = $whereParts ? implode(' AND ', $whereParts) : null;

    if ($raw === true) {
        $cols = '*';
        if ($columns) {
            $safeCols = array_map(function($c) use ($driver){
                $c2 = sanitize_identifier((string)$c);
                return ($driver === 'mysql' || $driver === 'sqlite') ? "`{$c2}`" : '"' . $c2 . '"';
            }, $columns);
            $cols = implode(', ', $safeCols);
        }
        $quotedTable = ($driver === 'mysql' || $driver === 'sqlite') ? "`{$table}`" : '"' . $table . '"';
        $sql = 'SELECT ' . $cols . ' FROM ' . $quotedTable;
        $whereSQL = $where ?: $filtersWhere;
        if ($whereSQL) { $sql .= ' WHERE ' . $whereSQL; }
        if ($sortCol !== '') {
            $sc = sanitize_identifier($sortCol);
            $scq = ($driver === 'mysql' || $driver === 'sqlite') ? "`{$sc}`" : '"' . $sc . '"';
            $dir = $sortDir === 'DESC' ? 'DESC' : 'ASC';
            $sql .= ' ORDER BY ' . $scq . ' ' . $dir;
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
            if ($offset > 0) { $sql .= ' OFFSET ' . $offset; }
        }
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();
        json_response(['rows' => $rows, 'sql' => $sql]);
    }
    if (!$groupBys && !$metrics) {
        json_response(['error' => 'No fields requested'], 400);
    }

    $aggWhere = $where ?: $filtersWhere;
    list($sql, $params) = build_aggregation_sql($driver, $table, $groupBys, $metrics, $aggWhere);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response(['rows' => $rows, 'sql' => $sql]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
?>

