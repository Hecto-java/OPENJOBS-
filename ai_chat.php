<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/services/GeminiChatService.php';
require_once __DIR__ . '/app/services/GeminiService.php'; // <-- AGREGAR ESTA LÍNEA
require_auth();

$u = current_user();
$pdo = db();
$chatService = new GeminiChatService($u, $pdo);
$context = $chatService->getContext();
$history = $_SESSION['ai_chat_history'] ?? [];
$geminiConfigured = GeminiService::isConfigured(); // <-- AHORA FUNCIONA
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
<title>Asistente Gemini · OpenJobs</title>
<style>
.chat-ai-container {
    height: calc(100vh - 220px);
    display: flex;
    flex-direction: column;
}
.chat-ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: linear-gradient(180deg, var(--surface), var(--surface-2));
    border-radius: 24px;
    margin-bottom: 1rem;
}
.chat-ai-message {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    animation: fadeIn .3s ease;
}
.chat-ai-message.user {
    justify-content: flex-end;
}
.chat-ai-message.user .message-bubble {
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white;
    border-radius: 20px 20px 4px 20px;
}
.chat-ai-message.assistant .message-bubble {
    background: var(--surface-solid);
    border: 1px solid var(--border);
    border-radius: 20px 20px 20px 4px;
}
.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white;
    font-weight: bold;
}
.chat-ai-message.user .message-avatar {
    background: var(--surface-3);
    color: var(--text);
    order: 2;
}
.message-bubble {
    max-width: 75%;
    padding: 0.9rem 1.2rem;
    line-height: 1.5;
}
.message-time {
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 0.25rem;
}
.chat-ai-input {
    display: flex;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface);
    border-radius: 24px;
    border: 1px solid var(--border);
}
.chat-ai-input textarea {
    flex: 1;
    border: none !important;
    background: transparent !important;
    resize: none;
    padding: 0.75rem;
    font-family: inherit;
}
.chat-ai-input textarea:focus {
    outline: none;
    box-shadow: none !important;
}
.chat-ai-input button {
    align-self: flex-end;
}
.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 0.5rem 0;
}
.typing-indicator span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--muted);
    animation: typing 1.4s infinite ease-in-out;
}
.typing-indicator span:nth-child(1) { animation-delay: 0s; }
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-10px); opacity: 1; }
}
.context-badge {
    font-size: 0.75rem;
    background: var(--surface-2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.warning-box {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid var(--warning);
    border-radius: 16px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
}
</style>
</head>
<body class="page-shell">
<div id="loader"><div class="spinner-border text-primary"></div></div>

<nav class="navbar navbar-premium">
    <div class="container">
        <a class="navbar-brand brand-gradient" href="dashboard.php">OpenJobs</a>
        <div class="d-flex gap-2">
            <button onclick="toggleTheme()" class="btn btn-soft"><i class="bi bi-moon-stars"></i></button>
            <a class="btn btn-soft" href="dashboard.php">Dashboard</a>
            <a class="btn btn-gradient" href="ai.php">AI Studio</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="glass-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="pill-stat mb-2"><i class="bi bi-robot"></i> Google Gemini AI</span>
                <h1 class="section-title mb-1">Asistente Inteligente</h1>
                <p class="section-subtitle mb-0">
                    Pregúntame cualquier cosa sobre OpenJobs. Te ayudaré con vacantes, perfil, postulaciones, 
                    y todo lo que necesites según tu rol de <?= e($context['role_label']) ?>.
                </p>
            </div>
            <div class="d-flex gap-2">
                <?php if(!$geminiConfigured): ?>
                    <span class="badge text-bg-warning">⚠️ Gemini sin configurar</span>
                <?php else: ?>
                    <span class="badge text-bg-success">✅ Gemini activo</span>
                <?php endif; ?>
                <button class="btn btn-soft" id="clearHistoryBtn">
                    <i class="bi bi-trash"></i> Limpiar historial
                </button>
            </div>
        </div>
    </div>

    <?php if(!$geminiConfigured): ?>
    <div class="warning-box mb-4">
        <i class="bi bi-info-circle-fill text-warning me-2"></i>
        <strong>Gemini no está configurado.</strong> Agrega tu API key en <code>config/config.php</code> (constante GEMINI_API_KEY).
        <a href="https://aistudio.google.com/apikey" target="_blank" class="ms-2">Obtener API key gratis →</a>
        <br><small>Mientras tanto, el asistente funcionará con respuestas de respaldo.</small>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="panel-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> Tu contexto</h5>
                <div class="d-flex flex-column gap-2">
                    <div class="context-badge">
                        <i class="bi bi-person"></i> <?= e($context['name']) ?>
                    </div>
                    <div class="context-badge">
                        <i class="bi bi-person-badge"></i> <?= e($context['role_label']) ?>
                    </div>
                    <?php if ($context['role'] === 'talent'): ?>
    <div class="context-badge">
        <i class="bi bi-code"></i> <?= e(mb_strimwidth($context['profile']['skills'] ?? 'No especificadas', 0, 40, '...')) ?>
    </div>
    <div class="context-badge">
        <i class="bi bi-briefcase"></i> <?= $context['profile']['experience_years'] ?? 0 ?> años exp.
    </div>
    <div class="context-badge">
        <i class="bi bi-send-check"></i> <?= $context['applications_count'] ?? 0 ?> postulaciones
    </div>
    <div class="context-badge">
        <i class="bi bi-star"></i> <?= $context['reviews_count'] ?? 0 ?> reseñas
    </div>x
                    <?php elseif ($context['role'] === 'company'): ?>
                        <div class="context-badge">
                            <i class="bi bi-building"></i> <?= e($context['company']['name']) ?>
                        </div>
                        <div class="context-badge">
                            <i class="bi bi-briefcase"></i> <?= $context['active_jobs'] ?> vacantes activas
                        </div>
                        <div class="context-badge">
                            <i class="bi bi-people"></i> <?= $context['total_applications'] ?> postulaciones
                        </div>
                        <div class="context-badge">
                            <i class="bi bi-star"></i> ⭐ <?= $context['avg_rating'] ?> / 5
                        </div>
                    <?php elseif ($context['role'] === 'admin'): ?>
                        <div class="context-badge">
                            <i class="bi bi-people"></i> <?= $context['stats']['total_users'] ?> usuarios
                        </div>
                        <div class="context-badge">
                            <i class="bi bi-building"></i> <?= $context['stats']['total_companies'] ?> empresas
                        </div>
                        <div class="context-badge">
                            <i class="bi bi-briefcase"></i> <?= $context['stats']['active_jobs'] ?> vacantes activas
                        </div>
                        <div class="context-badge">
                            <i class="bi bi-star"></i> <?= $context['stats']['pending_reviews'] ?> reseñas pendientes
                        </div>
                    <?php elseif ($context['role'] === 'support'): ?>
                        <div class="context-badge">
                            <i class="bi bi-ticket"></i> <?= $context['pending_tickets'] ?> tickets pendientes
                        </div>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="small text-muted">
                    <i class="bi bi-lightbulb"></i> Ejemplos de preguntas:
                    <ul class="small mt-2 mb-0">
                        <?php if ($context['role'] === 'talent'): ?>
                            <li>¿Qué vacantes me recomiendas?</li>
                            <li>¿Cómo mejorar mi perfil?</li>
                            <li>Analiza mis habilidades</li>
                            <li>Consejos para entrevistas</li>
                        <?php elseif ($context['role'] === 'company'): ?>
                            <li>¿Cómo escribir una vacante atractiva?</li>
                            <li>¿Cómo mejorar mi reputación?</li>
                            <li>Consejos para entrevistas</li>
                        <?php elseif ($context['role'] === 'admin'): ?>
                            <li>Resumen del sistema</li>
                            <li>¿Qué revisar primero?</li>
                            <li>Alertas de moderación</li>
                        <?php elseif ($context['role'] === 'support'): ?>
                            <li>¿Cómo responder este ticket?</li>
                            <li>Problemas comunes</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="panel-card p-0 overflow-hidden chat-ai-container">
                <div class="chat-ai-messages" id="chatMessages">
                    <?php if (empty($history)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-dots fs-1"></i>
                            <p class="mt-3">
                                <strong>¡Hola <?= e($context['name']) ?>!</strong><br>
                                Soy tu asistente de OpenJobs con Gemini AI.<br>
                                Pregúntame cualquier cosa sobre la plataforma, tu perfil, vacantes, o cómo sacarle el máximo provecho.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($history as $msg): ?>
                            <div class="chat-ai-message <?= $msg['role'] ?>">
                                <div class="message-avatar">
                                    <?= $msg['role'] === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-robot"></i>' ?>
                                </div>
                                <div>
                                    <div class="message-bubble"><?= nl2br(e($msg['content'])) ?></div>
                                    <div class="message-time"><?= date('H:i') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="chat-ai-input">
                    <textarea id="messageInput" rows="1" placeholder="Escribe tu mensaje... (Enter para enviar, Shift+Enter para nueva línea)"></textarea>
                    <button class="btn btn-gradient" id="sendBtn">
                        <i class="bi bi-send"></i> Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
const messageInput = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');
const clearHistoryBtn = document.getElementById('clearHistoryBtn');
let isTyping = false;

function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function addMessage(role, content, isNew = true) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-ai-message ${role}`;
    messageDiv.innerHTML = `
        <div class="message-avatar">
            ${role === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-robot"></i>'}
        </div>
        <div>
            <div class="message-bubble">${escapeHtml(content).replace(/\n/g, '<br>')}</div>
            <div class="message-time">${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

function showTypingIndicator() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-ai-message assistant';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `
        <div class="message-avatar"><i class="bi bi-robot"></i></div>
        <div>
            <div class="typing-indicator">
                <span></span><span></span><span></span>
            </div>
        </div>
    `;
    chatMessages.appendChild(typingDiv);
    scrollToBottom();
}

function removeTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) indicator.remove();
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message || isTyping) return;
    
    messageInput.value = '';
    messageInput.style.height = 'auto';
    
    addMessage('user', message);
    
    isTyping = true;
    showTypingIndicator();
    
    try {
        const response = await fetch('api/chat_ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message })
        });
        
        const data = await response.json();
        removeTypingIndicator();
        
        if (data.ok) {
            addMessage('assistant', data.response);
        } else {
            addMessage('assistant', 'Lo siento, hubo un error. Por favor intenta de nuevo.');
        }
    } catch (error) {
        removeTypingIndicator();
        addMessage('assistant', 'Error de conexión. Por favor verifica tu conexión a internet.');
    }
    
    isTyping = false;
}

function clearHistory() {
    if (confirm('¿Borrar todo el historial de conversación?')) {
        fetch('api/chat_clear.php', { method: 'POST' })
            .then(() => {
                chatMessages.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-chat-dots fs-1"></i>
                        <p class="mt-3">Historial borrado. ¡Empieza una nueva conversación!</p>
                    </div>
                `;
            });
    }
}

sendBtn.addEventListener('click', sendMessage);
clearHistoryBtn.addEventListener('click', clearHistory);

messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

scrollToBottom();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>