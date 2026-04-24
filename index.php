<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';

$u = current_user();
$avatar = $u ? uploaded_url($u['avatar'] ?? null) : null;
$role = $u['role'] ?? '';
$roleLabel = $u ? role_label($role) : 'Usuario';
$quickLinks = [
    'talent' => [
        ['href' => 'applications.php', 'icon' => 'bi-send-check', 'label' => 'Mis postulaciones'],
        ['href' => 'reviews.php', 'icon' => 'bi-star', 'label' => 'Mis reseñas'],
    ],
    'company' => [
        ['href' => 'company_jobs.php', 'icon' => 'bi-briefcase', 'label' => 'Mis vacantes'],
        ['href' => 'profile.php', 'icon' => 'bi-building', 'label' => 'Perfil empresa'],
    ],
    'admin' => [
        ['href' => 'admin.php', 'icon' => 'bi-shield-check', 'label' => 'Panel admin'],
        ['href' => 'dashboard.php', 'icon' => 'bi-graph-up', 'label' => 'Resumen'],
    ],
    'support' => [
        ['href' => 'support.php', 'icon' => 'bi-life-preserver', 'label' => 'Mesa de ayuda'],
        ['href' => 'chat.php', 'icon' => 'bi-chat-dots', 'label' => 'Tickets por chat'],
    ],
][$role] ?? [];

$stats = [
    'reviews' => 240,
    'companies' => 72,
    'jobs' => 185,
    'users' => 920,
];
$featuredTitle = 'Lo mejor de OpenJobs';
$featuredSubtitle = 'Vacantes y señales destacadas para comenzar.';
$featuredItems = [];
$sideHighlights = [];

