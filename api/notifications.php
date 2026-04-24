<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/helpers/helpers.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
if (!current_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$pdo = db();
$userId = (int)current_user()['id'];
$items = fetch_recent_notifications($pdo, $userId, 6);
echo json_encode([
    'ok' => true,
    'unread_count' => unread_notification_count($pdo, $userId),
    'items' => $items,
], JSON_UNESCAPED_UNICODE);