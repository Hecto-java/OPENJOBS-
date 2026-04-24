<?php
declare(strict_types=1);

// Nombre de la aplicación y entorno
define('APP_NAME', 'OpenJobs');
define('APP_ENV', 'production'); 

// Configuración de la URL base para InfinityFree
$baseUrl = 'http://servicios-escolares.lovestoblog.com';
define('BASE_URL', rtrim($baseUrl, '/'));

// DATOS DE BASE DE DATOS
define('DB_HOST', 'sql306.infinityfree.com');
define('DB_NAME', 'if0_41206567_openjobs');
define('DB_USER', 'if0_41206567');
define('DB_PASS', 'vnZ9Qbw4'); 
define('DB_CHARSET', 'utf8mb4');

// Configuración de APIs (Solo una vez cada una)
define('DEEPSEEK_API_KEY', 'tu_key_aqui');
define('GEMINI_API_KEY', 'AIzaSyD_qmr3Nru7tlm2VP60U_Gzs97jvf8eRDQ');
define('GEMINI_MODEL', 'gemini-1.5-flash');

// Google OAuth (Corregido para tu link real)
define('GOOGLE_REDIRECT_URI', 'http://servicios-escolares.lovestoblog.com/auth/google_callback.php');
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://openidconnect.googleapis.com/v1/userinfo');

define('MAIL_FROM', 'no-reply@servicios-escolares.lovestoblog.com');

// Rutas de carpetas (Sin los puntos ../ porque ya estás en la raíz)
define('PRIVATE_LOG_DIR', __DIR__ . '/storage/logs/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('AVATAR_DIR', UPLOAD_DIR . 'avatars/');
define('LOGO_DIR', UPLOAD_DIR . 'logos/');
define('CV_DIR', UPLOAD_DIR . 'cvs/');

define('MAX_UPLOAD_MB', 4);

// Crear directorios si no existen
foreach ([UPLOAD_DIR, AVATAR_DIR, LOGO_DIR, CV_DIR, PRIVATE_LOG_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Errores (Cambiado a '1' temporalmente para que veas si algo falla)
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_name('openjobs_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => false, // InfinityFree a veces no tiene SSL (HTTPS) activado por defecto
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}