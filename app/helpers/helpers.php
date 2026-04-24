<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . url($path)); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_auth(): void { if (!current_user()) redirect('login.php'); }
function require_role(string ...$roles): void {
    require_auth();
    $role = current_user()['role'] ?? null;
    if (!in_array($role, $roles, true)) redirect('dashboard.php');
}
function flash(string $key, ?string $value = null): ?string {
    if (func_num_args() > 1) { $_SESSION['_flash'][$key] = $value; return null; }
    $v = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $v;
}
function asset(string $path): string { return url('assets/' . ltrim($path, '/')); }
function uploaded_url(?string $path): ?string {
    if (!$path) return null;
    if (preg_match('~^https?://~i', $path)) return $path;
    return url(ltrim($path, '/'));
}
function upload_file(array $file, string $dir, array $allowedExt): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;
    if (($file['size'] ?? 0) > MAX_UPLOAD_MB * 1024 * 1024) return null;
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) return null;
    if (str_contains($dir, 'avatars')) return 'uploads/avatars/' . $name;
    if (str_contains($dir, 'logos')) return 'uploads/logos/' . $name;
    if (str_contains($dir, 'cvs')) return 'uploads/cvs/' . $name;
    return $name;
}
function log_activity(PDO $pdo, int $userId, string $action, string $type = 'info'): void {
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, type) VALUES (?,?,?)');
        $stmt->execute([$userId, $action, $type]);
    } catch (Throwable $e) {
    }
}

function create_notification(PDO $pdo, int $userId, string $title, string $body, string $link = '', string $type = 'info'): void {
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, body, link, type) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId, $title, $body, $link, $type]);
    } catch (Throwable $e) {
    }
}

function unread_notification_count(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0');
        $stmt->execute([$userId]);
        return (int)($stmt->fetch()['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function fetch_recent_notifications(PDO $pdo, int $userId, int $limit = 8): array {
    try {
        $stmt = $pdo->prepare('SELECT id, title, body, link, type, is_read, created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT ' . (int)$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function mark_notifications_read(PDO $pdo, int $userId): void {
    try {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0');
        $stmt->execute([$userId]);
    } catch (Throwable $e) {
    }
}

function role_label(string $role): string {
    return [
        'talent' => 'Talento',
        'company' => 'Empresa',
        'admin' => 'Administrador',
        'support' => 'Soporte',
    ][$role] ?? ucfirst($role);
}

function support_user(PDO $pdo): ?array {
    try {
        $stmt = $pdo->prepare('SELECT id,name,email,role,avatar FROM users WHERE email=? LIMIT 1');
        $stmt->execute(['soporte@openjobs.local']);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function support_chat_link(PDO $pdo): string {
    $support = support_user($pdo);
    return $support ? 'chat.php?to=' . (int)$support['id'] : 'support.php';
}