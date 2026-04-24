<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';

require_auth();
$u = current_user();
$pdo = db();
$me = (int)$u['id'];
$selected = (int)($_GET['to'] ?? 0);

$contactsStmt = $pdo->prepare("SELECT DISTINCT
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS contact_id,
        usr.name,
        usr.role,
        MAX(m.id) AS last_message_id,
        MAX(m.created_at) AS last_message_at
    FROM messages m
    JOIN users usr ON usr.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY contact_id, usr.name, usr.role
    ORDER BY last_message_id DESC");
$contactsStmt->execute([$me, $me, $me, $me]);
$contacts = $contactsStmt->fetchAll() ?: [];

$contactIds = array_map(fn($c) => (int)$c['contact_id'], $contacts);
if ($selected > 0 && !in_array($selected, $contactIds, true)) {
    $extraStmt = $pdo->prepare('SELECT id AS contact_id, name, role FROM users WHERE id=? AND id<>? LIMIT 1');
    $extraStmt->execute([$selected, $me]);
    $extra = $extraStmt->fetch();
    if ($extra) {
        array_unshift($contacts, $extra);
    }
}

if (!$selected && !empty($contacts)) {
    $selected = (int)$contacts[0]['contact_id'];
}

$selectedContact = null;
foreach ($contacts as $c) {
    if ((int)$c['contact_id'] === $selected) {
        $selectedContact = $c;
        break;
    }
}
if (!$selectedContact && $selected > 0) {
    $selectedStmt = $pdo->prepare('SELECT id AS contact_id, name, role FROM users WHERE id=? LIMIT 1');
    $selectedStmt->execute([$selected]);
    $selectedContact = $selectedStmt->fetch() ?: null;
}

$msgs = [];
if ($selected > 0) {
    $s = $pdo->prepare('SELECT m.*, sender.name AS sender_name, sender.role AS sender_role
        FROM messages m
        JOIN users sender ON sender.id = m.sender_id
        WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
        ORDER BY m.id');
    $s->execute([$me, $selected, $selected, $me]);
    $msgs = $s->fetchAll() ?: [];
}
$lastMessageId = $msgs ? (int)end($msgs)['id'] : 0;
$unreadNotifications = unread_notification_count($pdo, $me);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/styles.css">
<title>Mensajes · OpenJobs</title>
</head>
<body class="page-shell" data-notification-poll="1">
<div id="loader"><div class="spinner-border text-primary"></div></div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="appToast" class="toast border-0 shadow"><div class="toast-body text-white bg-success rounded-4">Acción realizada correctamente</div></div>
</div>

<nav class="navbar navbar-premium mb-3">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand brand-gradient" href="dashboard.php">OpenJobs</a>
        <div class="d-flex gap-2 align-items-center">
            <div class="dropdown">
                <button class="btn btn-soft position-relative" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <span class="notification-badge <?= $unreadNotifications ? '' : 'd-none' ?>" id="notificationBadge"><?= $unreadNotifications ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-menu p-0 overflow-hidden">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <strong>Notificaciones</strong>
                        <button class="btn btn-link btn-sm text-decoration-none p-0" id="markNotificationsRead">Marcar leídas</button>
                    </div>
                    <div id="notificationList" class="notification-list">
                        <div class="p-3 text-secondary small">Cargando…</div>
                    </div>
                </div>
            </div>
            <button onclick="toggleTheme()" class="btn btn-soft"><i class="bi bi-moon-stars"></i></button>
            <a class="btn btn-soft" href="support.php">Soporte</a><a class="btn btn-soft" href="dashboard.php">Volver</a>
        </div>
    </div>
</nav>

<div class="container-fluid py-3 py-lg-4">
    <div class="glass-card mb-4 reveal">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="section-title mb-1">Mensajería en tiempo real</h1>
                <p class="section-subtitle mb-0">El listado solo muestra conversaciones iniciadas dentro del sistema. También puedes abrir un chat nuevo desde una vacante, postulación o soporte.</p>
            </div>
            <a class="btn btn-gradient <?= $selected ? '' : 'disabled' ?>" href="ai.php?action=suggest_reply&to=<?= $selected ?>"><i class="bi bi-stars me-2"></i>Sugerir respuesta con IA</a>
        </div>
    </div>

    <div class="row g-3 chat-layout-card">
        <div class="col-lg-3">
            <div class="chat-card h-100 chat-sidebar">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Conversaciones</h5>
                    <span class="badge text-bg-success">En vivo</span>
                </div>
                <?php if(!$contacts): ?>
                    <div class="empty-state">Aún no tienes conversaciones activas. Inicia una desde una vacante, una postulación o soporte.</div>
                <?php else: ?>
                <div class="chat-contact-list">
                <?php foreach($contacts as $c): ?>
                    <a class="chat-contact <?= $selected===(int)$c['contact_id']?'active':'' ?>" href="chat.php?to=<?= (int)$c['contact_id'] ?>">
                        <div class="fw-semibold"><?= e($c['name']) ?></div>
                        <small class="section-subtitle text-capitalize"><?= e(role_label($c['role'])) ?></small>
                    </a>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="chat-card p-0 overflow-hidden chat-main">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0"><?= $selectedContact ? e($selectedContact['name']) : 'Selecciona una conversación' ?></h5>
                        <small class="section-subtitle">
                            <?php if($selectedContact): ?>
                                Estás hablando con <?= e(role_label($selectedContact['role'])) ?>
                            <?php else: ?>
                                Transparencia, reclutamiento y seguimiento
                            <?php endif; ?>
                        </small>
                    </div>
                    <span class="pill-stat"><i class="bi bi-circle-fill text-success"></i> Actualización automática</span>
                </div>
                <div class="chat-window" id="chatWindow" data-chat-selected="<?= $selected ?>" data-chat-last-id="<?= $lastMessageId ?>">
                    <?php if(!$selectedContact): ?>
                        <div class="chat-empty-state"><div><h5 class="fw-bold mb-2">No hay conversación seleccionada</h5><div>Selecciona una conversación del lado izquierdo o inicia una desde una vacante, postulación o soporte.</div></div></div>
                    <?php elseif(!$msgs): ?>
                        <div class="chat-empty-state"><div><h5 class="fw-bold mb-2">Nuevo chat</h5><div>Vas a iniciar una conversación con <?= e($selectedContact['name']) ?> (<?= e(role_label($selectedContact['role'])) ?>).</div></div></div>
                    <?php endif; ?>
                    <?php foreach($msgs as $m): ?>
                        <div class="message-row <?= $m['sender_id']==$me?'sent':'received' ?>" data-message-id="<?= (int)$m['id'] ?>">
                            <div class="bubble">
                                <div class="small opacity-75 mb-1"><?= e($m['sender_name']) ?> · <?= e(role_label($m['sender_role'])) ?></div>
                                <div><?= e($m['body']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-top p-3">
                    <form class="row g-2" method="post" action="send_message.php">
                        <input type="hidden" name="to" value="<?= $selected ?>">
                        <div class="col-md-9">
                            <input class="form-control" name="body" placeholder="<?= $selectedContact ? 'Escribe un mensaje para ' . e($selectedContact['name']) . '...' : 'Selecciona una conversación...' ?>" <?= $selectedContact ? 'required' : 'disabled' ?>>
                        </div>
                        <div class="col-md-3"><button class="btn btn-gradient w-100" <?= $selectedContact ? '' : 'disabled' ?>>Enviar</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>window.OPENJOBS_ME = <?= $me ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>