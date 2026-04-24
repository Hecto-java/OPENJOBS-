<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/services/RecommendationService.php';
$id=(int)($_GET['id'] ?? 1); 
$pdo=db();
$s=$pdo->prepare('SELECT j.*, c.name as company_name, c.description as company_description, c.location as company_location, c.verified, c.latitude, c.longitude, c.user_id as company_user_id FROM jobs j LEFT JOIN companies c ON c.id=j.company_id WHERE j.id=? LIMIT 1');
$s->execute([$id]); 
$job=$s->fetch(); 
if(!$job){ http_response_code(404); echo 'Vacante no encontrada'; exit; }
if(!empty($_POST['apply']) && !empty($_SESSION['user']) && ($_SESSION['user']['role']??'')==='talent'){
    $exists=$pdo->prepare('SELECT id FROM applications WHERE user_id=? AND job_id=? LIMIT 1'); 
    $exists->execute([$_SESSION['user']['id'],$id]);
    if(!$exists->fetch()){
        $pdo->prepare('INSERT INTO applications (job_id, user_id, status) VALUES (?, ?, ?)')->execute([$id, $_SESSION['user']['id'], 'enviada']);
        log_activity($pdo, (int)$_SESSION['user']['id'], 'Se postuló a la vacante ' . $job['title']);
        $owner = $pdo->prepare('SELECT user_id FROM companies WHERE id=? LIMIT 1');
        $owner->execute([(int)$job['company_id']]);
        $companyOwnerId = (int)($owner->fetch()['user_id'] ?? 0);
        if ($companyOwnerId > 0) {
            create_notification($pdo, $companyOwnerId, 'Nueva postulación', 'Recibiste una nueva postulación para ' . $job['title'] . '.', 'company_jobs.php', 'application');
        }
        flash('success','Postulación enviada correctamente');
    } else flash('success','Ya te habías postulado a esta vacante');
    redirect('/job.php?id=' . $id);
}
$success=flash('success');
$match=null; 
if(!empty($_SESSION['user']) && ($_SESSION['user']['role']??'')==='talent'){ 
    $map = RecommendationService::recommend((int)$_SESSION['user']['id']); 
    $match = $map[$job['id']] ?? ['score'=>78,'reason'=>'Coincide con tus habilidades principales y modalidad preferida.'];
}
$sim=$pdo->prepare('SELECT id,title,technology,location FROM jobs WHERE id<>? ORDER BY id DESC LIMIT 3'); 
$sim->execute([$id]); 
$similar=$sim->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="assets/css/styles.css"><title><?= e($job['title']) ?></title></head><body class="page-shell"><div id="loader"><div class="spinner-border text-primary"></div></div>
<nav class="navbar navbar-expand-lg sticky-top navbar-premium"><div class="container"><a class="navbar-brand brand-gradient" href="index.php">OpenJobs</a><div class="ms-auto d-flex gap-2"><a class="btn btn-soft" href="jobs.php">Volver</a><a class="btn btn-soft" href="index.php">Inicio</a><a class="btn btn-gradient" href="chat.php?to=<?= (int)($job['company_user_id'] ?? 0) ?>">Contactar</a></div></div></nav>
<div class="container py-4 py-lg-5"><?php if($success): ?><div class="alert alert-success rounded-4"><?= e($success) ?></div><?php endif; ?><div class="detail-hero mb-4 reveal"><div class="row g-4 align-items-end"><div class="col-lg-8"><span class="pill-stat bg-transparent border border-light-subtle text-white mb-3"><i class="bi bi-briefcase"></i> Vacante OpenJobs</span><h1 class="fw-bold mb-2"><?= e($job['title']) ?></h1><p class="mb-3 opacity-75"><?= e($job['company_name'] ?: 'Empresa') ?> · <?= e($job['location']) ?> · <?= e($job['modality']) ?></p><div class="d-flex flex-wrap gap-2 mb-3"><span class="benefit-chip bg-transparent text-white border-light-subtle"><i class="bi bi-cash-stack"></i>$<?= number_format((float)$job['salary_min']) ?> - $<?= number_format((float)$job['salary_max']) ?></span><span class="benefit-chip bg-transparent text-white border-light-subtle"><i class="bi bi-person-workspace"></i><?= e($job['employment_type']) ?></span><span class="benefit-chip bg-transparent text-white border-light-subtle"><i class="bi bi-cpu"></i><?= e($job['technology']) ?></span></div><div class="d-flex gap-2 flex-wrap"><?php if(!empty($_SESSION['user']) && ($_SESSION['user']['role']??'')==='talent'): ?><form method="post" class="d-inline"><button name="apply" value="1" class="btn btn-light rounded-4 fw-semibold">Aplicar ahora</button></form><?php else: ?><a href="login.php" class="btn btn-light rounded-4 fw-semibold">Inicia sesión para postularte</a><?php endif; ?><button class="btn btn-glass" data-toast="Vacante guardada">Guardar</button></div></div><div class="col-lg-4"><?php if($match): ?><div class="premium-card text-dark"><div class="d-flex align-items-center gap-3"><div class="match-circle" style="--value:<?= (int)$match['score'] ?>"><span><?= (int)$match['score'] ?>%</span></div><div><div class="kicker">Match IA</div><div class="fw-bold"><?= e($match['reason']) ?></div></div></div></div><?php endif; ?></div></div></div>
<div class="row g-4"><div class="col-lg-8"><div class="panel-card mb-4"><h3 class="fw-bold mb-3">Descripción</h3><p><?= nl2br(e($job['description'])) ?></p></div><div class="panel-card mb-4"><h3 class="fw-bold mb-3">Mapa y zona de trabajo</h3><div id="map" style="height:320px;border-radius:18px"></div></div><div class="panel-card"><h3 class="fw-bold mb-3">Vacantes similares</h3><div class="row g-3"><?php foreach($similar as $s): ?><div class="col-md-6"><div class="showcase-card"><div class="fw-semibold"><?= e($s['title']) ?></div><div class="small section-subtitle mb-2"><?= e($s['technology']) ?> · <?= e($s['location']) ?></div><a class="btn btn-soft btn-sm" href="job.php?id=<?= (int)$s['id'] ?>">Abrir</a></div></div><?php endforeach; ?></div></div></div><div class="col-lg-4"><div class="panel-card"><div class="kicker mb-2">Empresa</div><h4 class="fw-bold mb-1"><?= e($job['company_name'] ?: 'Empresa') ?></h4><div class="section-subtitle mb-3"><?= e($job['company_location'] ?: $job['location']) ?><?= !empty($job['verified']) ? ' · Verificada' : '' ?></div><p class="small mb-3"><?= e($job['company_description'] ?: 'Empresa activa dentro de OpenJobs.') ?></p><div class="d-grid gap-2"><a class="btn btn-gradient" href="chat.php?to=<?= (int)($job['company_user_id'] ?? 0) ?>">Enviar mensaje</a><a class="btn btn-soft" href="profile.php">Ver perfil empresa</a></div></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>const map=L.map('map').setView([<?= (float)($job['latitude'] ?? 25.6866) ?>,<?= (float)($job['longitude'] ?? -100.3161) ?>],13);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);L.marker([<?= (float)($job['latitude'] ?? 25.6866) ?>,<?= (float)($job['longitude'] ?? -100.3161) ?>]).addTo(map).bindPopup('Zona de trabajo');</script><script src="assets/js/app.js"></script></body></html>