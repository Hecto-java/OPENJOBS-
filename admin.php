<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_auth();
if((current_user()['role']??'')!=='admin') redirect('/dashboard.php');

$pdo=db();
$success=flash('success');
$error='';

if(is_post()){
    try{
        $action=$_POST['action'] ?? '';
        if($action === 'toggle_company_verification'){
            $companyId=(int)($_POST['company_id'] ?? 0);
            $verified=(int)($_POST['verified'] ?? 0);
            $pdo->prepare('UPDATE companies SET verified=? WHERE id=?')->execute([$verified ? 1 : 0, $companyId]);
            log_activity($pdo, (int)current_user()['id'], ($verified ? 'Verificó' : 'Marcó como no verificada') . ' una empresa', 'admin');
            flash('success','Estado de verificación actualizado');
            redirect('/admin.php');
        }
        if($action === 'toggle_job_status'){
            $jobId=(int)($_POST['job_id'] ?? 0);
            $status=$_POST['job_status'] ?? 'active';
            if(!in_array($status, ['active','paused','closed'], true)){ $status='active'; }
            $pdo->prepare('UPDATE jobs SET status=? WHERE id=?')->execute([$status, $jobId]);
            log_activity($pdo, (int)current_user()['id'], 'Cambió el estado de una vacante', 'admin');
            flash('success','Vacante actualizada');
            redirect('/admin.php');
        }
    }catch(Throwable $e){
        $error = $e->getMessage();
    }
}

