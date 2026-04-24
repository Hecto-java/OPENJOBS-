<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_auth();
$pdo = db();
$u = current_user();
$support = support_user($pdo);
$success = flash('success');
$error = '';
if (!$support) {
    $error = 'No se encontró el perfil de soporte técnico.';
}
if (is_post() && $support) {
    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        $error = 'Describe el problema para poder ayudarte.';
    } else {
        $pdo->prepare('INSERT INTO messages (sender_id,receiver_id,body,created_at) VALUES (?,?,?,NOW())')->execute([(int)$u['id'], (int)$support['id'], $body]);
        create_notification($pdo, (int)$support['id'], 'Nuevo reporte técnico', mb_strimwidth($body,0,120,'…','UTF-8'), 'chat.php?to=' . (int)$u['id'], 'support');
        log_activity($pdo, (int)$u['id'], 'Reportó una incidencia a soporte', 'support');
        flash('success', 'Tu mensaje fue enviado al soporte técnico.');
        redirect('support.php');
    }
}
$avatar = uploaded_url($support['avatar'] ?? null);
$chatHref = $support ? 'chat.php?to=' . (int)$support['id'] : 'chat.php';
$companyDirectory = [];
$userDirectory = [];
if (($u['role'] ?? '') === 'support' || ($u['role'] ?? '') === 'admin') {
    $companyDirectory = $pdo->query('SELECT c.name, c.location, c.verified, u.name AS owner_name, u.email FROM companies c JOIN users u ON u.id=c.user_id ORDER BY c.id DESC LIMIT 10')->fetchAll() ?: [];
    $userDirectory = $pdo->query('SELECT name, email, role, created_at FROM users ORDER BY id DESC LIMIT 10')->fetchAll() ?: [];
}
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
<title>Soporte técnico · OpenJobs</title>
</head>
<body class="page-shell">
<div class="container py-4 py-lg-5">
    <div class="glass-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="pill-stat mb-2"><i class="bi bi-life-preserver"></i> Atención técnica</span>
                <h1 class="section-title mb-1">Soporte OpenJobs</h1>
                <p class="section-subtitle mb-0">Reporta problemas técnicos relacionados con tu cuenta, vacantes, postulaciones o el funcionamiento general de la plataforma OpenJobs.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-soft" href="dashboard.php">Volver</a>
                <a class="btn btn-gradient" href="<?= e($chatHref) ?>">Abrir chat de soporte</a>
            </div>
        </div>
    </div>
    <?php if($success): ?><div class="alert alert-success rounded-4"><?= e($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger rounded-4"><?= e($error) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card h-100">
                <h5 class="fw-bold mb-3">Perfil de soporte técnico</h5>
                <div class="showcase-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php if($avatar): ?>
                            <img src="<?= e($avatar) ?>" class="user-chip-avatar" alt="soporte">
                        <?php else: ?>
                            <span class="user-chip-fallback"><i class="bi bi-headset"></i></span>
                        <?php endif; ?>
                        <div>
                            <div class="fw-semibold"><?= e($support['name'] ?? 'Soporte OpenJobs') ?></div>
                            <div class="small section-subtitle">Equipo de soporte técnico</div>
                        </div>
                    </div>
                    <div class="small section-subtitle mb-2">Canales disponibles</div>
                    <div class="d-grid gap-2">
                        <div class="mini-stat"><strong>Canal principal:</strong> Chat interno de OpenJobs</div>
                        <div class="mini-stat"><strong>Tipo de ayuda:</strong> Incidencias técnicas y funcionamiento de la plataforma</div>
                        <div class="mini-stat"><strong>Estado:</strong> Soporte operativo dentro del sistema</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel-card h-100">
                <?php if(($u['role'] ?? '') === 'support'): ?>
                    <h5 class="fw-bold mb-3">Mesa de ayuda interna</h5>
                    <?php $tickets = $pdo->prepare('SELECT m.body,m.created_at,sender.name,sender.role FROM messages m JOIN users sender ON sender.id=m.sender_id WHERE m.receiver_id=? ORDER BY m.id DESC LIMIT 8'); $tickets->execute([(int)$u['id']]); $tickets = $tickets->fetchAll(); ?>
                    <?php if(!$tickets): ?><div class="section-subtitle">No hay tickets recientes.</div><?php endif; ?>
                    <?php foreach($tickets as $ticket): ?>
                        <div class="showcase-card mb-3">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold"><?= e($ticket['name']) ?></div>
                                    <div class="small section-subtitle text-capitalize"><?= e(role_label($ticket['role'])) ?></div>
                                </div>
                                <div class="small section-subtitle"><?= e($ticket['created_at']) ?></div>
                            </div>
                            <div class="mt-2"><?= e($ticket['body']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if($companyDirectory || $userDirectory): ?>
                        <div class="panel-card mt-4 mb-4 support-directory-entry">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <h5 class="fw-bold mb-1">Directorio separado</h5>
                                    <p class="section-subtitle mb-0">Las listas de usuarios y empresas están aparte de la mesa de ayuda para no mezclar tickets con directorios.</p>
                                </div>
                                <div class="panel-quicklinks">
                                    <a class="btn btn-soft" href="#usuarios"><i class="bi bi-people"></i>Ir a usuarios</a>
                                    <a class="btn btn-soft" href="#empresas"><i class="bi bi-buildings"></i>Ir a empresas</a>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-lg-6" id="usuarios">
                                <div class="showcase-card h-100">
                                    <div class="fw-bold mb-3">Ver usuarios</div>
                                    <?php foreach($userDirectory as $user): ?>
                                        <div class="entity-list-card">
                                            <div class="fw-semibold"><?= e($user['name']) ?></div>
                                            <div class="small section-subtitle"><?= e($user['email']) ?></div>
                                            <div class="entity-list-meta">
                                                <span class="entity-tag"><i class="bi bi-person-badge"></i><?= e(role_label($user['role'])) ?></span>
                                                <span class="entity-tag"><i class="bi bi-calendar3"></i><?= e(substr((string)$user['created_at'],0,10)) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-lg-6" id="empresas">
                                <div class="showcase-card h-100">
                                    <div class="fw-bold mb-3">Ver empresas</div>
                                    <?php foreach($companyDirectory as $company): ?>
                                        <div class="entity-list-card">
                                            <div class="fw-semibold"><?= e($company['name']) ?></div>
                                            <div class="small section-subtitle"><?= e($company['location'] ?: 'Sin ubicación registrada') ?></div>
                                            <div class="small"><?= e($company['email']) ?></div>
                                            <div class="entity-list-meta">
                                                <span class="entity-tag"><i class="bi bi-person"></i><?= e($company['owner_name']) ?></span>
                                                <span class="entity-tag"><i class="bi bi-patch-check"></i><?= $company['verified'] ? 'Verificada' : 'Pendiente' ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-gradient" href="chat.php">Abrir conversaciones</a>
                        <a class="btn btn-soft" href="dashboard.php">Ver dashboard</a>
                    </div>
                <?php else: ?>
                    <h5 class="fw-bold mb-3">Reportar una falla</h5>
                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Describe el problema</label>
                            <textarea name="body" class="form-control" rows="8" placeholder="Ejemplo: al abrir mi perfil aparece un error, la IA no genera respuesta o las notificaciones se ven cortadas." required></textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button class="btn btn-gradient">Enviar a soporte</button>
                            <a class="btn btn-soft" href="<?= e($chatHref) ?>">Continuar por chat</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>