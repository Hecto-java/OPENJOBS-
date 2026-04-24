<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_auth();

$me = (int)current_user()['id'];
$to = (int)($_POST['to'] ?? 0);
$body = trim($_POST['body'] ?? '');

if (is_post() && $to > 0 && $to !== $me && $body !== '') {
    $pdo = db();
    $targetStmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id=? LIMIT 1');
    $targetStmt->execute([$to]);
    $target = $targetStmt->fetch();

    if ($target) {
        $pdo->prepare('INSERT INTO messages (sender_id,receiver_id,body,created_at) VALUES (?,?,?,NOW())')
            ->execute([$me, $to, $body]);
        log_activity($pdo, $me, 'Envió un mensaje a ' . ($target['name'] ?? 'otro usuario'));
        create_notification($pdo, $to, 'Nuevo mensaje', mb_strimwidth($body, 0, 120, '…', 'UTF-8'), 'chat.php?to=' . $me, 'message');
    }
}
redirect('chat.php?to=' . $to);