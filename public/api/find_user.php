<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
if (!Auth::isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$email = trim($_GET['email'] ?? '');
if ($email === '') { echo json_encode(['success'=>false]); exit; }

$u = db()->fetchOne("SELECT id, name, email FROM users WHERE email = ?", [$email]);
if ($u) {
    echo json_encode(['success'=>true, 'user'=>$u]);
} else {
    echo json_encode(['success'=>false]);
}
?>









