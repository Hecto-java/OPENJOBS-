<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/services/GeminiService.php'; // <-- CAMBIADO

require_auth();
$u = current_user();
$pdo = db();
$success = flash('success');
$error = '';
$filter = $_GET['filter'] ?? 'recent';
if (!in_array($filter, ['recent', 'best', 'worst'], true)) {
    $filter = 'recent';
}

$orderSql = $filter === 'best' ? 'r.rating DESC, r.id DESC' : ($filter === 'worst' ? 'r.rating ASC, r.id DESC' : 'r.id DESC');

if ($u['role'] === 'talent' && is_post()) {
    $companyId = (int)($_POST['company_id'] ?? 0);
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim((string)($_POST['comment'] ?? ''));

    if ($companyId <= 0 || $comment === '') {
        $error = 'Completa la empresa y tu comentario.';
    } else {
        $moderation = GeminiService::classifyReview($comment);
        $pdo->prepare('INSERT INTO reviews (user_id, company_id, rating, comment, moderation_status, moderation_reason, ai_score) VALUES (?,?,?,?,?,?,?)')
            ->execute([
                (int)$u['id'],
                $companyId,
                $rating,
                $comment,
                $moderation['status'],
                $moderation['reason'],
                $moderation['score'],
            ]);

        $pdo->prepare('UPDATE users SET points = points + 10, level = FLOOR((points + 10) / 100) + 1 WHERE id=?')->execute([(int)$u['id']]);

        $companyUserStmt = $pdo->prepare('SELECT user_id, name FROM companies WHERE id=? LIMIT 1');
        $companyUserStmt->execute([$companyId]);
        $companyMeta = $companyUserStmt->fetch() ?: [];
        if (!empty($companyMeta['user_id'])) {
            create_notification(
                $pdo,
                (int)$companyMeta['user_id'],
                'Nueva reseña recibida',
                'Se publicó una reseña para ' . ($companyMeta['name'] ?? 'tu empresa') . '.',
                'reviews.php',
                'review'
            );
        }

        log_activity($pdo, (int)$u['id'], 'Publicó una reseña', $moderation['status'] === 'approved' ? 'info' : 'warning');
        flash('success', $moderation['status'] === 'approved'
            ? 'Reseña publicada y analizada por IA.'
            : 'Tu reseña quedó pendiente de revisión automática.');
        redirect('/reviews.php');
    }
}