try {
    $pdo = db();
    $stats['reviews'] = (int)($pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn() ?: 0);
    $stats['companies'] = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE role='company'")->fetchColumn() ?: 0);
    $stats['jobs'] = (int)($pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn() ?: 0);
    $stats['users'] = (int)($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0);

    if ($u && $role === 'talent') {
        $featuredTitle = 'Tus mejores reseñas y vacantes destacadas';
        $featuredSubtitle = 'La portada te muestra primero lo que más te sirve para volver a aplicar o reforzar tu reputación.';
        $stmt = $pdo->prepare('SELECT r.rating, r.comment, r.created_at, c.name AS company_name
            FROM reviews r
            LEFT JOIN companies c ON c.id=r.company_id
            WHERE r.user_id=?
            ORDER BY r.rating DESC, r.ai_score DESC, r.created_at DESC LIMIT 3');
        $stmt->execute([$u['id']]);
        $featuredItems = $stmt->fetchAll() ?: [];

        $sideHighlights = $pdo->query('SELECT j.id, j.title, c.name company_name, j.modality, j.location, COUNT(a.id) applicants
            FROM jobs j
            LEFT JOIN companies c ON c.id=j.company_id
            LEFT JOIN applications a ON a.job_id=j.id
            WHERE j.status="active"
            GROUP BY j.id
            ORDER BY applicants DESC, j.salary_max DESC, j.id DESC LIMIT 4')->fetchAll() ?: [];
    } elseif ($u && $role === 'company') {
        $featuredTitle = 'Vacantes más atractivas para tu empresa';
        $featuredSubtitle = 'La portada prioriza vacantes con más interés para que identifiques qué publicar o reforzar.';
        $companyStmt = $pdo->prepare('SELECT id, name FROM companies WHERE user_id=? LIMIT 1');
        $companyStmt->execute([$u['id']]);
        $company = $companyStmt->fetch() ?: [];
        if (!empty($company['id'])) {
            $stmt = $pdo->prepare('SELECT j.title, j.status, j.modality, j.location, j.salary_max, COUNT(a.id) applicants
                FROM jobs j
                LEFT JOIN applications a ON a.job_id=j.id
                WHERE j.company_id=?
                GROUP BY j.id
                ORDER BY applicants DESC, j.salary_max DESC, j.id DESC LIMIT 4');
            $stmt->execute([(int)$company['id']]);
            $featuredItems = $stmt->fetchAll() ?: [];
        }
        $sideHighlights = $pdo->query('SELECT j.id, j.title, c.name company_name, COUNT(a.id) applicants
            FROM jobs j
            LEFT JOIN companies c ON c.id=j.company_id
            LEFT JOIN applications a ON a.job_id=j.id
            WHERE j.status="active"
            GROUP BY j.id
            ORDER BY applicants DESC, j.salary_max DESC, j.id DESC LIMIT 4')->fetchAll() ?: [];
    } elseif ($u && $role === 'admin') {
        $featuredTitle = 'Control general del sistema';
        $featuredSubtitle = 'La portada administrativa resume actividad, moderación y focos operativos de OpenJobs.';
        $featuredItems[] = [
            'title' => 'Usuarios registrados',
            'value' => (int)($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0),
            'meta' => 'Cuentas activas en la plataforma',
        ];
        $featuredItems[] = [
            'title' => 'Reseñas pendientes',
            'value' => (int)($pdo->query('SELECT COUNT(*) FROM reviews WHERE moderation_status <> "approved"')->fetchColumn() ?: 0),
            'meta' => 'Requieren revisión o validación',
        ];
        $featuredItems[] = [
            'title' => 'Empresas por verificar',
            'value' => (int)($pdo->query('SELECT COUNT(*) FROM companies WHERE verified=0')->fetchColumn() ?: 0),
            'meta' => 'Pendientes de aprobación',
        ];
        $sideHighlights = $pdo->query('SELECT a.action, a.created_at, u.name
            FROM activity_logs a
            LEFT JOIN users u ON u.id=a.user_id
            ORDER BY a.id DESC LIMIT 5')->fetchAll() ?: [];
    } elseif ($u && $role === 'support') {
        $featuredTitle = 'Mesa de ayuda y seguimiento';
        $featuredSubtitle = 'La portada del soporte muestra incidencias recientes y el estado de atención del sistema.';
        $supportId = (int)$u['id'];
        $stmt = $pdo->prepare('SELECT m.body, m.created_at, sender.name AS requester
            FROM messages m
            JOIN users sender ON sender.id=m.sender_id
            WHERE m.receiver_id=?
            ORDER BY m.id DESC LIMIT 4');
        $stmt->execute([$supportId]);
        $featuredItems = $stmt->fetchAll() ?: [];
        $sideHighlights[] = ['title' => 'Tickets recientes', 'value' => count($featuredItems), 'meta' => 'Conversaciones nuevas o reactivadas'];
        $sideHighlights[] = ['title' => 'Canal principal', 'value' => 'Chat interno', 'meta' => 'Atención desde OpenJobs'];
    }
} catch (Throwable $e) {
    // Mantener home funcional incluso si la BD aún no está importada.
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
    <title>OpenJobs · Transparencia laboral</title>
</head>
<body class="page-shell">
<div id="loader"><div class="spinner-border text-primary"></div></div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="appToast" class="toast border-0 shadow"><div class="toast-body text-white bg-success rounded-4">Acción realizada correctamente</div></div>
</div>

<nav class="navbar navbar-expand-lg sticky-top navbar-premium">
    <div class="container">
        <a class="navbar-brand brand-gradient" href="index.php">OpenJobs</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="jobs.php">Vacantes</a></li>
                <li class="nav-item"><a class="nav-link" href="#valor">Qué ofrece</a></li>
                <li class="nav-item"><a class="nav-link" href="#perfiles">Perfiles</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                <li class="nav-item"><a class="nav-link" href="ai_chat.php">Asistente AI</a></li>
                <li class="nav-item"><button onclick="toggleTheme()" class="btn btn-soft ms-lg-2" aria-label="Cambiar tema"><i class="bi bi-moon-stars"></i></button></li>
                <?php if($u): ?>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="user-chip dropdown-toggle text-decoration-none" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if($avatar): ?>
                                <img src="<?= e($avatar) ?>" alt="avatar" class="user-chip-avatar">
                            <?php else: ?>
                                <span class="user-chip-fallback"><i class="bi bi-person-fill"></i></span>
                            <?php endif; ?>
                            <span class="user-chip-text">
                                <small>Conectado como</small>
                                <strong><?= e($roleLabel) ?></strong>
                                <span><?= e($u['name'] ?? 'Usuario') ?></span>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2 user-menu">
                            <li class="dropdown-user-summary">
                                <?php if($avatar): ?>
                                    <img src="<?= e($avatar) ?>" alt="avatar" class="dropdown-user-avatar">
                                <?php else: ?>
                                    <span class="dropdown-user-avatar dropdown-user-avatar-fallback"><i class="bi bi-person-fill"></i></span>
                                <?php endif; ?>
                                <div>
                                    <strong><?= e($u['name'] ?? 'Usuario') ?></strong>
                                    <div class="small text-secondary">Rol activo: <?= e($roleLabel) ?></div>
                                    <div class="small text-secondary">Accesos rápidos del usuario</div>
                                </div>
                            </li>
                            <li><a class="dropdown-item rounded-3" href="dashboard.php"><i class="bi bi-grid me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item rounded-3" href="profile.php"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
                            <?php foreach($quickLinks as $link): ?>
                                <li><a class="dropdown-item rounded-3" href="<?= e($link['href']) ?>"><i class="bi <?= e($link['icon']) ?> me-2"></i><?= e($link['label']) ?></a></li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item rounded-3 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2"><a class="btn btn-soft" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-gradient" href="register.php">Crear cuenta</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-4 g-xl-5">
            <div class="col-lg-7 reveal">
                <span class="pill-stat mb-3"><i class="bi bi-shield-check"></i> Plataforma de transparencia laboral</span>
                <h1 class="hero-title mb-3">Decide dónde trabajar con información real.</h1>
                <p class="hero-text mb-4">OpenJobs es una plataforma gratuita donde trabajadores, ex trabajadores y estudiantes pueden consultar experiencias reales sobre empresas, compartir reseñas estructuradas y postularse a vacantes con más contexto antes de tomar una decisión.</p>

                <?php if($u): ?>
                    <div class="hero-card user-welcome-card mb-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div>
                                <div class="small text-secondary mb-1">Sesión activa</div>
                                <h3 class="h4 fw-bold mb-1">Hola, <?= e($u['name'] ?? 'Usuario') ?></h3>
                                <p class="mb-0 text-secondary">Entraste como <strong><?= e($roleLabel) ?></strong>. Continúa con tus acciones rápidas, revisa tu reputación o entra a tu dashboard.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-gradient" href="dashboard.php">Ir al dashboard</a>
                                <?php foreach($quickLinks as $link): ?>
                                    <a class="btn btn-soft" href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <form class="hero-card row g-2 align-items-center mb-3">
                        <div class="col-md-5"><input class="form-control" placeholder="Busca empresa, vacante o habilidad"></div>
                        <div class="col-md-4">
                            <select class="form-select">
                                <option>Todas las modalidades</option>
                                <option>Remoto</option>
                                <option>Presencial</option>
                                <option>Híbrido</option>
                            </select>
                        </div>
                        <div class="col-md-3"><a class="btn btn-gradient w-100" href="jobs.php">Explorar</a></div>
                    </form>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2">
                    <span class="pill-stat"><i class="bi bi-stars"></i> Reseñas y valoraciones reales</span>
                    <span class="pill-stat"><i class="bi bi-briefcase"></i> Vacantes vinculadas a empresas</span>
                    <span class="pill-stat"><i class="bi bi-trophy"></i> Reputación y gamificación</span>
                </div>
            </div>
            <div class="col-lg-5 reveal">
                <div class="showcase-card">
                    <div class="stat-float">
                        <div class="mini-stat"><div class="small text-secondary">Acceso</div><div class="fw-bold">Gratis</div></div>
                        <div class="mini-stat"><div class="small text-secondary">Enfoque</div><div class="fw-bold">Transparencia</div></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6"><div class="metric-card"><div class="metric-label">Reseñas</div><div class="counter-value reveal" data-target="<?= $stats['reviews'] ?>">0</div></div></div>
                        <div class="col-6"><div class="metric-card"><div class="metric-label">Empresas</div><div class="counter-value reveal" data-target="<?= $stats['companies'] ?>">0</div></div></div>
                        <div class="col-6"><div class="metric-card"><div class="metric-label">Vacantes</div><div class="counter-value reveal" data-target="<?= $stats['jobs'] ?>">0</div></div></div>
                        <div class="col-6"><div class="metric-card"><div class="metric-label">Comunidad</div><div class="counter-value reveal" data-target="<?= $stats['users'] ?>" data-suffix="+">0</div></div></div>
                        <div class="col-12">
                            <div class="premium-card">
                                <div class="kicker mb-2">Qué puedes hacer en OpenJobs</div>
                                <div class="d-grid gap-2 small">
                                    <div><i class="bi bi-check2-circle text-primary me-2"></i>Consultar salarios, ambiente, crecimiento y prácticas organizacionales</div>
                                    <div><i class="bi bi-check2-circle text-primary me-2"></i>Publicar reseñas y aportar a una comunidad confiable</div>
                                    <div><i class="bi bi-check2-circle text-primary me-2"></i>Postularte a vacantes con contexto de reputación laboral</div>
                                    <div><i class="bi bi-check2-circle text-primary me-2"></i>Usar IA, rankings y paneles por rol sin pagar suscripciones</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if($u): ?>
<section class="section-spacing pt-0">
    <div class="container">
        <div class="glass-card reveal">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <div class="kicker mb-2">Portada personalizada</div>
                    <h2 class="section-title mb-1"><?= e($featuredTitle) ?></h2>
                    <p class="section-lead mb-0"><?= e($featuredSubtitle) ?></p>
                </div>
                <a class="btn btn-gradient" href="dashboard.php">Abrir mi panel</a>
            </div>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="row g-3">
                        <?php if(!$featuredItems): ?>
                            <div class="col-12"><div class="showcase-card">Todavía no hay elementos destacados para tu rol. Completa tu actividad desde el dashboard.</div></div>
                        <?php elseif($role === 'talent'): ?>
                            <?php foreach($featuredItems as $item): ?>
                                <div class="col-md-6">
                                    <div class="showcase-card h-100">
                                        <div class="fw-semibold mb-1"><?= e($item['company_name'] ?? 'Empresa') ?></div>
                                        <div class="small section-subtitle mb-2"><?= e($item['created_at'] ?? '') ?></div>
                                        <div class="mb-2"><?= str_repeat('⭐', (int)($item['rating'] ?? 0)) ?></div>
                                        <div><?= e(mb_strimwidth($item['comment'] ?? '', 0, 180, '…', 'UTF-8')) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif($role === 'company'): ?>
                            <?php foreach($featuredItems as $item): ?>
                                <div class="col-md-6">
                                    <div class="showcase-card h-100">
                                        <div class="fw-semibold mb-1"><?= e($item['title'] ?? 'Vacante') ?></div>
                                        <div class="small section-subtitle mb-2"><?= e($item['location'] ?? 'Sin ubicación') ?> · <?= e($item['modality'] ?? 'Híbrido') ?></div>
                                        <div class="d-flex justify-content-between small">
                                            <span>Postulaciones: <strong><?= (int)($item['applicants'] ?? 0) ?></strong></span>
                                            <span class="text-capitalize"><?= e($item['status'] ?? 'active') ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif($role === 'admin'): ?>
                            <?php foreach($featuredItems as $item): ?>
                                <div class="col-md-4">
                                    <div class="metric-card h-100">
                                        <div class="metric-label"><?= e($item['title'] ?? '') ?></div>
                                        <div class="metric-value"><?= e((string)($item['value'] ?? '0')) ?></div>
                                        <div class="small section-subtitle mt-2"><?= e($item['meta'] ?? '') ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach($featuredItems as $item): ?>
                                <div class="col-md-6">
                                    <div class="showcase-card h-100">
                                        <div class="fw-semibold mb-1"><?= e($item['requester'] ?? 'Usuario') ?></div>
                                        <div class="small section-subtitle mb-2"><?= e($item['created_at'] ?? '') ?></div>
                                        <div><?= e(mb_strimwidth($item['body'] ?? '', 0, 180, '…', 'UTF-8')) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="premium-card h-100">
                        <h3 class="h5 fw-bold mb-3">Panel lateral</h3>
                        <?php if(!$sideHighlights): ?>
                            <div class="section-subtitle">Sin datos laterales disponibles por ahora.</div>
                        <?php else: ?>
                            <div class="d-grid gap-3">
                                <?php foreach($sideHighlights as $row): ?>
                                    <div class="mini-stat">
                                        <?php if($role === 'talent' || $role === 'company'): ?>
                                            <div class="fw-semibold"><?= e($row['title'] ?? '') ?></div>
                                            <div class="small section-subtitle"><?= e($row['company_name'] ?? '') ?></div>
                                            <div class="small mt-1"><?= (int)($row['applicants'] ?? 0) ?> postulaciones · <?= e($row['modality'] ?? '') ?> <?= !empty($row['location']) ? '· ' . e($row['location']) : '' ?></div>
                                        <?php elseif($role === 'admin'): ?>
                                            <div class="fw-semibold"><?= e($row['name'] ?? 'Actividad reciente') ?></div>
                                            <div class="small section-subtitle"><?= e($row['created_at'] ?? '') ?></div>
                                            <div class="small mt-1"><?= e($row['action'] ?? '') ?></div>
                                        <?php else: ?>
                                            <div class="fw-semibold"><?= e($row['title'] ?? '') ?></div>
                                            <div class="small section-subtitle"><?= e((string)($row['value'] ?? '')) ?></div>
                                            <div class="small mt-1"><?= e($row['meta'] ?? '') ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section-spacing-sm" id="valor">
    <div class="container">
        <div class="startup-strip reveal">
            <div class="startup-logo">RESEÑAS</div>
            <div class="startup-logo">EMPRESAS</div>
            <div class="startup-logo">VACANTES</div>
            <div class="startup-logo">IA</div>
            <div class="startup-logo">COMUNIDAD</div>
        </div>
    </div>
</section>

<section class="section-spacing">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <div class="kicker mb-2">Qué hace diferente a OpenJobs</div>
            <h2 class="section-title mb-2">Una plataforma para ver la realidad laboral antes de postularte.</h2>
            <p class="section-lead mx-auto">OpenJobs combina reputación empresarial, experiencias reales y vacantes en un solo lugar para que talento y empresas tomen mejores decisiones.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 reveal">
                <div class="premium-card h-100">
                    <div class="feature-icon mb-3"><i class="bi bi-chat-square-quote"></i></div>
                    <h3 class="h4 fw-bold">Reseñas estructuradas</h3>
                    <p class="section-subtitle">Comparte y consulta experiencias reales sobre horarios, salario, prestaciones, crecimiento y ambiente laboral.</p>
                    <a class="btn btn-soft" href="reviews.php">Ver reseñas</a>
                </div>
            </div>
            <div class="col-lg-4 reveal">
                <div class="premium-card h-100">
                    <div class="feature-icon mb-3"><i class="bi bi-briefcase"></i></div>
                    <h3 class="h4 fw-bold">Vacantes con contexto</h3>
                    <p class="section-subtitle">Cada vacante está vinculada al perfil de la empresa para mostrar reputación, valoraciones y métricas antes de aplicar.</p>
                    <a class="btn btn-soft" href="jobs.php">Explorar vacantes</a>
                </div>
            </div>
            <div class="col-lg-4 reveal">
                <div class="premium-card h-100">
                    <div class="feature-icon mb-3"><i class="bi bi-graph-up-arrow"></i></div>
                    <h3 class="h4 fw-bold">IA y reputación</h3>
                    <p class="section-subtitle">Recomendaciones de vacantes, análisis automático de CV, rankings y señales de confianza para fortalecer la comunidad.</p>
                    <a class="btn btn-gradient" href="ai.php">Abrir AI Studio</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-spacing pt-0">
    <div class="container">
        <div class="glass-card reveal">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="kicker mb-2">Valor para usuarios y empresas</div>
                    <h2 class="section-title mb-2">Transparencia laboral útil para quienes buscan empleo y para quienes quieren mejorar su reputación.</h2>
                    <p class="section-lead mb-0">Los usuarios toman decisiones más informadas; las empresas detectan áreas de oportunidad y destacan naturalmente cuando tienen buenas prácticas laborales.</p>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3">
                        <div class="col-6"><div class="mini-stat"><div class="small text-secondary">Usuarios</div><div class="fw-bold fs-5">Reseñas y CV</div></div></div>
                        <div class="col-6"><div class="mini-stat"><div class="small text-secondary">Empresas</div><div class="fw-bold fs-5">Métricas reales</div></div></div>
                        <div class="col-6"><div class="mini-stat"><div class="small text-secondary">Paneles</div><div class="fw-bold fs-5">Por rol</div></div></div>
                        <div class="col-6"><div class="mini-stat"><div class="small text-secondary">Costo</div><div class="fw-bold fs-5">Sin pagos</div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="testimonios" class="section-spacing pt-0">
    <div class="container">
        <div class="row align-items-end justify-content-between mb-4">
            <div class="col-lg-7 reveal">
                <div class="kicker mb-2">Comunidad OpenJobs</div>
                <h2 class="section-title mb-2">Una experiencia más cercana a cómo la gente realmente decide.</h2>
                <p class="section-lead mb-0">La plataforma busca que cada reseña, vacante y estadística aporte contexto real y útil dentro del proceso laboral.</p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 reveal">
                <div class="testimonial-card">
                    <p class="mb-0">“Antes de postularme pude revisar comentarios sobre ambiente, crecimiento y salario. Eso me ayudó a elegir mejor.”</p>
                    <div class="testimonial-user"><img src="https://placehold.co/80x80" alt="avatar"><div><div class="fw-semibold">Ana Torres</div><div class="small text-secondary">Estudiante de TI</div></div></div>
                </div>
            </div>
            <div class="col-lg-4 reveal">
                <div class="testimonial-card">
                    <p class="mb-0">“Como empresa, ver estadísticas de reputación y opiniones reales nos ayuda a entender cómo nos perciben los colaboradores.”</p>
                    <div class="testimonial-user"><img src="https://placehold.co/80x80" alt="avatar"><div><div class="fw-semibold">Eduardo Salas</div><div class="small text-secondary">Recruiter Lead</div></div></div>
                </div>
            </div>
            <div class="col-lg-4 reveal">
                <div class="testimonial-card">
                    <p class="mb-0">“La gamificación motiva a aportar información útil. Se siente como una comunidad que sí construye transparencia.”</p>
                    <div class="testimonial-user"><img src="https://placehold.co/80x80" alt="avatar"><div><div class="fw-semibold">María González</div><div class="small text-secondary">Ex colaboradora</div></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="perfiles" class="section-spacing pt-0">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <div class="kicker mb-2">OpenJobs para cada perfil</div>
            <h2 class="section-title mb-2">Una plataforma gratuita para usuarios, empresas y administración.</h2>
            <p class="section-lead mx-auto">No hay planes ni suscripciones. Cada perfil accede a herramientas enfocadas en transparencia laboral, reputación y seguimiento.</p>
        </div>
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-4 reveal">
                <div class="pricing-card h-100">
                    <div class="kicker mb-2">Usuarios</div>
                    <div class="price mb-2">Explora y comparte</div>
                    <p class="section-subtitle">Consulta reseñas, crea tu perfil, suma reputación y postúlate a vacantes con información real.</p>
                    <ul class="list-unstyled d-grid gap-2 mb-4">
                        <li><i class="bi bi-check2 text-success me-2"></i>Perfil profesional y CV</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Reseñas laborales</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Postulaciones y notificaciones</li>
                    </ul>
                    <a class="btn btn-soft w-100" href="register.php">Crear cuenta</a>
                </div>
            </div>
            <div class="col-lg-4 reveal">
                <div class="pricing-card featured h-100">
                    <div class="pill-stat mb-3"><i class="bi bi-building"></i> Perfil empresarial</div>
                    <div class="kicker mb-2">Empresas</div>
                    <div class="price mb-2">Gestiona tu reputación</div>
                    <p class="section-subtitle">Publica vacantes, analiza estadísticas derivadas de reseñas y da seguimiento a postulantes.</p>
                    <ul class="list-unstyled d-grid gap-2 mb-4">
                        <li><i class="bi bi-check2 text-success me-2"></i>Publicación de vacantes</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Estadísticas laborales</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Gestión de candidatos</li>
                    </ul>
                    <a class="btn btn-gradient w-100" href="register.php">Acceder como empresa</a>
                </div>
            </div>
            <div class="col-lg-4 reveal">
                <div class="pricing-card h-100">
                    <div class="kicker mb-2">Administración</div>
                    <div class="price mb-2">Control y calidad</div>
                    <p class="section-subtitle">Supervisa actividad, modera contenido y asegura la calidad de la información compartida.</p>
                    <ul class="list-unstyled d-grid gap-2 mb-4">
                        <li><i class="bi bi-check2 text-success me-2"></i>Gestión de usuarios</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Moderación de reseñas y vacantes</li>
                        <li><i class="bi bi-check2 text-success me-2"></i>Actividad del sistema</li>
                    </ul>
                    <a class="btn btn-soft w-100" href="dashboard.php">Ver panel</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="faq" class="section-spacing pt-0">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5 reveal">
                <div class="kicker mb-2">FAQ</div>
                <h2 class="section-title mb-2">Preguntas frecuentes sobre OpenJobs.</h2>
                <p class="section-lead">Una vista rápida de cómo funciona la plataforma, qué la hace diferente y cómo aprovecha la IA sin perder el enfoque en transparencia laboral.</p>
            </div>
            <div class="col-lg-7 reveal">
                <div class="accordion" id="faqAcc">
                    <div class="accordion-item faq-item">
                        <h2 class="accordion-header"><button class="accordion-button faq-button" type="button" data-bs-toggle="collapse" data-bs-target="#f1">¿OpenJobs es gratis?</button></h2>
                        <div id="f1" class="accordion-collapse collapse show" data-bs-parent="#faqAcc"><div class="accordion-body">Sí. La plataforma está pensada para usarse sin pagos, planes ni suscripciones tanto por usuarios como por empresas.</div></div>
                    </div>
                    <div class="accordion-item faq-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed faq-button" type="button" data-bs-toggle="collapse" data-bs-target="#f2">¿Qué hace diferente a OpenJobs?</button></h2>
                        <div id="f2" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">Integra reseñas laborales estructuradas, reputación empresarial, vacantes con contexto, gamificación y herramientas de IA en una sola plataforma.</div></div>
                    </div>
                    <div class="accordion-item faq-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed faq-button" type="button" data-bs-toggle="collapse" data-bs-target="#f3">¿La IA reemplaza la opinión humana?</button></h2>
                        <div id="f3" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">No. La IA ayuda con recomendaciones, análisis y moderación, pero el valor principal sigue siendo la experiencia real compartida por la comunidad.</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-spacing pt-0">
    <div class="container">
        <div class="cta-banner reveal">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="kicker text-white-50 mb-2">Call to action final</div>
                    <h2 class="fw-bold mb-2">Construyamos un entorno laboral más transparente.</h2>
                    <p class="mb-0 text-white-50">Tu experiencia puede ayudar a miles de personas a comparar empresas, detectar buenas prácticas y tomar mejores decisiones profesionales.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a class="btn btn-light btn-lg rounded-4 fw-semibold me-2" href="register.php">Crear cuenta</a>
                    <a class="btn btn-glass btn-lg rounded-4" href="jobs.php">Ver vacantes</a>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="premium-footer">
    <div class="container d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="brand-gradient fs-4">OpenJobs</div>
            <div class="footer-note">Plataforma gratuita de transparencia laboral con reseñas, reputación empresarial, vacantes y herramientas de IA.</div>
        </div>
        <div class="footer-note">Hecho con PHP, MySQL, Bootstrap, DeepSeek y una comunidad que comparte experiencias reales.</div>
    </div>
</footer>

<a class="btn btn-gradient rounded-circle mobile-fab" href="register.php" style="width:58px;height:58px;align-items:center;justify-content:center;"><i class="bi bi-rocket-takeoff"></i></a>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>