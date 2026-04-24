<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_auth();
$u=current_user();
if(($u['role']??'')!=='company') redirect('/dashboard.php');
$pdo=db();
$stmt=$pdo->prepare('SELECT * FROM companies WHERE user_id=? LIMIT 1');
$stmt->execute([$u['id']]);
$company=$stmt->fetch();
if(!$company){
    $pdo->prepare('INSERT INTO companies (user_id, name, location, latitude, longitude) VALUES (?,?,?,?,?)')->execute([$u['id'], $u['name'], 'Monterrey, NL', 25.6866, -100.3161]);
    $stmt->execute([$u['id']]);
    $company=$stmt->fetch();
}
$companyId=(int)$company['id'];
$error=''; $success=flash('success');

if(is_post()){
    $action = $_POST['action'] ?? 'create_job';
    try{
        if($action === 'create_job'){
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if($title === '' || $description === ''){
                throw new RuntimeException('El título y la descripción son obligatorios.');
            }
            $pdo->prepare('INSERT INTO jobs (company_id,title,description,technology,modality,employment_type,experience_required,location,salary_min,salary_max,status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([
                $companyId,
                $title,
                $description,
                trim($_POST['technology'] ?? ''),
                in_array($_POST['modality'] ?? '', ['Remoto','Presencial','Híbrido'], true) ? $_POST['modality'] : 'Híbrido',
                trim($_POST['employment_type'] ?? 'Tiempo completo'),
                trim($_POST['experience_required'] ?? 'Mid'),
                trim($_POST['location'] ?? ($company['location'] ?? '')),
                (float)($_POST['salary_min'] ?? 0),
                (float)($_POST['salary_max'] ?? 0),
                'active'
            ]);
            log_activity($pdo, (int)$u['id'], 'Publicó una nueva vacante');
            flash('success','Vacante publicada correctamente');
            redirect('/company_jobs.php');
        }

        if($action === 'update_job_status'){
            $jobId = (int)($_POST['job_id'] ?? 0);
            $status = $_POST['job_status'] ?? 'active';
            if(!in_array($status, ['active','paused','closed'], true)){
                $status = 'active';
            }
            $pdo->prepare('UPDATE jobs SET status=? WHERE id=? AND company_id=?')->execute([$status, $jobId, $companyId]);
            log_activity($pdo, (int)$u['id'], 'Actualizó el estado de una vacante');
            flash('success','Estado de vacante actualizado');
            redirect('/company_jobs.php');
        }

        if($action === 'delete_job'){
            $jobId = (int)($_POST['job_id'] ?? 0);
            $pdo->prepare('DELETE FROM jobs WHERE id=? AND company_id=?')->execute([$jobId, $companyId]);
            log_activity($pdo, (int)$u['id'], 'Eliminó una vacante');
            flash('success','Vacante eliminada');
            redirect('/company_jobs.php');
        }

        if($action === 'update_application'){
            $applicationId = (int)($_POST['application_id'] ?? 0);
            $status = $_POST['application_status'] ?? 'revision';
            if(!in_array($status, ['enviada','revision','aceptada','rechazada'], true)){
                $status = 'revision';
            }
            $target = $pdo->prepare('SELECT a.user_id, j.title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? LIMIT 1');
            $target->execute([$applicationId]);
            $appInfo = $target->fetch() ?: [];
            $update = $pdo->prepare('UPDATE applications a
                JOIN jobs j ON j.id=a.job_id
                SET a.status=?
                WHERE a.id=? AND j.company_id=?');
            $update->execute([$status, $applicationId, $companyId]);
            if (!empty($appInfo['user_id'])) {
                create_notification($pdo, (int)$appInfo['user_id'], 'Estado de postulación', 'Tu postulación a ' . ($appInfo['title'] ?? 'una vacante') . ' cambió a ' . $status . '.', 'applications.php', 'application');
            }
            log_activity($pdo, (int)$u['id'], 'Actualizó el estado de una postulación');
            flash('success','Postulación actualizada');
            redirect('/company_jobs.php');
        }
    }catch(Throwable $e){
        $error = $e->getMessage();
    }
}

