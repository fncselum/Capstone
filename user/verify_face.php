<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!isset($payload['verified']) || !$payload['verified']) {
    echo json_encode(['success' => false, 'message' => 'Verification flag missing']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please rescan RFID.']);
    exit;
}

$_SESSION['face_verified'] = true;

echo json_encode(['success' => true]);
