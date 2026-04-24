<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers/helpers.php';

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    flash('error', 'Google Login no está configurado todavía. Agrega GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET.');
    redirect('/login.php');
}
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'prompt' => 'select_account',
    'state' => $state,
];
header('Location: ' . GOOGLE_AUTH_URL . '?' . http_build_query($params));
exit;