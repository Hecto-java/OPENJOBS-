<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/helpers/helpers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/services/GeminiChatService.php';
require_once __DIR__ . '/../../app/services/GeminiService.php'; // <-- AGREGAR ESTA LÍNEA

header('Content-Type: application/json; charset=utf-8');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    echo json_encode(['ok' => false, 'error' => 'Mensaje vacío']);
    exit;
}

$user = current_user();
$pdo = db();

$chatService = new GeminiChatService($user, $pdo);
$result = $chatService->sendMessage($message);

if ($result['ok']) {
    echo json_encode([
        'ok' => true,
        'response' => $result['response'],
        'context' => $chatService->getContext()
    ]);
} else {
    // Respuesta de fallback cuando Gemini no responde
    $context = $chatService->getContext();
    $role = $context['role'];
    $name = $context['name'];
    
    $fallbackResponses = [
        'talent' => "Hola {$name}. Lamento informarte que el asistente Gemini no está disponible en este momento (error: {$result['error']}). Por favor, intenta de nuevo más tarde. Mientras tanto, puedes revisar tus vacantes recomendadas en la sección 'Explorar vacantes' de tu dashboard.",
        'company' => "Hola {$name}. El asistente Gemini no está disponible temporalmente (error: {$result['error']}). Te sugiero revisar tus postulaciones en la sección 'Vacantes' de tu dashboard mientras resolvemos esto.",
        'admin' => "Hola Admin. Gemini no responde en este momento (error: {$result['error']}). Puedes revisar el panel de administración para ver métricas del sistema.",
        'support' => "Hola {$name}. El asistente Gemini no está disponible (error: {$result['error']}). Revisa los tickets pendientes en la mesa de ayuda.",
    ];
    
    echo json_encode([
        'ok' => true,
        'response' => $fallbackResponses[$role] ?? "Lo siento, el asistente no está disponible en este momento. Por favor intenta de nuevo más tarde. Error: {$result['error']}",
        'context' => $context,
        'note' => 'Gemini no disponible - usando fallback'
    ]);
}