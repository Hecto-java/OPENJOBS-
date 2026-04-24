<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
if (current_user()) redirect('dashboard.php');

$error = '';
$success = flash('success');
$oauthError = flash('error');
if (is_post()) {
    require_once __DIR__ . '/config/database.php';
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Completa correo y contraseña.';
    } else {
        $stmt = db()->prepare('SELECT id,name,email,password,role,avatar FROM users WHERE email=:email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $u = $stmt->fetch();
        if ($u && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = $u;
            log_activity(db(), (int)$u['id'], 'Inició sesión');
            flash('success', 'Bienvenido de nuevo, ' . $u['name']);
            redirect('dashboard.php');
        }
        $error = 'Credenciales incorrectas.';
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/css/styles.css"><title>Login</title></head><body class="auth-bg page-shell"><div id="loader"><div class="spinner-border text-primary"></div></div><div class="position-fixed top-0 end-0 p-3"><button onclick="toggleTheme()" class="btn btn-soft"><i class="bi bi-moon-stars"></i></button></div><div class="container auth-shell"><div class="row align-items-center justify-content-center g-4 w-100"><div class="col-xl-6 d-none d-lg-block"><div class="auth-spotlight glass-card"><span class="pill-stat bg-transparent border border-light-subtle text-white mb-3"><i class="bi bi-lightning-charge"></i> flujo claro de acceso</span><h1 class="hero-title fs-1 mb-3 text-white">Entra y continúa donde te quedaste.</h1><p class="mb-4 opacity-75">Accede al panel según tu rol, termina tu perfil, explora vacantes o administra la plataforma desde un flujo más ordenado.</p><div class="row g-3"><div class="col-6"><div class="mini-stat border-0"><div class="small text-white-50">Paso 1</div><div class="fw-bold fs-5 text-white">Iniciar sesión</div></div></div><div class="col-6"><div class="mini-stat border-0"><div class="small text-white-50">Paso 2</div><div class="fw-bold fs-5 text-white">Ir al dashboard</div></div></div><div class="col-12"><div class="mini-stat border-0"><div class="small text-white-50">Paso 3</div><div class="text-white">Editar perfil, publicar vacantes o revisar postulaciones según tu rol.</div></div></div></div></div></div><div class="col-xl-4 col-lg-6 col-md-8"><div class="auth-card reveal"><div class="text-center mb-4"><div class="brand-gradient fs-2">OpenJobs</div><p class="section-subtitle mb-0">Inicia sesión en tu cuenta</p></div><?php if($success): ?><div class="alert alert-success rounded-4"><?= e($success) ?></div><?php endif; ?><?php if($oauthError): ?><div class="alert alert-warning rounded-4"><?= e($oauthError) ?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger rounded-4"><?= e($error) ?></div><?php endif; ?><?php if (GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== ''): ?>
            <a class="btn btn-soft w-100 mb-3" href="<?= url('auth/google_login.php') ?>"><i class="bi bi-google me-2"></i>Continuar con Google</a>
            <?php else: ?>
            <button type="button" class="btn btn-soft w-100 mb-3" disabled title="Configura GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en config/config.php"><i class="bi bi-google me-2"></i>Google no configurado</button>
            <?php endif; ?><div class="text-center small text-secondary mb-3">o continúa con tu correo</div><form method="post"><div class="mb-3"><label class="form-label">Correo</label><input class="form-control" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div><div class="mb-3"><label class="form-label">Contraseña</label><input class="form-control" type="password" name="password" required></div><button class="btn btn-gradient w-100">Entrar</button></form><div class="d-flex justify-content-between align-items-center mt-3 small"><span class="text-secondary">¿No tienes cuenta?</span><a href="register.php">Crear cuenta</a></div></div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/app.js"></script></body></html>