$jobsStmt=$pdo->prepare('SELECT j.*,
    (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id) AS applicants
    FROM jobs j
    WHERE j.company_id=?
    ORDER BY j.id DESC');
$jobsStmt->execute([$companyId]);
$jobs=$jobsStmt->fetchAll();

$applicantsStmt=$pdo->prepare('SELECT a.id, a.status, a.created_at, u.id AS user_id, u.name, u.email, tp.headline, tp.skills, tp.cv_file, j.title
    FROM applications a
    JOIN jobs j ON j.id=a.job_id
    JOIN users u ON u.id=a.user_id
    LEFT JOIN talent_profiles tp ON tp.user_id=u.id
    WHERE j.company_id=?
    ORDER BY a.created_at DESC');
$applicantsStmt->execute([$companyId]);
$applicants=$applicantsStmt->fetchAll();

$stats = [
    'jobs' => count($jobs),
    'active' => count(array_filter($jobs, fn($job) => $job['status'] === 'active')),
    'applications' => count($applicants),
    'accepted' => count(array_filter($applicants, fn($row) => $row['status'] === 'aceptada')),
];
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
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="assets/css/styles.css">
<title>Vacantes empresa · OpenJobs</title>
</head>
<body class="page-shell">
<div class="container py-4 py-lg-5">
    <?php if($success): ?><div class="alert alert-success rounded-4"><?= e($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger rounded-4"><?= e($error) ?></div><?php endif; ?>

    <div class="glass-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="pill-stat mb-2"><i class="bi bi-buildings"></i> Empresa en OpenJobs</span>
                <h1 class="section-title mb-1">Sistema completo de vacantes</h1>
                <p class="section-subtitle mb-0">Publica oportunidades, administra estados y revisa candidatos desde un flujo simple y gratuito.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-soft" href="dashboard.php">Dashboard</a>
                <a class="btn btn-gradient" href="profile.php">Perfil empresa</a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Vacantes</div><div class="metric-value"><?= $stats['jobs'] ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Activas</div><div class="metric-value"><?= $stats['active'] ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Postulaciones</div><div class="metric-value"><?= $stats['applications'] ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Aceptadas</div><div class="metric-value"><?= $stats['accepted'] ?></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h4 class="fw-bold mb-3">Nueva vacante</h4>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="create_job">
                    <div class="col-md-6"><input name="title" class="form-control" placeholder="Título de la vacante" required></div>
                    <div class="col-md-6"><input name="technology" class="form-control" placeholder="Tecnologías o herramientas"></div>
                    <div class="col-md-6">
                        <select name="modality" class="form-select">
                            <option>Remoto</option>
                            <option>Presencial</option>
                            <option>Híbrido</option>
                        </select>
                    </div>
                    <div class="col-md-6"><input name="employment_type" class="form-control" value="Tiempo completo"></div>
                    <div class="col-md-6"><input name="location" class="form-control" value="<?= e($company['location'] ?? '') ?>" placeholder="Ubicación"></div>
                    <div class="col-md-6"><input name="experience_required" class="form-control" placeholder="Junior / Mid / Senior"></div>
                    <div class="col-md-6"><input type="number" step="0.01" name="salary_min" class="form-control" placeholder="Salario mínimo"></div>
                    <div class="col-md-6"><input type="number" step="0.01" name="salary_max" class="form-control" placeholder="Salario máximo"></div>
                    <div class="col-12"><textarea name="description" class="form-control" rows="4" placeholder="Describe responsabilidades, requisitos y beneficios" required></textarea></div>
                    <div class="col-12">
                        <button class="btn btn-gradient">Publicar vacante</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h4 class="fw-bold mb-3">Zona de trabajo</h4>
                <div id="map" style="height:320px;border-radius:18px"></div>
                <div class="small section-subtitle mt-3">La ubicación de la empresa se toma desde tu perfil para mostrar mejor la zona de trabajo.</div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Mis vacantes</h4>
                    <span class="pill-stat"><i class="bi bi-briefcase"></i> Gestión completa</span>
                </div>
                <?php if(!$jobs): ?><div class="section-subtitle">Aún no has publicado vacantes.</div><?php endif; ?>
                <?php foreach($jobs as $j): ?>
                    <div class="showcase-card mb-3">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div>
                                <div class="fw-semibold"><?= e($j['title']) ?></div>
                                <div class="small section-subtitle"><?= e($j['location']) ?> · <?= e($j['modality']) ?> · <?= e($j['employment_type']) ?></div>
                                <div class="small mt-2">$<?= number_format((float)$j['salary_min']) ?> - $<?= number_format((float)$j['salary_max']) ?> · <?= (int)$j['applicants'] ?> postulaciones</div>
                            </div>
                            <div class="text-end">
                                <span class="badge text-bg-light border text-capitalize mb-2"><?= e($j['status']) ?></span>
                                <form method="post" class="d-grid gap-2 mb-2">
                                    <input type="hidden" name="action" value="update_job_status">
                                    <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                                    <select name="job_status" class="form-select form-select-sm">
                                        <option value="active" <?= $j['status']==='active'?'selected':'' ?>>Activa</option>
                                        <option value="paused" <?= $j['status']==='paused'?'selected':'' ?>>Pausada</option>
                                        <option value="closed" <?= $j['status']==='closed'?'selected':'' ?>>Cerrada</option>
                                    </select>
                                    <button class="btn btn-soft btn-sm">Guardar estado</button>
                                </form>
                                <form method="post" onsubmit="return confirm('¿Eliminar esta vacante?');">
                                    <input type="hidden" name="action" value="delete_job">
                                    <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Postulantes recientes</h4>
                    <a class="btn btn-soft btn-sm" href="chat.php">Mensajes</a>
                </div>
                <?php if(!$applicants): ?><div class="section-subtitle">Todavía no hay postulaciones.</div><?php endif; ?>
                <?php foreach($applicants as $a): ?>
                    <div class="showcase-card mb-3">
                        <div class="fw-semibold"><?= e($a['name']) ?></div>
                        <div class="small section-subtitle"><?= e($a['title']) ?> · <?= e($a['headline'] ?? 'Sin titular profesional') ?></div>
                        <div class="small mb-2"><?= e($a['email']) ?></div>
                        <?php if(!empty($a['skills'])): ?><div class="small mb-2">Skills: <?= e($a['skills']) ?></div><?php endif; ?>
                        <form method="post" class="row g-2 align-items-center">
                            <input type="hidden" name="action" value="update_application">
                            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                            <div class="col-7">
                                <select name="application_status" class="form-select form-select-sm">
                                    <option value="enviada" <?= $a['status']==='enviada'?'selected':'' ?>>Enviada</option>
                                    <option value="revision" <?= $a['status']==='revision'?'selected':'' ?>>En revisión</option>
                                    <option value="aceptada" <?= $a['status']==='aceptada'?'selected':'' ?>>Aceptada</option>
                                    <option value="rechazada" <?= $a['status']==='rechazada'?'selected':'' ?>>Rechazada</option>
                                </select>
                            </div>
                            <div class="col-5"><button class="btn btn-soft btn-sm w-100">Actualizar</button></div>
                        </form>
                        <div class="d-flex gap-2 flex-wrap mt-2"><?php if(!empty($a['cv_file'])): ?><a class="btn btn-gradient btn-sm" target="_blank" href="<?= e(uploaded_url($a['cv_file'])) ?>">Ver CV</a><?php endif; ?><a class="btn btn-soft btn-sm" href="chat.php?to=<?= (int)$a['user_id'] ?>">Enviar mensaje</a></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map=L.map('map').setView([<?= (float)($company['latitude'] ?? 25.6866) ?>,<?= (float)($company['longitude'] ?? -100.3161) ?>],13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);
L.marker([<?= (float)($company['latitude'] ?? 25.6866) ?>,<?= (float)($company['longitude'] ?? -100.3161) ?>]).addTo(map).bindPopup('Ubicación de la empresa');
</script>
<script src="assets/js/app.js"></script>
</body>
</html>