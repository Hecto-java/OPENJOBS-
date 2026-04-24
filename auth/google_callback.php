<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/helpers/helpers.php';
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], (string)$_GET['state'])) redirect('/login.php');
unset($_SESSION['google_oauth_state']);
if (empty($_GET['code'])) redirect('/login.php');
$fields = [
  'code' => (string)$_GET['code'],
  'client_id' => GOOGLE_CLIENT_ID,
  'client_secret' => GOOGLE_CLIENT_SECRET,
  'redirect_uri' => GOOGLE_REDIRECT_URI,
  'grant_type' => 'authorization_code',
];
$ch = curl_init(GOOGLE_TOKEN_URL);
curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
$token = curl_exec($ch); $code=(int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if($token===false || $code!==200) redirect('/login.php');
$t=json_decode($token,true); if(empty($t['access_token'])) redirect('/login.php');
$ch = curl_init(GOOGLE_USERINFO_URL); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$t['access_token']]]); $userRes = curl_exec($ch); $userCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); if($userRes===false||$userCode!==200) redirect('/login.php');
$gu=json_decode($userRes,true); $email=trim((string)($gu['email']??'')); $name=trim((string)($gu['name']??'')); $avatar=trim((string)($gu['picture']??''));
$stmt=db()->prepare('SELECT id,name,email,role,avatar FROM users WHERE email=:email LIMIT 1'); $stmt->execute(['email'=>$email]); $u=$stmt->fetch();
if(!$u){ $_SESSION['google_register']=['name'=>$name,'email'=>$email,'avatar'=>$avatar]; redirect('/register_google.php'); }
session_regenerate_id(true); $_SESSION['user']=$u; redirect('/dashboard.php');