$stats=[
    'users'=>(int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
    'companies'=>(int)$pdo->query('SELECT COUNT(*) c FROM companies')->fetch()['c'],
    'jobs'=>(int)$pdo->query('SELECT COUNT(*) c FROM jobs')->fetch()['c'],
    'applications'=>(int)$pdo->query('SELECT COUNT(*) c FROM applications')->fetch()['c'],
    'reviews'=>(int)$pdo->query('SELECT COUNT(*) c FROM reviews')->fetch()['c'],
    'messages'=>(int)$pdo->query('SELECT COUNT(*) c FROM messages')->fetch()['c']
];
$recentUsers=$pdo->query('SELECT name,email,role,created_at FROM users ORDER BY id DESC LIMIT 8')->fetchAll();
$recentActivity=$pdo->query('SELECT a.*, u.name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 12')->fetchAll();
$companies=$pdo->query('SELECT c.id, c.name, c.location, c.verified, u.email
    FROM companies c
    JOIN users u ON u.id=c.user_id
    ORDER BY c.id DESC LIMIT 8')->fetchAll();
$jobs=$pdo->query('SELECT j.id, j.title, j.status, c.name company_name, COUNT(a.id) applicants
    FROM jobs j
    LEFT JOIN companies c ON c.id=j.company_id
    LEFT JOIN applications a ON a.job_id=j.id
    GROUP BY j.id
    ORDER BY j.id DESC LIMIT 8')->fetchAll();
$supportInbox=$pdo->query("SELECT m.body,m.created_at,u.name FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.receiver_id=(SELECT id FROM users WHERE email='soporte@openjobs.local' LIMIT 1) ORDER BY m.id DESC LIMIT 6")->fetchAll();
$pendingReviews=(int)$pdo->query('SELECT COUNT(*) c FROM reviews WHERE moderation_status <> "approved"')->fetch()['c'];
$companyDirectory=$pdo->query('SELECT c.id, c.name, c.location, c.verified, u.name AS owner_name, u.email
    FROM companies c
    JOIN users u ON u.id=c.user_id
    ORDER BY c.id DESC LIMIT 12')->fetchAll();
$userDirectory=$pdo->query('SELECT u.id, u.name, u.email, u.role, u.created_at
    FROM users u
    ORDER BY u.id DESC LIMIT 12')->fetchAll();

?><!doctype html>
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
<title>Admin · OpenJobs</title>
</head>
<body class="page-shell">
<div class="container-fluid">
<div class="row">
<aside class="col-lg-2 sidebar-premium">
    <div class="brand-gradient fs-4 mb-4">OpenJobs</div>
    <nav class="nav flex-column">
        <a class="nav-link" href="index.php"><i class="bi bi-house-door"></i>Inicio</a>
        <a class="nav-link" href="dashboard.php"><i class="bi bi-grid"></i>Dashboard</a>
        <a class="nav-link active" href="admin.php"><i class="bi bi-shield-check"></i>Control admin</a>
        <a class="nav-link" href="jobs.php"><i class="bi bi-briefcase"></i>Vacantes</a>
        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i>Salir</a>
    </nav>
</aside>

<main class="col-lg-10 p-4 p-md-5">
    <?php if($success): ?><div class="alert alert-success rounded-4"><?= e($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger rounded-4"><?= e($error) ?></div><?php endif; ?>

    <div class="glass-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="pill-stat mb-2"><i class="bi bi-shield-check"></i> Administración total</span>
                <h1 class="section-title mb-1">Panel de control OpenJobs</h1>
                <p class="section-subtitle mb-0">Supervisa la actividad del sistema, gestiona usuarios y empresas, revisa soporte y mantiene la calidad de la información publicada en OpenJobs.</p>
            </div>
            <div class="panel-quicklinks">
                <a class="btn btn-soft" href="dashboard.php">Volver al dashboard</a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Soporte técnico</h5>
                    <a class="btn btn-soft btn-sm" href="support.php">Abrir soporte</a>
                </div>
                <div class="showcase-card mb-3">
                    <div class="small section-subtitle">Perfil asignado</div>
                    <div class="fw-semibold">Soporte OpenJobs</div>
                    <div class="small mt-2 section-subtitle">Canal interno para incidencias técnicas y seguimiento operativo.</div>
                </div>
                <?php if(!$supportInbox): ?><div class="section-subtitle">No hay reportes recientes.</div><?php endif; ?>
                <?php foreach($supportInbox as $ticket): ?>
                    <div class="showcase-card mb-3">
                        <div class="fw-semibold"><?= e($ticket['name']) ?></div>
                        <div class="small section-subtitle"><?= e($ticket['created_at']) ?></div>
                        <div class="small mt-2"><?= e($ticket['body']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h5 class="fw-bold mb-3">Moderación y alertas</h5>
                <div class="showcase-card mb-3">
                    <div class="small section-subtitle">Reseñas pendientes de moderación</div>
                    <div class="h3 fw-bold mb-0"><?= $pendingReviews ?></div>
                </div>
                <div class="showcase-card">
                    <div class="small section-subtitle">Rol del administrador</div>
                    <div class="fw-semibold">Controla todo OpenJobs, no opera como empresa ni publica vacantes propias.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <?php foreach(['users'=>'Usuarios','companies'=>'Empresas','jobs'=>'Vacantes','applications'=>'Postulaciones','reviews'=>'Reseñas','messages'=>'Mensajes'] as $k=>$label): ?>
            <div class="col-md-4 col-xl-2">
                <div class="metric-card">
                    <div class="metric-label"><?= $label ?></div>
                    <div class="metric-value"><?= $stats[$k] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel-card mb-4 admin-directory-entry">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Directorio separado</h5>
                <p class="section-subtitle mb-0">Las listas de usuarios y empresas están fuera del dashboard principal para que el panel admin quede más limpio.</p>
            </div>
            <div class="panel-quicklinks">
                <a class="btn btn-soft" href="#usuarios"><i class="bi bi-people"></i>Ir a usuarios</a>
                <a class="btn btn-soft" href="#empresas"><i class="bi bi-buildings"></i>Ir a empresas</a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h5 class="fw-bold mb-3">Resumen general</h5>
                <canvas id="chart" height="180"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h5 class="fw-bold mb-3">Usuarios recientes</h5>
                <div class="table-responsive">
                    <table class="table table-premium align-middle">
                        <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th></tr></thead>
                        <tbody>
                        <?php foreach($recentUsers as $r): ?>
                            <tr>
                                <td><?= e($r['name']) ?></td>
                                <td><?= e($r['email']) ?></td>
                                <td><span class="badge text-bg-light border text-capitalize"><?= e($r['role']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Empresas y verificación</h5>
                    <span class="pill-stat"><i class="bi bi-patch-check"></i> Moderación</span>
                </div>
                <?php foreach($companies as $company): ?>
                    <div class="showcase-card mb-3">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-semibold"><?= e($company['name']) ?></div>
                                <div class="small section-subtitle"><?= e($company['location'] ?: 'Sin ubicación') ?></div>
                                <div class="small"><?= e($company['email']) ?></div>
                            </div>
                            <form method="post" class="text-end">
                                <input type="hidden" name="action" value="toggle_company_verification">
                                <input type="hidden" name="company_id" value="<?= (int)$company['id'] ?>">
                                <input type="hidden" name="verified" value="<?= $company['verified'] ? 0 : 1 ?>">
                                <div class="mb-2">
                                    <span class="badge <?= $company['verified'] ? 'text-bg-success' : 'text-bg-light border' ?>">
                                        <?= $company['verified'] ? 'Verificada' : 'Pendiente' ?>
                                    </span>
                                </div>
                                <button class="btn btn-soft btn-sm"><?= $company['verified'] ? 'Quitar verificación' : 'Verificar' ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Vacantes recientes</h5>
                    <span class="pill-stat"><i class="bi bi-sliders"></i> Estados</span>
                </div>
                <?php foreach($jobs as $job): ?>
                    <div class="showcase-card mb-3">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-semibold"><?= e($job['title']) ?></div>
                                <div class="small section-subtitle"><?= e($job['company_name'] ?: 'Empresa') ?> · <?= (int)$job['applicants'] ?> postulaciones</div>
                            </div>
                            <form method="post" class="text-end">
                                <input type="hidden" name="action" value="toggle_job_status">
                                <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                                <select name="job_status" class="form-select form-select-sm mb-2">
                                    <option value="active" <?= $job['status']==='active'?'selected':'' ?>>Activa</option>
                                    <option value="paused" <?= $job['status']==='paused'?'selected':'' ?>>Pausada</option>
                                    <option value="closed" <?= $job['status']==='closed'?'selected':'' ?>>Cerrada</option>
                                </select>
                                <button class="btn btn-soft btn-sm">Guardar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6" id="usuarios">
            <div class="panel-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0">Ver usuarios</h5>
                    <span class="pill-stat"><i class="bi bi-people"></i> Directorio</span>
                </div>
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
            <div class="panel-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0">Ver empresas</h5>
                    <span class="pill-stat"><i class="bi bi-buildings"></i> Directorio</span>
                </div>
                <?php foreach($companyDirectory as $companyRow): ?>
                    <div class="entity-list-card">
                        <div class="fw-semibold"><?= e($companyRow['name']) ?></div>
                        <div class="small section-subtitle"><?= e($companyRow['location'] ?: 'Sin ubicación registrada') ?></div>
                        <div class="small"><?= e($companyRow['email']) ?></div>
                        <div class="entity-list-meta">
                            <span class="entity-tag"><i class="bi bi-person"></i><?= e($companyRow['owner_name']) ?></span>
                            <span class="entity-tag"><i class="bi bi-patch-check"></i><?= $companyRow['verified'] ? 'Verificada' : 'Pendiente' ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-12">
            <div class="panel-card">
                <h5 class="fw-bold mb-3">Actividad reciente</h5>
                <div class="table-responsive">
                    <table class="table table-premium align-middle">
                        <thead><tr><th>Usuario</th><th>Acción</th><th>Tipo</th><th>Fecha</th></tr></thead>
                        <tbody>
                        <?php foreach($recentActivity as $a): ?>
                            <tr>
                                <td><?= e($a['name'] ?? 'Sistema') ?></td>
                                <td><?= e($a['action']) ?></td>
                                <td><span class="badge text-bg-light border text-capitalize"><?= e($a['type']) ?></span></td>
                                <td><?= e($a['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
</div>
</div>
<script>
new Chart(document.getElementById('chart'),{
    type:'bar',
    data:{
        labels:['Usuarios','Empresas','Vacantes','Postulaciones','Reseñas','Mensajes'],
        datasets:[{
            label:'Totales',
            data:[<?= $stats['users'] ?>,<?= $stats['companies'] ?>,<?= $stats['jobs'] ?>,<?= $stats['applications'] ?>,<?= $stats['reviews'] ?>,<?= $stats['messages'] ?>],
            borderRadius:12,
            backgroundColor:[
                'rgba(37,99,235,.85)',
                'rgba(124,58,237,.85)',
                'rgba(6,182,212,.85)',
                'rgba(16,185,129,.85)',
                'rgba(249,115,22,.85)',
                'rgba(236,72,153,.85)'
            ]
        }]
    },
    options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>