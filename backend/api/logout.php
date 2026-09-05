<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

$_SESSION = [];
session_destroy();

echo json_encode(['success' => true]);
