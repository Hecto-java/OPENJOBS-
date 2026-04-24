<?php
declare(strict_types=1);

class GeminiChatService {
    private string $apiKey;
    private string $model;
    private array $conversationHistory;
    private array $userContext;
    private PDO $pdo;

    public function __construct(array $user, PDO $pdo) {
        $this->apiKey = defined('GEMINI_API_KEY') ? (string) GEMINI_API_KEY : '';
        $this->model = defined('GEMINI_MODEL') ? (string) GEMINI_MODEL : 'gemini-1.5-flash';
        $this->conversationHistory = $_SESSION['ai_chat_history'] ?? [];
        $this->userContext = $this->buildUserContext($user, $pdo);
        $this->pdo = $pdo;
    }

    private function buildUserContext(array $user, PDO $pdo): array {
        // Valores por defecto para evitar undefined array keys
        $context = [
            'user_id' => $user['id'] ?? 0,
            'name' => $user['name'] ?? 'Usuario',
            'role' => $user['role'] ?? 'talent',
            'role_label' => role_label($user['role'] ?? 'talent'),
            'current_date' => date('Y-m-d H:i:s'),
            // Valores por defecto para talento
            'applications_count' => 0,
            'reviews_count' => 0,
            'profile' => [
                'headline' => 'No especificado',
                'bio' => 'Sin biografía',
                'skills' => 'No especificadas',
                'experience_years' => 0,
                'location' => 'No especificada',
                'xp' => 0,
                'has_cv' => false,
            ],
            'recommended_jobs' => [],
            // Valores por defecto para empresa
            'company' => [
                'id' => 0,
                'name' => 'No registrada',
                'description' => 'Sin descripción',
                'location' => 'No especificada',
                'website' => 'No disponible',
                'verified' => false,
                'has_logo' => false,
            ],
            'active_jobs' => 0,
            'total_jobs' => 0,
            'total_applications' => 0,
            'avg_rating' => 0,
            // Valores por defecto para admin
            'stats' => [],
            'recent_activity' => [],
            // Valores por defecto para soporte
            'pending_tickets' => 0,
            'recent_tickets' => [],
        ];

        if ($user['role'] === 'talent') {
            try {
                $stmt = $pdo->prepare('SELECT headline, bio, skills, experience_years, location, xp, cv_file FROM talent_profiles WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $profile = $stmt->fetch() ?: [];
                
                $context['profile'] = [
                    'headline' => $profile['headline'] ?? 'No especificado',
                    'bio' => $profile['bio'] ?? 'Sin biografía',
                    'skills' => $profile['skills'] ?? 'No especificadas',
                    'experience_years' => (int)($profile['experience_years'] ?? 0),
                    'location' => $profile['location'] ?? 'No especificada',
                    'xp' => (int)($profile['xp'] ?? 0),
                    'has_cv' => !empty($profile['cv_file']),
                ];
                
                // Contar postulaciones
                $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM applications WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $context['applications_count'] = (int)($stmt->fetch()['total'] ?? 0);
                
                // Contar reseñas - CORREGIDO: siempre existe esta clave
                $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM reviews WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $context['reviews_count'] = (int)($stmt->fetch()['total'] ?? 0);
                
                // Vacantes recomendadas
                $skills = $context['profile']['skills'];
                if ($skills !== 'No especificadas' && !empty(trim($skills))) {
                    $skillArray = array_map('trim', explode(',', $skills));
                    $skillArray = array_filter($skillArray);
                    
                    if (!empty($skillArray)) {
                        $placeholders = implode(',', array_fill(0, count($skillArray), '?'));
                        $sql = "SELECT j.id, j.title, j.description, j.technology, j.modality, j.location, j.salary_min, j.salary_max, c.name as company_name
                                FROM jobs j 
                                LEFT JOIN companies c ON c.id = j.company_id
                                WHERE j.status = 'active' 
                                AND (";
                        $conditions = [];
                        foreach ($skillArray as $i => $skill) {
                            $conditions[] = "j.title LIKE ? OR j.description LIKE ? OR j.technology LIKE ?";
                        }
                        $sql .= implode(' OR ', $conditions) . ") LIMIT 5";
                        
                        $params = [];
                        foreach ($skillArray as $skill) {
                            $search = "%{$skill}%";
                            $params[] = $search;
                            $params[] = $search;
                            $params[] = $search;
                        }
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $context['recommended_jobs'] = $stmt->fetchAll() ?: [];
                    }
                }
            } catch (Throwable $e) {
                // Si hay error, mantener valores por defecto
                error_log("Error en buildUserContext (talent): " . $e->getMessage());
            }
            
        } elseif ($user['role'] === 'company') {
            try {
                $stmt = $pdo->prepare('SELECT id, name, description, location, website, verified, logo FROM companies WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $company = $stmt->fetch() ?: [];
                
                $context['company'] = [
                    'id' => $company['id'] ?? 0,
                    'name' => $company['name'] ?? 'No registrada',
                    'description' => $company['description'] ?? 'Sin descripción',
                    'location' => $company['location'] ?? 'No especificada',
                    'website' => $company['website'] ?? 'No disponible',
                    'verified' => (bool)($company['verified'] ?? false),
                    'has_logo' => !empty($company['logo']),
                ];
                
                if (!empty($company['id'])) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM jobs WHERE company_id = ? AND status = "active"');
                    $stmt->execute([$company['id']]);
                    $context['active_jobs'] = (int)($stmt->fetch()['total'] ?? 0);
                    
                    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM jobs WHERE company_id = ?');
                    $stmt->execute([$company['id']]);
                    $context['total_jobs'] = (int)($stmt->fetch()['total'] ?? 0);
                    
                    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.company_id = ?');
                    $stmt->execute([$company['id']]);
                    $context['total_applications'] = (int)($stmt->fetch()['total'] ?? 0);
                    
                    $stmt = $pdo->prepare('SELECT AVG(rating) as avg_rating FROM reviews WHERE company_id = ?');
                    $stmt->execute([$company['id']]);
                    $context['avg_rating'] = round((float)($stmt->fetch()['avg_rating'] ?? 0), 1);
                }
            } catch (Throwable $e) {
                error_log("Error en buildUserContext (company): " . $e->getMessage());
            }
            
        } elseif ($user['role'] === 'admin') {
            try {
                $context['stats'] = [
                    'total_users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                    'total_talent' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role = "talent"')->fetchColumn(),
                    'total_companies' => (int)$pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
                    'total_jobs' => (int)$pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn(),
                    'total_applications' => (int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
                    'total_reviews' => (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
                    'pending_reviews' => (int)$pdo->query('SELECT COUNT(*) FROM reviews WHERE moderation_status = "pending"')->fetchColumn(),
                    'pending_companies' => (int)$pdo->query('SELECT COUNT(*) FROM companies WHERE verified = 0')->fetchColumn(),
                    'active_jobs' => (int)$pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "active"')->fetchColumn(),
                ];
                $context['recent_activity'] = $pdo->query('SELECT action, created_at, type FROM activity_logs ORDER BY id DESC LIMIT 5')->fetchAll() ?: [];
            } catch (Throwable $e) {
                error_log("Error en buildUserContext (admin): " . $e->getMessage());
            }
            
        } elseif ($user['role'] === 'support') {
            try {
                $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM messages WHERE receiver_id = ?');
                $stmt->execute([$user['id']]);
                $context['pending_tickets'] = (int)($stmt->fetch()['total'] ?? 0);
                
                $stmt = $pdo->prepare('SELECT m.body, m.created_at, u.name as sender_name 
                                       FROM messages m 
                                       JOIN users u ON u.id = m.sender_id 
                                       WHERE m.receiver_id = ? 
                                       ORDER BY m.id DESC LIMIT 5');
                $stmt->execute([$user['id']]);
                $context['recent_tickets'] = $stmt->fetchAll() ?: [];
            } catch (Throwable $e) {
                error_log("Error en buildUserContext (support): " . $e->getMessage());
            }
        }

        return $context;
    }

    private function getSystemPrompt(): string {
        $role = $this->userContext['role'];
        $roleLabel = $this->userContext['role_label'];
        $name = $this->userContext['name'];
        
        $basePrompt = "Eres un asistente virtual experto de OpenJobs, una plataforma de transparencia laboral.
Tu misión es ayudar a los usuarios con información precisa, útil y personalizada según su perfil.

INSTRUCCIONES IMPORTANTES:
1. Responde SIEMPRE en español, de forma clara, amigable y profesional.
2. Sé conciso pero completo - responde directamente a lo que preguntan.
3. Utiliza los datos reales del perfil del usuario para personalizar tus respuestas.
4. NO uses respuestas predeterminadas o genéricas - cada respuesta debe ser única y relevante.
5. Si no sabes algo, indícalo honestamente y sugiere dónde pueden encontrar ayuda.
6. Mantén un tono cálido y cercano pero profesional.

CONTEXTO DEL USUARIO:
- Nombre: {$name}
- Rol: {$roleLabel}
- Fecha/hora: {$this->userContext['current_date']}";

        if ($role === 'talent') {
            $profile = $this->userContext['profile'];
            $basePrompt .= "

DATOS DE TU PERFIL:
- Titular: {$profile['headline']}
- Habilidades: {$profile['skills']}
- Experiencia: {$profile['experience_years']} años
- Ubicación: {$profile['location']}
- Puntos XP: {$profile['xp']}
- CV cargado: " . ($profile['has_cv'] ? 'Sí' : 'No') . "
- Postulaciones: {$this->userContext['applications_count']}
- Reseñas escritas: {$this->userContext['reviews_count']}

Puedo ayudarte con:
- Recomendarte vacantes según tus habilidades
- Mejorar tu perfil y CV
- Consejos para postulaciones
- Cómo aumentar tu reputación en OpenJobs";

        } elseif ($role === 'company') {
            $company = $this->userContext['company'];
            $basePrompt .= "

DATOS DE TU EMPRESA:
- Nombre: {$company['name']}
- Ubicación: {$company['location']}
- Verificada: " . ($company['verified'] ? 'Sí' : 'No') . "
- Vacantes activas: {$this->userContext['active_jobs']}
- Total postulaciones: {$this->userContext['total_applications']}
- Calificación: {$this->userContext['avg_rating']} estrellas

Puedo ayudarte con:
- Mejorar descripciones de vacantes
- Gestionar postulaciones
- Estrategias para mejorar reputación
- Proceso de verificación";

        } elseif ($role === 'admin') {
            $stats = $this->userContext['stats'];
            $basePrompt .= "

ESTADÍSTICAS DEL SISTEMA:
- Usuarios: {$stats['total_users']}
- Empresas: {$stats['total_companies']}
- Vacantes activas: {$stats['active_jobs']}
- Reseñas pendientes: {$stats['pending_reviews']}
- Empresas por verificar: {$stats['pending_companies']}

Puedo ayudarte con:
- Resúmenes ejecutivos
- Prioridades de moderación
- Análisis de actividad
- Recomendaciones operativas";

        } elseif ($role === 'support') {
            $basePrompt .= "

DATOS DE SOPORTE:
- Tickets pendientes: {$this->userContext['pending_tickets']}

Puedo ayudarte con:
- Sugerir respuestas para tickets
- Resolver dudas técnicas
- Guiar usuarios paso a paso";

        }

        return $basePrompt;
    }

    public function sendMessage(string $userMessage): array {
        if (!$this->isReady()) {
            return [
                'ok' => true,
                'response' => $this->getFallbackResponse($userMessage),
                'error' => null,
                'fallback' => true
            ];
        }

        $systemPrompt = $this->getSystemPrompt();
        
        $conversationContext = "";
        if (!empty($this->conversationHistory)) {
            $conversationContext = "\n\nHISTORIAL:\n";
            $lastMessages = array_slice($this->conversationHistory, -6);
            foreach ($lastMessages as $msg) {
                $conversationContext .= "- {$msg['role']}: " . substr($msg['content'], 0, 200) . "\n";
            }
        }

        $fullPrompt = $systemPrompt . $conversationContext . "\n\nUSUARIO: {$userMessage}\n\nASISTENTE:";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.8,
                'maxOutputTokens' => 800,
                'topP' => 0.95,
                'topK' => 40,
            ]
        ];

        $result = $this->makeRequest($payload);

        if (!$result['ok']) {
            return [
                'ok' => true,
                'response' => $this->getFallbackResponse($userMessage),
                'error' => $result['error'],
                'fallback' => true
            ];
        }

        $responseText = $this->extractText($result['data']);
        
        if (!$responseText) {
            return [
                'ok' => true,
                'response' => $this->getFallbackResponse($userMessage),
                'error' => 'No se pudo obtener respuesta',
                'fallback' => true
            ];
        }

        $this->conversationHistory[] = ['role' => 'user', 'content' => $userMessage];
        $this->conversationHistory[] = ['role' => 'assistant', 'content' => $responseText];
        
        if (count($this->conversationHistory) > 30) {
            $this->conversationHistory = array_slice($this->conversationHistory, -30);
        }
        
        $_SESSION['ai_chat_history'] = $this->conversationHistory;

        return [
            'ok' => true,
            'response' => $responseText,
            'context' => $this->userContext,
        ];
    }

    private function getFallbackResponse(string $message): string {
        $role = $this->userContext['role'];
        $name = $this->userContext['name'];
        $messageLower = strtolower($message);
        
        // Respuestas contextuales según el mensaje
        if (str_contains($messageLower, 'vacante') || str_contains($messageLower, 'trabajo') || str_contains($messageLower, 'empleo')) {
            if ($role === 'talent') {
                return "Hola {$name}. Para encontrar vacantes, ve a la sección 'Explorar vacantes' en tu dashboard. Puedes filtrar por tecnología, modalidad y experiencia. También puedes usar el AI Studio para recibir recomendaciones personalizadas según tus habilidades: " . ($this->userContext['profile']['skills'] ?? 'PHP, JavaScript, SQL');
            } elseif ($role === 'company') {
                return "Hola {$name}. Puedes publicar vacantes en la sección 'Vacantes' de tu dashboard. Asegúrate de completar bien la descripción, requisitos y rango salarial para atraer mejores candidatos. ¿Quieres que te ayude a redactar una?";
            }
        }
        
        if (str_contains($messageLower, 'perfil') || str_contains($messageLower, 'cv')) {
            if ($role === 'talent') {
                $skills = $this->userContext['profile']['skills'] ?? 'PHP, JavaScript, SQL';
                return "Hola {$name}. Te recomiendo completar tu perfil con tus habilidades actuales: {$skills}. También puedes subir tu CV en PDF. Un perfil completo tiene más probabilidades de ser encontrado por empresas. ¿Quieres que te ayude a mejorar tu biografía?";
            } elseif ($role === 'company') {
                return "Hola {$name}. Completa la información de tu empresa: descripción, ubicación, sitio web y logo. Esto genera más confianza en los candidatos. Además, verifica tu empresa para obtener el distintivo de confianza.";
            }
        }
        
        if (str_contains($messageLower, 'postulacion') || str_contains($messageLower, 'postular')) {
            if ($role === 'talent') {
                return "Hola {$name}. Puedes postularte a vacantes desde la página de cada vacante. En tu dashboard verás el seguimiento de todas tus postulaciones (actualmente tienes {$this->userContext['applications_count']}). También recibirás notificaciones cuando el estado cambie.";
            } elseif ($role === 'company') {
                return "Hola {$name}. Revisa las postulaciones en la sección 'Vacantes' de tu dashboard. Actualmente tienes {$this->userContext['total_applications']} postulaciones para revisar. Allí puedes ver los candidatos y actualizar el estado.";
            }
        }
        
        // Respuesta genérica según rol
        $responses = [
            'talent' => "Hola {$name}! Soy tu asistente de OpenJobs. Puedo ayudarte a encontrar vacantes según tus habilidades, mejorar tu perfil, analizar tu CV, o responder dudas sobre la plataforma. ¿En qué puedo ayudarte hoy?",
            'company' => "Hola {$name}! Soy tu asistente de OpenJobs. Puedo ayudarte a escribir mejores vacantes, gestionar postulaciones, mejorar tu perfil de empresa, o resolver dudas sobre la plataforma. ¿Qué necesitas?",
            'admin' => "Hola Admin! Soy tu asistente de OpenJobs. Puedo ayudarte con resúmenes del sistema, sugerencias de moderación, reportes de actividad, o responder preguntas sobre la administración. ¿En qué puedo ayudarte?",
            'support' => "Hola {$name}! Soy tu asistente de soporte. Puedo ayudarte a redactar respuestas, resolver dudas técnicas, o guiarte en problemas comunes. ¿Qué consulta tienes?",
        ];
        
        return $responses[$role] ?? "Hola {$name}! Soy el asistente de OpenJobs. ¿En qué puedo ayudarte hoy?";
    }

    public function getContext(): array {
        return $this->userContext;
    }

    public function isReady(): bool {
        return $this->apiKey !== '' && $this->apiKey !== 'TU_API_KEY_GEMINI_AQUI' && function_exists('curl_init');
    }

    private function makeRequest(array $payload): array {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->apiKey;
        
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'Error de conexión: ' . $error,
            ];
        }

        $json = json_decode($response, true);
        
        if ($code < 200 || $code >= 300) {
            $errorMsg = is_array($json) ? ($json['error']['message'] ?? 'HTTP ' . $code) : 'HTTP ' . $code;
            return [
                'ok' => false,
                'data' => $json,
                'error' => $errorMsg,
            ];
        }

        return [
            'ok' => true,
            'data' => $json,
            'error' => null,
        ];
    }

    private function extractText(array $json): ?string {
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }
        return null;
    }
}