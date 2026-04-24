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

$me = (int)current_user()['id'];
$selected = (int)($_GET['to'] ?? 0);
$afterId = (int)($_GET['after_id'] ?? 0);
if ($selected <= 0) {
    echo json_encode(['ok' => true, 'messages' => [], 'last_id' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare('SELECT m.id, m.sender_id, m.receiver_id, m.body, m.created_at, sender.name AS sender_name, sender.role AS sender_role
    FROM messages m
    JOIN users sender ON sender.id = m.sender_id
    WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
      AND m.id > ?
    ORDER BY m.id ASC');
$stmt->execute([$me, $selected, $selected, $me, $afterId]);
$messages = $stmt->fetchAll() ?: [];
$lastId = $afterId;
foreach ($messages as $message) {
    $lastId = max($lastId, (int)$message['id']);
}

echo json_encode([
    'ok' => true,
    'messages' => $messages,
    'last_id' => $lastId,
], JSON_UNESCAPED_UNICODE);