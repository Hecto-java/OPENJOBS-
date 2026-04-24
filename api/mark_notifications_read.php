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

mark_notifications_read(db(), (int)current_user()['id']);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);