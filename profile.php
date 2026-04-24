<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_auth();
$u = current_user();
$pdo = db();
$success = flash('success');
$error = '';

$checkUser = $pdo->prepare('SELECT id,name,email,role,avatar,points,level FROM users WHERE id=? LIMIT 1');
$checkUser->execute([(int)($u['id'] ?? 0)]);
$freshUser = $checkUser->fetch();
if (!$freshUser) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    flash('error', 'Tu sesión anterior ya no es válida. Inicia sesión nuevamente.');
    redirect('/login.php');
}
$u = array_merge($u, $freshUser);
$_SESSION['user'] = $u;

if ($u['role'] === 'talent') {
    $stmt = $pdo->prepare('SELECT * FROM talent_profiles WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $profile = $stmt->fetch();
    if (!$profile) {
        $pdo->prepare('INSERT INTO talent_profiles (user_id) VALUES (?)')->execute([$u['id']]);
        $stmt->execute([$u['id']]);
        $profile = $stmt->fetch();
    }

    if (is_post()) {
        $avatarPath = upload_file($_FILES['avatar'] ?? [], AVATAR_DIR, ['jpg','jpeg','png','webp']) ?: ($u['avatar'] ?? null);
        $cvPath = upload_file($_FILES['cv'] ?? [], CV_DIR, ['pdf']) ?: ($profile['cv_file'] ?? null);

        $pdo->prepare('UPDATE users SET name=?, avatar=? WHERE id=?')->execute([
            trim($_POST['name'] ?? $u['name']),
            $avatarPath,
            $u['id']
        ]);

        $pdo->prepare('UPDATE talent_profiles SET headline=?, bio=?, skills=?, experience_years=?, location=?, cv_file=? WHERE user_id=?')->execute([
            trim($_POST['headline'] ?? ''),
            trim($_POST['bio'] ?? ''),
            trim($_POST['skills'] ?? ''),
            (int)($_POST['experience_years'] ?? 0),
            trim($_POST['location'] ?? ''),
            $cvPath,
            $u['id']
        ]);

        if (!empty($_POST['exp_company']) && !empty($_POST['exp_position'])) {
            $pdo->prepare('INSERT INTO experience_work (user_id, company, position, start_date, end_date, description) VALUES (?,?,?,?,?,?)')->execute([
                $u['id'],
                trim($_POST['exp_company']),
                trim($_POST['exp_position']),
                $_POST['exp_start'] ?: null,
                $_POST['exp_end'] ?: null,
                trim($_POST['exp_description'] ?? '')
            ]);
        }

        $u = array_merge($u, ['name'=>trim($_POST['name'] ?? $u['name']), 'avatar'=>$avatarPath]);
        $_SESSION['user'] = $u;
        log_activity($pdo, (int)$u['id'], 'Actualizó perfil talento');
        flash('success','Perfil actualizado');
        redirect('/profile.php');
    }

    $exp = $pdo->prepare('SELECT * FROM experience_work WHERE user_id=? ORDER BY start_date DESC');
    $exp->execute([$u['id']]);
    $experiences = $exp->fetchAll();

    $completion = 0;
    foreach (['headline', 'bio', 'skills', 'location', 'cv_file'] as $field) {
        if (!empty($profile[$field])) $completion += 18;
    }
    if (count($experiences) > 0) $completion += 10;
    $completion = min(100, $completion);
} elseif ($u['role'] === 'company') {
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $company = $stmt->fetch();
    if (!$company) {
        $pdo->prepare('INSERT INTO companies (user_id, name) VALUES (?,?)')->execute([$u['id'], $u['name']]);
        $stmt->execute([$u['id']]);
        $company = $stmt->fetch();
    }

    if (is_post()) {
        $logo = upload_file($_FILES['logo'] ?? [], LOGO_DIR, ['jpg','jpeg','png','webp']) ?: ($company['logo'] ?? null);
        $pdo->prepare('UPDATE users SET name=?, avatar=? WHERE id=?')->execute([
            trim($_POST['owner_name'] ?? $u['name']),
            $logo,
            $u['id']
        ]);

        $pdo->prepare('UPDATE companies SET name=?, description=?, location=?, website=?, logo=?, latitude=?, longitude=? WHERE user_id=?')->execute([
            trim($_POST['company_name'] ?? ''),
            trim($_POST['description'] ?? ''),
            trim($_POST['location'] ?? ''),
            trim($_POST['website'] ?? ''),
            $logo,
            $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null,
            $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null,
            $u['id']
        ]);

        $u = array_merge($u, ['name'=>trim($_POST['owner_name'] ?? $u['name']), 'avatar'=>$logo]);
        $_SESSION['user']=$u;
        log_activity($pdo, (int)$u['id'], 'Actualizó perfil empresa');
        flash('success','Perfil empresa actualizado');
        redirect('/profile.php');
    }

    $stmt->execute([$u['id']]);
    $company = $stmt->fetch();
} else {
    if (is_post()) {
        $avatarPath = upload_file($_FILES['avatar'] ?? [], AVATAR_DIR, ['jpg','jpeg','png','webp']) ?: ($u['avatar'] ?? null);
        $newName = trim($_POST['name'] ?? $u['name']);
        $pdo->prepare('UPDATE users SET name=?, avatar=? WHERE id=?')->execute([$newName, $avatarPath, $u['id']]);
        $u = array_merge($u, ['name' => $newName, 'avatar' => $avatarPath]);
        $_SESSION['user'] = $u;
        log_activity($pdo, (int)$u['id'], 'Actualizó perfil interno');
        flash('success','Perfil actualizado');
        redirect('/profile.php');
    }
}
$avatarUrl = uploaded_url($u['avatar'] ?? null) ?: 'https://placehold.co/136x136';
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
<title>Perfil · OpenJobs</title>
</head>
<body class="page-shell">
<div id="loader"><div class="spinner-border text-primary"></div></div>
<nav class="navbar navbar-expand-lg sticky-top navbar-premium">
    <div class="container">
        <a class="navbar-brand brand-gradient" href="dashboard.php">OpenJobs</a>
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-soft" href="index.php">Inicio</a>
            <button onclick="toggleTheme()" class="btn btn-soft"><i class="bi bi-moon-stars"></i></button>
            <a class="btn btn-soft" href="dashboard.php">Dashboard</a>
        </div>
    </div>
</nav>

<div class="container py-4 py-lg-5">
    <?php if($success): ?><div class="alert alert-success rounded-4"><?= e($success) ?></div><?php endif; ?>

    <?php if($u['role']==='talent'): ?>
        <div class="profile-banner"></div>
        <div class="profile-card p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row align-items-lg-start gap-4">
                <img src="<?= e($avatarUrl) ?>" class="avatar-xl" alt="avatar">
                <div class="flex-grow-1">
                    <h1 class="fw-bold mb-1"><?= e($u['name']) ?></h1>
                    <p class="section-subtitle mb-2"><?= e($profile['headline'] ?? 'Talento OpenJobs') ?></p>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="badge text-bg-primary">Perfil <?= $completion ?>%</span>
                        <span class="badge text-bg-light border"><?= e($profile['location'] ?? 'Sin ubicación') ?></span>
                        <span class="badge text-bg-light border">XP <?= (int)($profile['xp'] ?? 0) ?></span>
                    </div>
                    <p class="small section-subtitle mb-0">Aquí puedes mantener tu perfil listo para que las empresas te encuentren, revisen tu CV y entiendan mejor tu experiencia.</p>
                </div>
            </div>

            <hr class="my-4">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="panel-card">
                        <h4 class="fw-bold mb-3">Editar perfil</h4>
                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nombre</label><input name="name" class="form-control" value="<?= e($u['name']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Titular profesional</label><input name="headline" class="form-control" value="<?= e($profile['headline'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Ubicación</label><input name="location" class="form-control" value="<?= e($profile['location'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Años de experiencia</label><input type="number" name="experience_years" class="form-control" value="<?= (int)($profile['experience_years'] ?? 0) ?>"></div>
                            <div class="col-12"><label class="form-label">Habilidades</label><input name="skills" class="form-control" value="<?= e($profile['skills'] ?? '') ?>" placeholder="PHP, JavaScript, UX, SQL"></div>
                            <div class="col-12"><label class="form-label">Acerca de ti</label><textarea name="bio" class="form-control" rows="4"><?= e($profile['bio'] ?? '') ?></textarea></div>
                            <div class="col-md-6"><label class="form-label">Foto de perfil</label><input type="file" name="avatar" class="form-control" accept="image/*"></div>
                            <div class="col-md-6"><label class="form-label">CV PDF</label><input type="file" name="cv" class="form-control" accept="application/pdf"></div>
                            <div class="col-12"><button class="btn btn-gradient">Guardar cambios</button></div>

                            <div class="col-12"><hr><h5 class="fw-bold">Agregar experiencia</h5></div>
                            <div class="col-md-6"><input name="exp_company" class="form-control" placeholder="Empresa"></div>
                            <div class="col-md-6"><input name="exp_position" class="form-control" placeholder="Puesto"></div>
                            <div class="col-md-6"><input type="date" name="exp_start" class="form-control"></div>
                            <div class="col-md-6"><input type="date" name="exp_end" class="form-control"></div>
                            <div class="col-12"><textarea name="exp_description" class="form-control" rows="3" placeholder="Descripción de actividades o logros"></textarea></div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="panel-card mb-4">
                        <h4 class="fw-bold mb-3">CV y visibilidad</h4>
                        <?php if(!empty($profile['cv_file'])): ?>
                            <a class="btn btn-soft w-100 mb-3" href="<?= e(uploaded_url($profile['cv_file'])) ?>" target="_blank">Ver CV actual</a>
                            <a class="btn btn-gradient w-100 mb-3" href="ai.php?action=analyze_cv">Analizar CV con IA</a>
                        <?php endif; ?>
                        <div class="progress-modern mb-2"><span style="width:<?= $completion ?>%"></span></div>
                        <div class="small section-subtitle">Mientras más completo esté tu perfil, más fácil será aparecer en búsquedas y recomendaciones.</div>
                    </div>

                    <div class="panel-card">
                        <h4 class="fw-bold mb-3">Experiencia registrada</h4>
                        <?php if(!$experiences): ?><div class="section-subtitle">Aún no has agregado experiencia laboral.</div><?php endif; ?>
                        <?php foreach($experiences as $row): ?>
                            <div class="timeline-item">
                                <div class="fw-semibold"><?= e($row['position']) ?></div>
                                <div class="section-subtitle"><?= e($row['company']) ?></div>
                                <div class="small"><?= e($row['description']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif($u['role']==='company'): ?>
        <div class="company-hero text-white p-4 p-lg-5 shadow-lg">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 position-relative">
                <div>
                    <h1 class="fw-bold mb-1"><?= e($company['name'] ?? $u['name']) ?></h1>
                    <p class="mb-0 opacity-75">Configura logo, descripción, ubicación y mapa para que tus vacantes inspiren confianza.</p>
                </div>
                <span class="badge text-bg-light"><?= !empty($company['verified']) ? 'Verificada' : 'Pendiente' ?></span>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-lg-7">
                <div class="panel-card">
                    <h4 class="fw-bold mb-3">Editar empresa</h4>
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-6"><label class="form-label">Responsable</label><input name="owner_name" class="form-control" value="<?= e($u['name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Nombre empresa</label><input name="company_name" class="form-control" value="<?= e($company['name'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Ubicación</label><input name="location" class="form-control" value="<?= e($company['location'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Sitio web</label><input name="website" class="form-control" value="<?= e($company['website'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">Descripción</label><textarea name="description" class="form-control" rows="4"><?= e($company['description'] ?? '') ?></textarea></div>
                        <div class="col-md-6"><label class="form-label">Logo o foto de perfil</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
                        <div class="col-md-3"><label class="form-label">Latitud</label><input name="latitude" id="latitude" class="form-control" value="<?= e((string)($company['latitude'] ?? '25.6866')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Longitud</label><input name="longitude" id="longitude" class="form-control" value="<?= e((string)($company['longitude'] ?? '-100.3161')) ?>"></div>
                        <div class="col-12"><button class="btn btn-gradient">Guardar empresa</button></div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="panel-card mb-4">
                    <h4 class="fw-bold mb-3">Vista de empresa</h4>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="<?= e(uploaded_url($company['logo'] ?? null) ?: $avatarUrl) ?>" class="avatar-xl" style="width:88px;height:88px" alt="logo">
                        <div>
                            <div class="fw-semibold"><?= e($company['name'] ?? $u['name']) ?></div>
                            <div class="small section-subtitle"><?= e($company['location'] ?? 'Sin ubicación') ?></div>
                        </div>
                    </div>
                    <div class="small section-subtitle">Tu información se mostrará junto con tus vacantes para que el talento entienda quién está contratando.</div>
                </div>
                <div class="panel-card" id="mapa">
                    <h4 class="fw-bold mb-3">Zona de trabajo</h4>
                    <div id="map" style="height:320px;border-radius:18px"></div>
                    <div class="small section-subtitle mt-2">Puedes ajustar latitud y longitud desde el formulario o dando clic en el mapa.</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="glass-card p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-4">
                <img src="<?= e($avatarUrl) ?>" class="avatar-xl" alt="avatar">
                <div class="flex-grow-1">
                    <span class="pill-stat mb-2"><i class="bi bi-person-badge"></i> Perfil interno</span>
                    <h1 class="fw-bold mb-1"><?= e($u['name']) ?></h1>
                    <p class="section-subtitle mb-0">Rol actual: <?= e(role_label($u['role'])) ?>. Este perfil permite mantener tu identidad dentro del sistema sin convertirte en empresa ni talento.</p>
                </div>
            </div>
            <hr class="my-4">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="panel-card">
                        <h4 class="fw-bold mb-3">Editar datos básicos</h4>
                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <div class="col-md-8"><label class="form-label">Nombre</label><input name="name" class="form-control" value="<?= e($u['name']) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Rol</label><input class="form-control" value="<?= e(role_label($u['role'])) ?>" disabled></div>
                            <div class="col-12"><label class="form-label">Avatar</label><input type="file" name="avatar" class="form-control" accept="image/*"></div>
                            <div class="col-12"><button class="btn btn-gradient">Guardar cambios</button></div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="panel-card">
                        <h4 class="fw-bold mb-3">Acciones rápidas</h4>
                        <div class="d-grid gap-2">
                            <?php if($u['role']==='admin'): ?>
                                <a class="btn btn-soft" href="admin.php">Abrir panel admin</a>
                            <?php else: ?>
                                <a class="btn btn-soft" href="support.php">Abrir mesa de ayuda</a>
                            <?php endif; ?>
                            <a class="btn btn-soft" href="dashboard.php">Volver al dashboard</a>
                            <a class="btn btn-soft" href="chat.php">Mensajes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/app.js"></script>
<?php if($u['role']!=='talent'): ?>
<script>
const latInput=document.getElementById('latitude');
const lngInput=document.getElementById('longitude');
const map=L.map('map').setView([parseFloat(latInput.value||25.6866),parseFloat(lngInput.value||-100.3161)],13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);
let marker=L.marker([parseFloat(latInput.value||25.6866),parseFloat(lngInput.value||-100.3161)]).addTo(map);
map.on('click',e=>{
    const {lat,lng}=e.latlng;
    latInput.value=lat.toFixed(6);
    lngInput.value=lng.toFixed(6);
    marker.setLatLng(e.latlng);
});
</script>
<?php endif; ?>
</body>
</html>