<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

Auth::requireAnyRole(['admin','staff']);

try {
	// Safety: allow only admins to delete all
	if (!Auth::hasRole('admin')) { echo json_encode(['success'=>false,'message'=>'Only admin can delete all']); exit; }
	
	db()->execute("DELETE FROM feedback");
	echo json_encode(['success'=>true]);
} catch (Exception $e) {
	echo json_encode(['success'=>false,'message'=>'Delete all failed']);
}