if ($u['role'] === 'talent') {
    $companies = $pdo->query('SELECT id,name FROM companies ORDER BY name')->fetchAll();
    $stmt = $pdo->prepare('SELECT r.*, c.name company_name FROM reviews r LEFT JOIN companies c ON c.id=r.company_id WHERE r.user_id=? ORDER BY ' . $orderSql);
    $stmt->execute([$u['id']]);
    $reviews = $stmt->fetchAll();
    $companyId = 0;
    $summary = null;
} else {
    $stmt = $pdo->prepare('SELECT id, name FROM companies WHERE user_id=?');
    $stmt->execute([$u['id']]);
    $companyMeta = $stmt->fetch() ?: [];
    $companyId = (int)($companyMeta['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT r.*, u.name reviewer_name FROM reviews r LEFT JOIN users u ON u.id=r.user_id WHERE r.company_id=? ORDER BY ' . $orderSql);
    $stmt->execute([$companyId]);
    $reviews = $stmt->fetchAll();

    $sumStmt = $pdo->prepare("SELECT 
        ROUND(AVG(rating),1) avg_rating,
        SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) star5,
        SUM(CASE WHEN rating=4 THEN 1 ELSE 0 END) star4,
        SUM(CASE WHEN rating=3 THEN 1 ELSE 0 END) star3,
        SUM(CASE WHEN rating=2 THEN 1 ELSE 0 END) star2,
        SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) star1,
        SUM(CASE WHEN moderation_status='pending' THEN 1 ELSE 0 END) pending_total,
        COUNT(*) total_reviews
        FROM reviews WHERE company_id=?");
    $sumStmt->execute([$companyId]);
    $summary = $sumStmt->fetch() ?: null;
}
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
<link rel="stylesheet" href="assets/css/styles.css">
<?php if($u['role'] !== 'talent'): ?><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><?php endif; ?>
<title>Reseñas · OpenJobs</title>
</head>
<body class="page-shell">
<div class="container py-4">
    <div class="glass-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="pill-stat mb-2"><i class="bi bi-star"></i> Reseñas OpenJobs</span>
                <h1 class="section-title mb-1"><?= $u['role']==='talent' ? 'Comparte tu experiencia laboral' : 'Percepción laboral de tu empresa' ?></h1>
                <p class="section-subtitle mb-0">
                    <?= $u['role']==='talent'
                        ? 'Publica reseñas con estrellas y validación automática por IA para mantener información útil y confiable.'
                        : 'Consulta calificaciones, distribución de estrellas y reseñas pendientes desde una sola vista.' ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-soft" href="dashboard.php">Dashboard</a>
                <?php if($u['role']==='talent'): ?><a class="btn btn-gradient" href="jobs.php">Explorar vacantes</a><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if($success): ?><div class="alert alert-success rounded-4"><?= e($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger rounded-4"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <?php if($u['role']==='talent'): ?>
            <div class="col-lg-5">
                <div class="panel-card">
                    <h4 class="fw-bold mb-3">Nueva reseña</h4>
                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Empresa</label>
                            <select name="company_id" class="form-select" required>
                                <?php foreach($companies as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Calificación</label>
                            <select name="rating" class="form-select">
                                <option value="5">⭐⭐⭐⭐⭐ Excelente</option>
                                <option value="4">⭐⭐⭐⭐ Buena</option>
                                <option value="3">⭐⭐⭐ Regular</option>
                                <option value="2">⭐⭐ Deficiente</option>
                                <option value="1">⭐ Mala</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Comentario</label>
                            <textarea name="comment" class="form-control" rows="5" placeholder="Describe horarios, ambiente, prestaciones, salario o crecimiento profesional." required></textarea>
                            <div class="small section-subtitle mt-2">La IA revisa si la reseña parece útil y real antes de marcarla como aprobada o pendiente.</div>
                        </div>
                        <div class="col-12"><button class="btn btn-gradient">Publicar reseña</button></div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="col-lg-5">
                <div class="panel-card h-100">
                    <h4 class="fw-bold mb-3">Resumen general</h4>
                    <div class="row g-3 mb-3">
                        <div class="col-6"><div class="metric-card"><div class="metric-label">Promedio</div><div class="metric-value"><?= e((string)($summary['avg_rating'] ?? '0.0')) ?>⭐</div></div></div>
                        <div class="col-6"><div class="metric-card"><div class="metric-label">Pendientes</div><div class="metric-value"><?= (int)($summary['pending_total'] ?? 0) ?></div></div></div>
                    </div>
                    <canvas id="ratingChart" height="210"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-lg-<?= $u['role']==='talent' ? '7' : '7' ?>">
            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h4 class="fw-bold mb-0"><?= $u['role']==='talent' ? 'Tus reseñas' : 'Reseñas recibidas' ?></h4>
                    <div class="d-flex gap-2">
                        <a class="btn btn-soft btn-sm <?= $filter==='recent' ? 'active-filter' : '' ?>" href="reviews.php?filter=recent">Más recientes</a>
                        <a class="btn btn-soft btn-sm <?= $filter==='best' ? 'active-filter' : '' ?>" href="reviews.php?filter=best">Mejores</a>
                        <a class="btn btn-soft btn-sm <?= $filter==='worst' ? 'active-filter' : '' ?>" href="reviews.php?filter=worst">Peores</a>
                    </div>
                </div>
                <?php if(!$reviews): ?><div class="section-subtitle">Todavía no hay reseñas para mostrar.</div><?php endif; ?>
                <?php foreach($reviews as $r): ?>
                    <div class="showcase-card mb-3">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div>
                                <div class="fw-semibold"><?= e($u['role']==='talent' ? $r['company_name'] : ($r['reviewer_name'] ?? 'Usuario')) ?></div>
                                <div class="small section-subtitle mb-2"><?= e($r['created_at']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="mb-1"><?= str_repeat('⭐', (int)$r['rating']) . str_repeat('☆', max(0, 5 - (int)$r['rating'])) ?></div>
                                <span class="badge <?= ($r['moderation_status'] ?? 'approved') === 'approved' ? 'text-bg-success' : 'text-bg-warning' ?>">
                                    <?= ($r['moderation_status'] ?? 'approved') === 'approved' ? 'Aprobada por IA' : 'Pendiente' ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-2"><?= nl2br(e($r['comment'])) ?></div>
                        <div class="small section-subtitle">
                            IA: <?= (int)($r['ai_score'] ?? 0) ?> / 100 · <?= e($r['moderation_reason'] ?? 'Sin observaciones') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if($u['role'] !== 'talent' && $summary): ?>
<script>
const ratingsCanvas = document.getElementById('ratingChart');
if (ratingsCanvas) {
    new Chart(ratingsCanvas, {
        type: 'doughnut',
        data: {
            labels: ['5 estrellas', '4 estrellas', '3 estrellas', '2 estrellas', '1 estrella'],
            datasets: [{
                data: [
                    <?= (int)($summary['star5'] ?? 0) ?>,
                    <?= (int)($summary['star4'] ?? 0) ?>,
                    <?= (int)($summary['star3'] ?? 0) ?>,
                    <?= (int)($summary['star2'] ?? 0) ?>,
                    <?= (int)($summary['star1'] ?? 0) ?>
                ]
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
}
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>