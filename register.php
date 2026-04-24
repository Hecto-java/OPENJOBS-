<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers/helpers.php';
if (current_user()) redirect('dashboard.php');

$error = '';
if (is_post()) {
    require_once __DIR__ . '/config/database.php';
    $role = $_POST['role'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (!in_array($role, ['talent', 'company'], true)) {
        $error = 'Selecciona un rol válido.';
    } elseif ($name === '' || $email === '' || $password === '') {
        $error = 'Completa los campos obligatorios.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $exists = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $error = 'Ese correo ya está registrado.';
        } else {
            try {
                db()->beginTransaction();
                $stmt = db()->prepare('INSERT INTO users (name,email,password,role,created_at) VALUES (:n,:e,:p,:r,NOW())');
                $stmt->execute([
                    'n' => $name,
                    'e' => $email,
                    'p' => password_hash($password, PASSWORD_DEFAULT),
                    'r' => $role,
                ]);
                $id = (int)db()->lastInsertId();
                if ($role === 'talent') {
                    db()->prepare('INSERT INTO talent_profiles (user_id,headline,bio,skills,experience_years,location,xp) VALUES (?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$id, 'Nuevo talento', '', trim($_POST['skills'] ?? ''), 0, $location, 100]);
                } else {
                    db()->prepare('INSERT INTO companies (user_id,name,description,location,verified) VALUES (?, ?, ?, ?, 0)')
                        ->execute([$id, trim($_POST['company_name'] ?? $name), '', $location]);
                }
                db()->commit();
                flash('success', 'Cuenta creada. Ahora inicia sesión para entrar a tu panel.');
                redirect('login.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $error = 'No fue posible registrar la cuenta.';
            }
        }
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/css/styles.css"><title>Registro</title></head><body class="auth-bg page-shell"><div id="loader"><div class="spinner-border text-primary"></div></div><div class="position-fixed top-0 end-0 p-3"><button onclick="toggleTheme()" class="btn btn-soft"><i class="bi bi-moon-stars"></i></button></div><div class="container auth-shell"><div class="row justify-content-center g-4 w-100"><div class="col-xl-5 d-none d-xl-block"><div class="auth-spotlight"><span class="pill-stat bg-transparent border border-light-subtle text-white mb-3"><i class="bi bi-rocket-takeoff"></i> onboarding funcional</span><h2 class="hero-title fs-1 text-white mb-3">Regístrate y entra con un flujo más limpio.</h2><p class="mb-4 opacity-75">Primero creas tu cuenta, luego inicias sesión y aterrizas directo en tu dashboard según el rol elegido.</p><div class="d-grid gap-3"><div class="mini-stat border-0"><strong class="text-white">Talento</strong><div class="text-white-50">Completa tu perfil, sube CV, explora vacantes y deja reseñas.</div></div><div class="mini-stat border-0"><strong class="text-white">Empresa</strong><div class="text-white-50">Edita empresa, configura ubicación, publica vacantes y revisa postulantes.</div></div></div></div></div><div class="col-xl-5 col-lg-7 col-md-9"><div class="auth-card reveal"><div class="text-center mb-4"><div class="brand-gradient fs-2">Crear cuenta</div><p class="section-subtitle mb-0">Regístrate para comenzar</p></div><?php if($error): ?><div class="alert alert-danger rounded-4"><?= e($error) ?></div><?php endif; ?><form method="post" class="row g-3"><div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="name" required value="<?= e($_POST['name'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Contraseña</label><input class="form-control" name="password" type="password" required></div><div class="col-md-6"><label class="form-label">Rol</label><select class="form-select" name="role" id="roleSelect" required><option value="">Selecciona</option><option value="talent" <?= (($_POST['role'] ?? '')==='talent') ? 'selected' : '' ?>>Talento</option><option value="company" <?= (($_POST['role'] ?? '')==='company') ? 'selected' : '' ?>>Empresa</option></select></div><div class="col-md-6"><label class="form-label">Ubicación</label><input class="form-control" name="location" value="<?= e($_POST['location'] ?? '') ?>"></div><div class="col-md-6 role-company d-none"><label class="form-label">Nombre empresa</label><input class="form-control" name="company_name" value="<?= e($_POST['company_name'] ?? '') ?>"></div><div class="col-12 role-talent d-none"><label class="form-label">Habilidades</label><input class="form-control" name="skills" placeholder="PHP, JavaScript, UX" value="<?= e($_POST['skills'] ?? '') ?>"></div><div class="col-12 d-grid gap-2"><button class="btn btn-gradient">Registrarme</button><a class="btn btn-soft" href="login.php">Ya tengo cuenta</a></div></form></div></div></div></div><script>const role=document.getElementById('roleSelect');const rt=document.querySelectorAll('.role-talent');const rc=document.querySelectorAll('.role-company');function toggle(){rt.forEach(x=>x.classList.toggle('d-none',role.value!=='talent'));rc.forEach(x=>x.classList.toggle('d-none',role.value!=='company'));}role.addEventListener('change',toggle);toggle();</script><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/app.js"></script></body></html>