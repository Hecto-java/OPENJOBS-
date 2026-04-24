<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/services/RecommendationService.php';
require_once __DIR__ . '/app/services/GeminiService.php'; // <-- CAMBIADO

// ... resto del código igual, cambiar DeepSeekService::buildDashboardInsight por GeminiService::buildDashboardInsight
require_auth();

$u = current_user();
$pdo = db();
$welcome = flash('success');
$stats = ['applications' => 0, 'reviews' => 0, 'jobs' => 0, 'messages' => 0];
$checklist = [];
$heroTitle = 'Tu centro de control OpenJobs';
$heroText = 'OpenJobs conecta talento y empresas en un flujo claro, simple y 100% gratis.';
$primaryAction = ['href' => 'profile.php', 'label' => 'Completar perfil'];
$secondaryAction = ['href' => 'jobs.php', 'label' => 'Explorar vacantes'];
$insights = [];
$recentRows = [];
$chartLabels = [];
$chartValues = [];

if ($u['role'] === 'talent') {
    $stmt = $pdo->prepare('SELECT headline, bio, skills, cv_file, location, experience_years, xp FROM talent_profiles WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $profile = $stmt->fetch() ?: [];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM applications WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $stats['applications'] = (int)$stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM reviews WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $stats['reviews'] = (int)$stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM messages WHERE sender_id=? OR receiver_id=?');
    $stmt->execute([$u['id'], $u['id']]);
    $stats['messages'] = (int)$stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM experience_work WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $experienceCount = (int)$stmt->fetch()['c'];

    $profileCompletion = 0;
    foreach (['headline', 'bio', 'skills', 'cv_file', 'location'] as $field) {
        if (!empty($profile[$field])) {
            $profileCompletion += 18;
        }
    }
    if ($experienceCount > 0) {
        $profileCompletion += 10;
    }
    $profileCompletion = min(100, $profileCompletion);

    $jobs = $pdo->query('SELECT j.id, j.title, j.description, j.technology, j.modality, j.experience_required, j.location
        FROM jobs j WHERE j.status="active" ORDER BY j.id DESC LIMIT 12')->fetchAll();
    $recommendedMap = RecommendationService::recommend((int)$u['id'], $jobs);
    $recommended = [];
    foreach ($jobs as $job) {
        if (isset($recommendedMap[(int)$job['id']])) {
            $recommended[] = array_merge($job, $recommendedMap[(int)$job['id']]);
        }
    }
    usort($recommended, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
    $recommended = array_slice($recommended, 0, 3);

    $appStmt = $pdo->prepare('SELECT a.status, a.created_at, j.title, c.name company_name
        FROM applications a
        JOIN jobs j ON j.id=a.job_id
        LEFT JOIN companies c ON c.id=j.company_id
        WHERE a.user_id=?
        ORDER BY a.created_at DESC LIMIT 5');
    $appStmt->execute([$u['id']]);
    $recentRows = $appStmt->fetchAll();

    $checklist = [
        ['ok' => !empty($profile['headline']), 'label' => 'Completar titular profesional'],
        ['ok' => !empty($profile['skills']), 'label' => 'Agregar habilidades clave'],
        ['ok' => !empty($profile['bio']), 'label' => 'Contar tu experiencia en OpenJobs'],
        ['ok' => !empty($profile['cv_file']), 'label' => 'Subir CV en PDF'],
        ['ok' => $experienceCount > 0, 'label' => 'Registrar experiencia laboral'],
    ];
    $heroTitle = 'Dashboard de talento';
    $heroText = 'Completa tu perfil, recibe recomendaciones y da seguimiento a tus postulaciones sin pagar nada.';
    $primaryAction = ['href' => 'profile.php', 'label' => 'Editar mi perfil'];
    $secondaryAction = ['href' => 'jobs.php', 'label' => 'Ver vacantes'];
    $insights = [
        ['label' => 'Perfil completo', 'value' => $profileCompletion . '%'],
        ['label' => 'Experiencias cargadas', 'value' => (string)$experienceCount],
        ['label' => 'XP OpenJobs', 'value' => (string)((int)($profile['xp'] ?? 0))],
    ];
    $chartLabels = ['Perfil', 'Postulaciones', 'Mensajes', 'Reseñas'];
    $chartValues = [$profileCompletion, $stats['applications'], $stats['messages'], $stats['reviews']];
} elseif ($u['role'] === 'company') {
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $company = $stmt->fetch() ?: [];
    $companyId = (int)($company['id'] ?? 0);

    if ($companyId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM jobs WHERE company_id=?');
        $stmt->execute([$companyId]);
        $stats['jobs'] = (int)$stmt->fetch()['c'];

        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.company_id=?');
        $stmt->execute([$companyId]);
        $stats['applications'] = (int)$stmt->fetch()['c'];

        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM reviews WHERE company_id=?');
        $stmt->execute([$companyId]);
        $stats['reviews'] = (int)$stmt->fetch()['c'];

        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM jobs WHERE company_id=? AND status="active"');
        $stmt->execute([$companyId]);
        $activeJobs = (int)$stmt->fetch()['c'];

        $stmt = $pdo->prepare('SELECT j.title, j.status, COUNT(a.id) applications
            FROM jobs j
            LEFT JOIN applications a ON a.job_id=j.id
            WHERE j.company_id=?
            GROUP BY j.id
            ORDER BY j.id DESC LIMIT 5');
        $stmt->execute([$companyId]);
        $recentRows = $stmt->fetchAll();
    } else {
        $activeJobs = 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM messages WHERE sender_id=? OR receiver_id=?');
    $stmt->execute([$u['id'], $u['id']]);
    $stats['messages'] = (int)$stmt->fetch()['c'];

    $checklist = [
        ['ok' => !empty($company['description']), 'label' => 'Agregar descripción de empresa'],
        ['ok' => !empty($company['website']), 'label' => 'Registrar sitio web'],
        ['ok' => !empty($company['location']), 'label' => 'Completar ubicación'],
        ['ok' => !empty($company['latitude']) && !empty($company['longitude']), 'label' => 'Configurar mapa'],
        ['ok' => $stats['jobs'] > 0, 'label' => 'Publicar al menos una vacante'],
    ];
    $heroTitle = 'Dashboard de empresa';
    $heroText = 'Publica vacantes, revisa postulaciones y administra tu presencia en OpenJobs desde un solo lugar.';
    $primaryAction = ['href' => 'company_jobs.php', 'label' => 'Gestionar vacantes'];
    $secondaryAction = ['href' => 'profile.php', 'label' => 'Editar empresa'];
    $insights = [
        ['label' => 'Vacantes activas', 'value' => (string)$activeJobs],
        ['label' => 'Empresa verificada', 'value' => !empty($company['verified']) ? 'Sí' : 'No'],
        ['label' => 'Mensajes', 'value' => (string)$stats['messages']],
    ];
    $chartLabels = ['Vacantes', 'Postulaciones', 'Reseñas', 'Mensajes'];
    $chartValues = [$stats['jobs'], $stats['applications'], $stats['reviews'], $stats['messages']];
} elseif ($u['role'] === 'support') {
    $stats = [
        'tickets' => 0,
        'messages' => 0,
        'users' => (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
        'jobs' => (int)$pdo->query('SELECT COUNT(*) c FROM jobs')->fetch()['c'],
    ];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM messages WHERE receiver_id=?');
    $stmt->execute([$u['id']]);
    $stats['tickets'] = (int)$stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM messages WHERE sender_id=? OR receiver_id=?');
    $stmt->execute([$u['id'], $u['id']]);
    $stats['messages'] = (int)$stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT m.body, m.created_at, sender.name, sender.role
        FROM messages m
        JOIN users sender ON sender.id=m.sender_id
        WHERE m.receiver_id=?
        ORDER BY m.id DESC LIMIT 8');
    $stmt->execute([$u['id']]);
    $recentRows = $stmt->fetchAll();

    $checklist = [
        ['ok' => true, 'label' => 'Revisar tickets nuevos'],
        ['ok' => true, 'label' => 'Dar seguimiento por chat'],
        ['ok' => true, 'label' => 'Escalar incidencias al admin'],
        ['ok' => $stats['tickets'] >= 0, 'label' => 'Mantener mesa de ayuda operativa'],
        ['ok' => true, 'label' => 'Responder con tono claro y técnico'],
    ];
    $heroTitle = 'Dashboard de soporte';
    $heroText = 'Centraliza incidencias, da seguimiento por chat y mantén una mesa de ayuda clara dentro de OpenJobs.';
    $primaryAction = ['href' => 'support.php', 'label' => 'Abrir mesa de ayuda'];
    $secondaryAction = ['href' => 'chat.php', 'label' => 'Ver conversaciones'];
    $insights = [
        ['label' => 'Tickets recibidos', 'value' => (string)$stats['tickets']],
        ['label' => 'Mensajes totales', 'value' => (string)$stats['messages']],
        ['label' => 'Cobertura', 'value' => 'Plataforma'],
    ];
    $chartLabels = ['Tickets', 'Mensajes', 'Usuarios', 'Vacantes'];
    $chartValues = [$stats['tickets'], $stats['messages'], $stats['users'], $stats['jobs']];
} else {
    $stats = [
        'users' => (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
        'applications' => (int)$pdo->query('SELECT COUNT(*) c FROM applications')->fetch()['c'],
        'reviews' => (int)$pdo->query('SELECT COUNT(*) c FROM reviews')->fetch()['c'],
        'jobs' => (int)$pdo->query('SELECT COUNT(*) c FROM jobs')->fetch()['c'],
        'messages' => (int)$pdo->query('SELECT COUNT(*) c FROM messages')->fetch()['c'],
    ];

    $recentRows = $pdo->query('SELECT a.action, a.type, a.created_at, u.name
        FROM activity_logs a
        LEFT JOIN users u ON u.id=a.user_id
        ORDER BY a.id DESC LIMIT 8')->fetchAll();

    $pendingCompanies = (int)$pdo->query('SELECT COUNT(*) c FROM companies WHERE verified=0')->fetch()['c'];
    $activeTalent = (int)$pdo->query('SELECT COUNT(*) c FROM users WHERE role="talent"')->fetch()['c'];

    $checklist = [
        ['ok' => true, 'label' => 'Monitorear usuarios'],
        ['ok' => true, 'label' => 'Revisar actividad reciente'],
        ['ok' => $stats['jobs'] > 0, 'label' => 'Validar vacantes activas'],
        ['ok' => $stats['applications'] >= 0, 'label' => 'Dar seguimiento a postulaciones'],
        ['ok' => true, 'label' => 'Mantener OpenJobs gratis y claro'],
    ];
    $heroTitle = 'Panel de control OpenJobs';
    $heroText = 'Supervisa la actividad del sistema, gestiona usuarios y asegura la calidad de la información dentro de OpenJobs.';
    $primaryAction = ['href' => 'admin.php', 'label' => 'Abrir panel admin'];
    $secondaryAction = ['href' => 'jobs.php', 'label' => 'Revisar vacantes'];
    $insights = [
        ['label' => 'Empresas pendientes', 'value' => (string)$pendingCompanies],
        ['label' => 'Talento registrado', 'value' => (string)$activeTalent],
        ['label' => 'Mensajes totales', 'value' => (string)$stats['messages']],
        ['label' => 'Soporte técnico', 'value' => 'Activo'],
    ];
    $chartLabels = ['Vacantes', 'Postulaciones', 'Reseñas', 'Mensajes'];
    $chartValues = [$stats['jobs'], $stats['applications'], $stats['reviews'], $stats['messages']];
}

$completed = count(array_filter($checklist, fn($i) => $i['ok']));
$progress = count($checklist) ? (int)round(($completed / count($checklist)) * 100) : 0;
$avatar = uploaded_url($u['avatar'] ?? null) ?: 'https://placehold.co/136x136';
$roleLabel = role_label($u['role']);
$notificationCount = unread_notification_count($pdo, (int)$u['id']);
$supportLink = support_chat_link($pdo);
$aiInsight = null;
if (isset($_GET['ai']) && $_GET['ai'] === '1' && GeminiService::isConfigured()) {
    $aiInsight = GeminiService::buildDashboardInsight($roleLabel, $stats, $heroText ?? '');
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<title>Dashboard · OpenJobs</title>
</head>
<body class="page-shell" data-notification-poll="1">
<div id="loader"><div class="spinner-border text-primary"></div></div>
<div class="container-fluid">
<div class="row">
<aside class="col-lg-2 sidebar-premium">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="brand-gradient fs-4">OpenJobs</div>
        <div class="d-flex gap-2 align-items-center">
            <div class="dropdown">
                <button class="btn btn-soft btn-sm position-relative" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <span class="notification-badge <?= $notificationCount ? '' : 'd-none' ?>" id="notificationBadge"><?= $notificationCount ?></span>
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
            <a class="btn btn-soft btn-sm" href="support.php"><i class="bi bi-life-preserver"></i></a>
            <button onclick="toggleTheme()" class="btn btn-soft btn-sm"><i class="bi bi-moon-stars"></i></button>
        </div>
    </div>
    <div class="showcase-card mb-3">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= e($avatar) ?>" alt="avatar" class="user-chip-avatar">
            <div>
                <div class="fw-semibold"><?= e($u['name']) ?></div>
                <div class="small section-subtitle"><?= e($roleLabel) ?></div>
            </div>
        </div>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="index.php"><i class="bi bi-house-door"></i>Inicio</a>
        <a class="nav-link active" href="dashboard.php"><i class="bi bi-grid"></i>Dashboard</a>
        <a class="nav-link" href="profile.php"><i class="bi bi-person"></i>Perfil</a>
        <?php if($u['role']==='talent'): ?>
            <a class="nav-link" href="jobs.php"><i class="bi bi-search"></i>Explorar vacantes</a>
            <a class="nav-link" href="applications.php"><i class="bi bi-send-check"></i>Postulaciones</a>
            <a class="nav-link" href="reviews.php"><i class="bi bi-star"></i>Reseñas</a>
        <?php elseif($u['role']==='company'): ?>
            <a class="nav-link" href="company_jobs.php"><i class="bi bi-briefcase"></i>Vacantes</a>
            <a class="nav-link" href="reviews.php"><i class="bi bi-star"></i>Reseñas</a>
        <?php endif; ?>
        <a class="nav-link" href="chat.php"><i class="bi bi-chat-dots"></i>Mensajes</a>
        <a class="nav-link" href="support.php"><i class="bi bi-life-preserver"></i>Soporte técnico</a>
        <a class="nav-link" href="ai_chat.php">
    <a class="nav-link" href="ai_chat.php">
    <i class="bi bi-robot"></i> Asistente AI
</a>
        <?php if($u['role']==='support'): ?><a class="nav-link" href="support.php"><i class="bi bi-headset"></i>Mesa de ayuda</a><?php endif; ?>
        <?php if($u['role']==='admin'): ?><a class="nav-link" href="admin.php"><i class="bi bi-shield"></i>Admin</a><?php endif; ?>
        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i>Salir</a>
    </nav>
</aside>
<main class="col-lg-10 p-4 p-md-5">
    <?php if($welcome): ?><div class="alert alert-success rounded-4"><?= e($welcome) ?></div><?php endif; ?>

    <div class="glass-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="pill-stat mb-2"><i class="bi bi-lightning-charge"></i> Flujo inteligente</span>
                <h1 class="section-title mb-1"><?= e($heroTitle) ?></h1>
                <p class="section-subtitle mb-0"><?= e($heroText) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-gradient" href="<?= e($primaryAction['href']) ?>"><?= e($primaryAction['label']) ?></a>
                <a class="btn btn-soft" href="<?= e($secondaryAction['href']) ?>"><?= e($secondaryAction['label']) ?></a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Rol activo</div><div class="metric-value"><?= e($roleLabel) ?></div></div></div>
        <?php if($u['role']==='admin'): ?>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Usuarios</div><div class="metric-value"><?= $stats['users'] ?></div><div class="metric-caption">Cuentas registradas</div></div></div>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Reseñas</div><div class="metric-value"><?= $stats['reviews'] ?></div><div class="metric-caption">Contenido a supervisar</div></div></div>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Mensajes</div><div class="metric-value"><?= $stats['messages'] ?></div><div class="metric-caption">Interacciones del sistema</div></div></div>
        <?php elseif($u['role']==='support'): ?>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Tickets</div><div class="metric-value"><?= $stats['tickets'] ?></div></div></div>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Mensajes</div><div class="metric-value"><?= $stats['messages'] ?></div></div></div>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Usuarios</div><div class="metric-value"><?= $stats['users'] ?></div></div></div>
        <?php else: ?>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label"><?= $u['role']==='company' ? 'Vacantes' : 'Postulaciones' ?></div><div class="metric-value"><?= $u['role']==='company' ? $stats['jobs'] : $stats['applications'] ?></div></div></div>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Reseñas</div><div class="metric-value"><?= $stats['reviews'] ?></div></div></div>
            <div class="col-md-3"><div class="metric-card"><div class="metric-label">Mensajes</div><div class="metric-value"><?= $stats['messages'] ?></div></div></div>
        <?php endif; ?>
    </div>

    <div class="panel-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Dashboard con IA</h5>
                <p class="section-subtitle mb-0">DeepSeek analiza tu actividad y te sugiere el siguiente paso dentro de OpenJobs.</p>
            </div>
            <?php if(GeminiService::isConfigured()): ?>
                <a class="btn btn-gradient" href="dashboard.php?ai=1">Generar insight</a>
            <?php else: ?>
                <span class="badge text-bg-warning">Configura DEEPSEEK_API_KEY para activar esta función</span>
            <?php endif; ?>
        </div>
        <?php if($aiInsight): ?>
            <div class="showcase-card"><?= nl2br(e($aiInsight)) ?></div>
        <?php else: ?>
            <div class="section-subtitle">Puedes usar IA para resumir tus métricas, detectar focos rojos y recibir acciones recomendadas.</div>
        <?php endif; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="panel-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Ruta de uso recomendada</h5>
                        <p class="section-subtitle mb-0">Te muestra qué sigue para sacarle provecho a OpenJobs.</p>
                    </div>
                    <span class="pill-stat"><i class="bi bi-graph-up"></i> <?= $progress ?>% completo</span>
                </div>
                <div class="progress-modern mb-3"><span style="width:<?= $progress ?>%"></span></div>
                <div class="row g-3">
                    <?php foreach($checklist as $item): ?>
                        <div class="col-md-6">
                            <div class="showcase-card h-100">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <div class="fw-semibold"><?= e($item['label']) ?></div>
                                    <span class="badge <?= $item['ok'] ? 'text-bg-success' : 'text-bg-light border' ?>">
                                        <?= $item['ok'] ? 'Listo' : 'Pendiente' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="panel-card h-100">
                <h5 class="fw-bold mb-3">Indicadores rápidos</h5>
                <div class="d-grid gap-3">
                    <?php foreach($insights as $insight): ?>
                        <div class="showcase-card">
                            <div class="small section-subtitle"><?= e($insight['label']) ?></div>
                            <div class="h4 fw-bold mb-0"><?= e($insight['value']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <hr>
                <canvas id="dashboardChart" height="180"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php if($u['role'] === 'talent'): ?>
            <div class="col-xl-7">
                <div class="panel-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Vacantes recomendadas</h5>
                            <p class="section-subtitle mb-0">Estas opciones combinan con tu perfil dentro de OpenJobs y priorizan coincidencia real con IA.</p>
                        </div>
                        <div class="d-flex gap-2"><a class="btn btn-soft btn-sm" href="jobs.php">Ver todo</a><a class="btn btn-gradient btn-sm" href="ai.php?action=recommend_jobs">IA recomendadora</a></div>
                    </div>
                    <?php if(!$recommended): ?>
                        <div class="section-subtitle">Completa tu perfil para recibir mejores recomendaciones.</div>
                    <?php endif; ?>
                    <?php foreach($recommended as $job): ?>
                        <div class="showcase-card mb-3">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold"><?= e($job['title']) ?></div>
                                    <div class="small section-subtitle"><?= e($job['location']) ?> · <?= e($job['modality']) ?></div>
                                    <div class="small mt-2"><?= e($job['reason'] ?? 'Buena compatibilidad con tu perfil.') ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge text-bg-primary rounded-pill mb-2"><?= (int)($job['score'] ?? 0) ?>% match</span>
                                    <?php if(!empty($job['fit'])): ?><div class="small text-capitalize section-subtitle">Encaje <?= e($job['fit']) ?></div><?php endif; ?>
                                    <div><a class="btn btn-gradient btn-sm" href="job.php?id=<?= (int)$job['id'] ?>">Abrir</a></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="panel-card h-100">
                    <h5 class="fw-bold mb-3">Seguimiento de postulaciones</h5>
                    <?php if(!$recentRows): ?>
                        <div class="section-subtitle">Aún no tienes postulaciones. Empieza explorando vacantes.</div>
                    <?php endif; ?>
                    <?php foreach($recentRows as $row): ?>
                        <div class="showcase-card mb-3">
                            <div class="fw-semibold"><?= e($row['title']) ?></div>
                            <div class="small section-subtitle"><?= e($row['company_name']) ?></div>
                            <div class="d-flex justify-content-between mt-2 small">
                                <span class="badge text-bg-light border text-capitalize"><?= e($row['status']) ?></span>
                                <span><?= e($row['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-grid"><a class="btn btn-soft" href="applications.php">Ver mis postulaciones</a></div>
                </div>
            </div>
        <?php elseif($u['role'] === 'company'): ?>
            <div class="col-xl-7">
                <div class="panel-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Estado de tus vacantes</h5>
                            <p class="section-subtitle mb-0">Consulta desempeño, estado y postulaciones por vacante.</p>
                        </div>
                        <a class="btn btn-soft btn-sm" href="company_jobs.php">Gestionar</a>
                    </div>
                    <?php if(!$recentRows): ?>
                        <div class="section-subtitle">Todavía no publicas vacantes.</div>
                    <?php endif; ?>
                    <?php foreach($recentRows as $row): ?>
                        <div class="showcase-card mb-3">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold"><?= e($row['title']) ?></div>
                                    <div class="small section-subtitle">Estado actual: <span class="text-capitalize"><?= e($row['status']) ?></span></div>
                                </div>
                                <div class="text-end">
                                    <div class="h5 fw-bold mb-0"><?= (int)$row['applications'] ?></div>
                                    <div class="small section-subtitle">postulaciones</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="panel-card h-100">
                    <h5 class="fw-bold mb-3">Acciones rápidas</h5>
                    <div class="d-grid gap-2">
                        <a class="btn btn-gradient" href="company_jobs.php">Publicar nueva vacante</a>
                        <a class="btn btn-soft" href="profile.php">Actualizar datos de empresa</a>
                        <a class="btn btn-soft" href="chat.php">Responder mensajes</a>
                        <a class="btn btn-soft" href="reviews.php">Revisar reseñas</a>
                    </div>
                </div>
            </div>
        <?php elseif($u['role'] === 'support'): ?>
            <div class="col-xl-7">
                <div class="panel-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Tickets recientes</h5>
                            <p class="section-subtitle mb-0">Conversaciones e incidencias que llegaron al canal de soporte.</p>
                        </div>
                        <a class="btn btn-soft btn-sm" href="support.php">Abrir mesa de ayuda</a>
                    </div>
                    <?php if(!$recentRows): ?><div class="section-subtitle">No hay tickets recientes.</div><?php endif; ?>
                    <?php foreach($recentRows as $row): ?>
                        <div class="showcase-card mb-3">
                            <div class="fw-semibold"><?= e($row['name'] ?? 'Usuario') ?></div>
                            <div class="small text-capitalize section-subtitle"><?= e(role_label($row['role'] ?? '')) ?></div>
                            <div class="small mt-2"><?= e($row['body']) ?></div>
                            <div class="d-flex justify-content-end mt-2 small">
                                <span><?= e($row['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="panel-card h-100">
                    <h5 class="fw-bold mb-3">Acciones rápidas</h5>
                    <div class="d-grid gap-2">
                        <a class="btn btn-gradient" href="chat.php">Responder tickets</a>
                        <a class="btn btn-soft" href="support.php">Ver mesa de ayuda</a>
                        <a class="btn btn-soft" href="ai.php?action=support_reply">Sugerir respuesta con IA</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-xl-7">
                <div class="panel-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Actividad reciente del sistema</h5>
                            <p class="section-subtitle mb-0">Últimos movimientos importantes dentro de OpenJobs.</p>
                        </div>
                        <a class="btn btn-soft btn-sm" href="admin.php">Ver panel completo</a>
                    </div>
                    <?php foreach($recentRows as $row): ?>
                        <div class="showcase-card mb-3">
                            <div class="fw-semibold"><?= e($row['name'] ?? 'Sistema') ?></div>
                            <div class="small"><?= e($row['action']) ?></div>
                            <div class="d-flex justify-content-between mt-2 small">
                                <span class="badge text-bg-light border text-capitalize"><?= e($row['type']) ?></span>
                                <span><?= e($row['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="panel-card h-100">
                    <h5 class="fw-bold mb-3">Acciones rápidas</h5>
                    <div class="d-grid gap-2">
                        <a class="btn btn-gradient" href="admin.php">Ir a control admin</a>
                        <a class="btn btn-soft" href="jobs.php">Revisar vacantes públicas</a>
                        <a class="btn btn-soft" href="chat.php">Entrar a mensajes</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>
</div>

<script>
const chart = document.getElementById('dashboardChart');
if (chart) {
    new Chart(chart, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Resumen',
                data: <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>,
                borderRadius: 12,
                backgroundColor: [
                    'rgba(37,99,235,.85)',
                    'rgba(124,58,237,.85)',
                    'rgba(6,182,212,.85)',
                    'rgba(16,185,129,.85)'
                ]
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>