<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/services/GeminiService.php'; // <-- VERIFICAR QUE ESTÁ
require_once __DIR__ . '/app/services/CandidateRankingService.php';
require_once __DIR__ . '/app/services/CVAnalysisService.php';
require_once __DIR__ . '/app/services/RecommendationService.php';
require_auth();

$u = current_user();
$pdo = db();
$result = '';
$action = $_GET['action'] ?? '';
$title = 'Selecciona una acción';
$error = '';

// Verificar si Gemini está configurado
$geminiConfigured = GeminiService::isConfigured();

// ... resto del código igual

if ($action !== '' && !$geminiConfigured && !in_array($action, ['candidate_rank', 'recommend_jobs', 'analyze_cv'])) {
    $error = '⚠️ Gemini no está configurado. Agrega tu API key en config/config.php';
    $result = 'No se puede ejecutar esta acción porque Gemini no está configurado. Visita https://aistudio.google.com/apikey para obtener una API key gratis.';
}

if ($action === 'improve_bio' && $u['role'] === 'talent' && $geminiConfigured) {
    $p = $pdo->prepare('SELECT bio, skills, headline FROM talent_profiles WHERE user_id=?');
    $p->execute([$u['id']]);
    $tp = $p->fetch() ?: [];
    
    $prompt = "Eres un experto en desarrollo profesional. Mejora este perfil de talento para OpenJobs.

DATOS ACTUALES:
- Headline: " . ($tp['headline'] ?? 'No tiene') . "
- Bio: " . ($tp['bio'] ?? 'No tiene') . "
- Skills: " . ($tp['skills'] ?? 'No tiene') . "

INSTRUCCIONES:
1. Escribe un headline más atractivo (máximo 80 caracteres)
2. Mejora la biografía destacando logros y habilidades clave (máximo 150 palabras)
3. Organiza las habilidades en categorías si es necesario
4. Mantén un tono profesional pero accesible
5. Responde SOLO con el perfil mejorado, sin explicaciones adicionales";

    $response = GeminiService::request($prompt);
    $result = $response ?? 'No se pudo generar la mejora.';
    $title = 'Bio mejorada con Gemini';
    
} elseif ($action === 'improve_job' && $u['role'] === 'company' && $geminiConfigured) {
    $j = $pdo->query('SELECT title, description, technology, modality, employment_type, location FROM jobs ORDER BY id DESC LIMIT 1')->fetch();
    
    $prompt = "Eres un redactor especializado en reclutamiento. Mejora esta vacante para OpenJobs.

VACANTE ACTUAL:
- Título: " . ($j['title'] ?? 'No especificado') . "
- Descripción: " . ($j['description'] ?? 'No especificada') . "
- Tecnologías: " . ($j['technology'] ?? 'No especificadas') . "
- Modalidad: " . ($j['modality'] ?? 'No especificada') . "
- Tipo de empleo: " . ($j['employment_type'] ?? 'No especificado') . "
- Ubicación: " . ($j['location'] ?? 'No especificada') . "

INSTRUCCIONES:
1. Crea un título más atractivo y específico
2. Mejora la descripción destacando: responsabilidades clave, requisitos indispensables, beneficios y cultura
3. Organiza la información en secciones claras
4. Mantén un tono profesional pero atractivo
5. Responde SOLO con la vacante mejorada, sin explicaciones adicionales";

    $response = GeminiService::request($prompt);
    $result = $response ?? 'No se pudo generar la mejora.';
    $title = 'Vacante mejorada con Gemini';
    
} elseif ($action === 'suggest_reply' && $geminiConfigured) {
    $to = (int)($_GET['to'] ?? 0);
    $m = $pdo->prepare('SELECT body, sender_id FROM messages WHERE sender_id=? AND receiver_id=? ORDER BY id DESC LIMIT 1');
    $m->execute([$to, $u['id']]);
    $last = $m->fetch();
    
    $senderStmt = $pdo->prepare('SELECT name, role FROM users WHERE id=?');
    $senderStmt->execute([$last['sender_id'] ?? 0]);
    $sender = $senderStmt->fetch();
    
    $prompt = "Eres un asistente de comunicación profesional. Sugiere una respuesta para este mensaje en OpenJobs.

CONTEXTO:
- Remitente: " . ($sender['name'] ?? 'Usuario') . " (" . ($sender['role'] ?? 'desconocido') . ")
- Mensaje recibido: " . ($last['body'] ?? 'No hay mensaje') . "
- Tu rol: " . role_label($u['role']) . "

INSTRUCCIONES:
1. La respuesta debe ser breve, profesional y cordial
2. Mantén un tono apropiado según el contexto
3. Sé específico y responde directamente al mensaje
4. Responde SOLO con la respuesta sugerida, sin explicaciones adicionales";

    $response = GeminiService::request($prompt);
    $result = $response ?? 'No se pudo generar la respuesta.';
    $title = 'Respuesta sugerida con Gemini';
    
} elseif ($action === 'candidate_rank' && $u['role'] === 'company') {
    $job = $pdo->query('SELECT j.* FROM jobs j ORDER BY id DESC LIMIT 1')->fetch() ?: [];
    $cand = $pdo->query('SELECT u.id, u.name, tp.skills, tp.experience_years, tp.xp, tp.headline 
                         FROM users u 
                         JOIN talent_profiles tp ON tp.user_id = u.id 
                         WHERE u.role = "talent" 
                         ORDER BY u.id DESC LIMIT 5')->fetchAll();
    $rank = CandidateRankingService::rank($job, $cand);
    $result = json_encode($rank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $title = 'Ranking de candidatos (IA)';
    
} elseif ($action === 'recommend_jobs' && $u['role'] === 'talent') {
    $jobs = $pdo->query('SELECT j.id, j.title, j.description, j.technology, j.modality, j.experience_required, j.location, c.name as company_name
                         FROM jobs j 
                         LEFT JOIN companies c ON c.id = j.company_id
                         WHERE j.status = "active" 
                         ORDER BY j.id DESC LIMIT 10')->fetchAll();
    $matches = RecommendationService::recommend((int)$u['id'], $jobs);
    
    if ($geminiConfigured) {
        $result = "<strong>🎯 Vacantes recomendadas por Gemini AI</strong>\n\n";
        if (empty($matches)) {
            $result .= "No se encontraron vacantes que coincidan con tu perfil. Te recomiendo completar tu perfil con más habilidades y experiencia.";
        } else {
            foreach (array_values($matches) as $i => $match) {
                $result .= ($i+1) . ". **" . $match['title'] . "**\n";
                $result .= "   📍 " . $match['location'] . " · " . $match['modality'] . "\n";
                $result .= "   🎯 Match: " . $match['score'] . "% · Encaje: " . $match['fit'] . "\n";
                $result .= "   💡 " . $match['reason'] . "\n\n";
            }
        }
    } else {
        $result = json_encode(array_values($matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    $title = 'Recomendación inteligente de vacantes';
    
} elseif ($action === 'moderation_overview' && $u['role'] === 'admin' && $geminiConfigured) {
    $stats = [
        'usuarios' => (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
        'empresas' => (int)$pdo->query('SELECT COUNT(*) c FROM companies')->fetch()['c'],
        'vacantes' => (int)$pdo->query('SELECT COUNT(*) c FROM jobs')->fetch()['c'],
        'postulaciones' => (int)$pdo->query('SELECT COUNT(*) c FROM applications')->fetch()['c'],
        'reseñas_pendientes' => (int)$pdo->query('SELECT COUNT(*) c FROM reviews WHERE moderation_status = "pending"')->fetch()['c'],
        'empresas_pendientes' => (int)$pdo->query('SELECT COUNT(*) c FROM companies WHERE verified = 0')->fetch()['c'],
        'notificaciones_no_leidas' => (int)$pdo->query('SELECT COUNT(*) c FROM notifications WHERE is_read=0')->fetch()['c'],
        'ultima_actividad' => $pdo->query('SELECT action, created_at FROM activity_logs ORDER BY id DESC LIMIT 3')->fetchAll(),
    ];
    
    $prompt = "Eres un asistente de administración para OpenJobs. Analiza estas métricas y genera un resumen ejecutivo.

MÉTRICAS DEL SISTEMA:
" . json_encode($stats, JSON_UNESCAPED_UNICODE) . "

INSTRUCCIONES:
1. Resume el estado general del sistema en 2-3 frases
2. Identifica los principales riesgos o áreas de atención
3. Sugiere prioridades de acción para las próximas 24h
4. Responde en español, con un tono profesional y ejecutivo
5. Sé específico y usa los datos proporcionados";

    $response = GeminiService::request($prompt);
    $result = $response ?? 'No se pudo generar el resumen.';
    $title = 'Resumen administrativo con Gemini AI';
    
} elseif ($action === 'support_reply' && in_array($u['role'], ['admin','support'], true) && $geminiConfigured) {
    $m = $pdo->query('SELECT m.body, m.created_at, u.name as user_name, u.role as user_role 
                      FROM messages m 
                      JOIN users u ON u.id = m.sender_id 
                      WHERE m.receiver_id = (SELECT id FROM users WHERE email = "soporte@openjobs.local" LIMIT 1)
                      ORDER BY m.id DESC LIMIT 1')->fetch();
    
    $prompt = "Eres un agente de soporte técnico para OpenJobs. Redacta una respuesta profesional para este ticket.

TICKET RECIBIDO:
- Usuario: " . ($m['user_name'] ?? 'Usuario') . " (" . ($m['user_role'] ?? 'rol desconocido') . ")
- Fecha: " . ($m['created_at'] ?? 'reciente') . "
- Mensaje: " . ($m['body'] ?? 'No hay mensaje') . "

INSTRUCCIONES:
1. Redacta una respuesta breve, empática y técnica
2. Demuestra comprensión del problema
3. Ofrece pasos concretos para resolverlo
4. Mantén un tono amable y profesional
5. Responde SOLO con la respuesta, sin explicaciones adicionales";

    $response = GeminiService::request($prompt);
    $result = $response ?? 'No se pudo generar la respuesta.';
    $title = 'Respuesta de soporte sugerida con Gemini AI';
    
} elseif ($action === 'analyze_cv' && $u['role'] === 'talent') {
    $p = $pdo->prepare('SELECT * FROM talent_profiles WHERE user_id=?');
    $p->execute([$u['id']]);
    $tp = $p->fetch() ?: [];
    $analysis = CVAnalysisService::analyzeCurrentUserCv($u, $tp);
    
    if ($analysis) {
        if ($geminiConfigured) {
            $result = "<strong>📄 Análisis de CV con Gemini AI</strong>\n\n";
            $result .= "**Resumen:** " . ($analysis['summary'] ?? 'No disponible') . "\n\n";
            $result .= "**✅ Fortalezas:**\n";
            foreach (($analysis['strengths'] ?? []) as $strength) {
                $result .= "• " . $strength . "\n";
            }
            $result .= "\n**⚠️ Áreas de mejora:**\n";
            foreach (($analysis['gaps'] ?? []) as $gap) {
                $result .= "• " . $gap . "\n";
            }
            $result .= "\n**🎯 Roles sugeridos:**\n";
            foreach (($analysis['suggested_roles'] ?? []) as $role) {
                $result .= "• " . $role . "\n";
            }
            $result .= "\n**📊 Puntuación: " . ($analysis['score'] ?? 0) . "/100**\n";
            $result .= "\n**🔑 Palabras clave detectadas:** " . implode(', ', ($analysis['keywords'] ?? []));
        } else {
            $result = json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } else {
        $result = 'No se pudo analizar el CV. Verifica que tengas un PDF cargado y que el servidor pueda leerlo.';
    }
    $title = 'Análisis automático de CV con Gemini';
    
} elseif ($action !== '') {
    $result = $result ?: 'Acción no disponible para tu rol o Gemini no configurado.';
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
<title>AI Studio · OpenJobs</title>
</head>
<body class="page-shell">
<div id="loader"><div class="spinner-border text-primary"></div></div>
<div class="container py-4 py-lg-5">
    <div class="glass-card mb-4 reveal">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="pill-stat mb-2"><i class="bi bi-stars"></i> Google Gemini AI</span>
                <h1 class="section-title mb-1">AI Studio</h1>
                <p class="section-subtitle mb-0">Recomendaciones inteligentes, análisis de CV y asistencia con Gemini para talento, empresas y administración.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-gradient" href="ai_chat.php">
                    <i class="bi bi-chat-dots"></i> Chat con IA
                </a>
                <a class="btn btn-soft" href="dashboard.php">Volver</a>
                <button onclick="toggleTheme()" class="btn btn-soft"><i class="bi bi-moon-stars"></i></button>
            </div>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-warning rounded-4"><?= e($error) ?></div>
    <?php endif; ?>
    
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="premium-card h-100 reveal">
                <div class="list-group list-group-flush rounded-4">
                    <?php if($u['role']==='talent'): ?>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=improve_bio">
                            <i class="bi bi-person-vcard me-2"></i>Mejorar bio con IA
                        </a>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=recommend_jobs">
                            <i class="bi bi-briefcase me-2"></i>Recomendación de vacantes
                        </a>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=analyze_cv">
                            <i class="bi bi-file-earmark-text me-2"></i>Análisis de CV
                        </a>
                    <?php endif; ?>
                    <?php if($u['role']==='company'): ?>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=improve_job">
                            <i class="bi bi-briefcase me-2"></i>Mejorar vacante
                        </a>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=candidate_rank">
                            <i class="bi bi-bar-chart me-2"></i>Ranking candidatos
                        </a>
                    <?php endif; ?>
                    <?php if($u['role']==='admin'): ?>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=moderation_overview">
                            <i class="bi bi-shield-check me-2"></i>Resumen administrativo
                        </a>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=support_reply">
                            <i class="bi bi-life-preserver me-2"></i>Sugerir respuesta soporte
                        </a>
                    <?php endif; ?>
                    <?php if($u['role']==='support'): ?>
                        <a class="list-group-item list-group-item-action py-3" href="ai.php?action=support_reply">
                            <i class="bi bi-life-preserver me-2"></i>Sugerir respuesta soporte
                        </a>
                    <?php endif; ?>
                    <a class="list-group-item list-group-item-action py-3" href="ai.php?action=suggest_reply">
                        <i class="bi bi-chat-dots me-2"></i>Sugerir respuesta mensaje
                    </a>
                </div>
                
                <hr class="my-3">
                
                <div class="small text-muted">
                    <i class="bi bi-robot"></i> <strong>Gemini AI</strong><br>
                    <?php if($geminiConfigured): ?>
                        <span class="text-success">✅ API configurada</span>
                    <?php else: ?>
                        <span class="text-warning">⚠️ Sin API key</span><br>
                        <a href="https://aistudio.google.com/apikey" target="_blank" class="small">Obtener API key gratis</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0"><?= e($title) ?></h5>
                    <?php if($geminiConfigured): ?>
                        <span class="badge text-bg-success">Gemini AI activo</span>
                    <?php else: ?>
                        <span class="badge text-bg-warning">Usando respaldo local</span>
                    <?php endif; ?>
                </div>
                
                <?php if(!$geminiConfigured && !in_array($action, ['candidate_rank', 'recommend_jobs', 'analyze_cv']) && $action !== ''): ?>
                    <div class="alert alert-warning rounded-4 mb-3">
                        <strong>⚠️ Gemini no está configurado</strong><br>
                        Agrega tu API key en <code>config/config.php</code> (constante GEMINI_API_KEY).<br>
                        Obtén una clave gratis en <a href="https://aistudio.google.com/apikey" target="_blank">https://aistudio.google.com/apikey</a>
                    </div>
                <?php endif; ?>
                
                <div class="result-box" style="white-space: pre-wrap; font-family: monospace;">
                    <?= $result ?: 'Selecciona una acción del menú izquierdo para generar resultados con Gemini AI.' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mostrar toast si hay error de configuración
<?php if(!$geminiConfigured && $action !== '' && !in_array($action, ['candidate_rank', 'recommend_jobs', 'analyze_cv'])): ?>
    setTimeout(() => {
        showToast('Gemini no configurado. Agrega tu API key en config/config.php', 'warning');
    }, 500);
<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>