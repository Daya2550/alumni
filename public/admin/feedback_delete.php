<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

Auth::requireAnyRole(['admin','staff']);

$id = sanitizeInput($_POST['id'] ?? '');
if (!$id) { echo json_encode(['success'=>false,'message'=>'Missing id']); exit; }

try {
	$exists = db()->fetchOne("SELECT id FROM feedback WHERE id = ?", [$id]);
	if (!$exists) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }
	
	db()->execute("DELETE FROM feedback WHERE id = ?", [$id]);
	echo json_encode(['success'=>true]);
} catch (Exception $e) {
	echo json_encode(['success'=>false,'message'=>'Delete failed']);
}





