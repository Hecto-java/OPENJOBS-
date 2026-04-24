<?php
declare(strict_types=1);

class GeminiService {
    private string $apiKey;
    private string $model;

    public function __construct() {
        $this->apiKey = defined('GEMINI_API_KEY') ? (string) GEMINI_API_KEY : '';
        $this->model = defined('GEMINI_MODEL') ? (string) GEMINI_MODEL : 'gemini-1.5-flash';
    }

    public function isReady(): bool {
        return $this->apiKey !== '' && $this->apiKey !== 'TU_API_KEY_GEMINI_AQUI' && function_exists('curl_init');
    }

    public static function isConfigured(): bool {
        $self = new self();
        return $self->isReady();
    }

    public function generateText(string $instructions, string $input): array {
        if (!$this->isReady()) {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'Gemini no está configurado',
            ];
        }

        $fullPrompt = $instructions . "\n\n" . $input;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1800,
                'topP' => 0.95,
            ]
        ];

        $result = $this->makeRequest($payload);

        if (!$result['ok']) {
            return [
                'ok' => false,
                'text' => null,
                'error' => $result['error'],
            ];
        }

        $text = $this->extractText($result['data']);
        
        if (!$text) {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'No se pudo obtener respuesta',
            ];
        }

        return [
            'ok' => true,
            'text' => $text,
            'raw' => $result['data'],
        ];
    }

    public function generateJson(string $input, string $instructions, array $schema): ?array {
        if (!$this->isReady()) {
            return null;
        }

        $fullPrompt = $instructions . "\n\n" . $input . "\n\nDevuelve SOLO JSON válido, sin texto adicional.\n\nEsquema esperado:\n" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1800,
                'topP' => 0.95,
                'responseMimeType' => 'application/json',
            ]
        ];

        $result = $this->makeRequest($payload);
        
        if (!$result['ok']) {
            return null;
        }

        $text = $this->extractText($result['data']);
        if (!$text) {
            return null;
        }

        // Limpiar posibles marcadores de código
        $text = preg_replace('/^```json\s*|\s*```$/i', '', trim($text));
        
        $parsed = json_decode(trim($text), true);
        return is_array($parsed) ? $parsed : null;
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

    // Métodos estáticos para compatibilidad con servicios existentes
    public static function request(string $prompt, ?string $instructions = null): ?string {
        $service = new self();
        $result = $service->generateText($instructions ?: 'Responde en español de forma útil para OpenJobs.', $prompt);
        return $result['ok'] ? $result['text'] : null;
    }

    public static function requestJson(string $input, string $instructions, array $schema): ?array {
        $service = new self();
        return $service->generateJson($input, $instructions, $schema);
    }

    public static function recommendJobs(array $user, array $jobs): ?array {
        $schema = [
            'type' => 'object',
            'properties' => [
                'matches' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'reason' => ['type' => 'string'],
                            'fit' => ['type' => 'string', 'enum' => ['alto', 'medio', 'bajo']],
                        ],
                        'required' => ['id', 'score', 'reason', 'fit'],
                    ],
                ],
                'summary' => ['type' => 'string'],
            ],
            'required' => ['matches', 'summary'],
        ];

        $input = 'Perfil: ' . json_encode($user, JSON_UNESCAPED_UNICODE) . "\nVacantes: " . json_encode($jobs, JSON_UNESCAPED_UNICODE);
        $instructions = 'Eres un motor de matching. Analiza el perfil y las vacantes. Devuelve JSON con matches y summary.';
        
        return self::requestJson($input, $instructions, $schema);
    }

    public static function analyzeCv(string $candidateName, string $cvText, string $targetRole = ''): ?array {
        $schema = [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                'gaps' => ['type' => 'array', 'items' => ['type' => 'string']],
                'suggested_roles' => ['type' => 'array', 'items' => ['type' => 'string']],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            ],
            'required' => ['summary', 'strengths', 'gaps', 'suggested_roles', 'keywords', 'score'],
        ];

        $snippet = mb_substr(trim($cvText), 0, 8000, 'UTF-8');
        $input = "Candidato: {$candidateName}\nRol objetivo: {$targetRole}\nCV: {$snippet}";
        $instructions = 'Analiza el CV. Resume, identifica fortalezas, vacíos, roles sugeridos, keywords y puntuación (0-100).';
        
        return self::requestJson($input, $instructions, $schema);
    }

    public static function classifyReview(string $comment): array {
        if (!self::isConfigured()) {
            return self::fallbackReviewClassification($comment);
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['approved', 'pending']],
                'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'reason' => ['type' => 'string'],
            ],
            'required' => ['status', 'score', 'reason'],
        ];

        $instructions = 'Eres moderador. Evalúa si esta reseña es auténtica y útil. Devuelve JSON.';
        
        $parsed = self::requestJson($comment, $instructions, $schema);
        
        if (!is_array($parsed)) {
            return self::fallbackReviewClassification($comment);
        }

        return [
            'status' => ($parsed['status'] ?? 'pending') === 'approved' ? 'approved' : 'pending',
            'score' => max(0, min(100, (int)($parsed['score'] ?? 0))),
            'reason' => trim((string)($parsed['reason'] ?? 'Revisión automática.')),
        ];
    }

    public static function buildDashboardInsight(string $role, array $stats, string $context = ''): ?string {
        $prompt = "Rol: {$role}. Estadísticas: " . json_encode($stats, JSON_UNESCAPED_UNICODE) . ". Contexto: {$context}. Genera 3 insights breves en español.";
        return self::request($prompt, 'Eres analista de producto para OpenJobs.');
    }

    private static function fallbackReviewClassification(string $comment): array {
        $normalized = mb_strtolower(trim($comment), 'UTF-8');
        $length = mb_strlen($normalized, 'UTF-8');
        $score = 82;

        if ($length < 35) $score -= 40;
        
        $patterns = ['excelente excelente', 'la mejor empresa', 'odio todo', 'horrible'];
        foreach ($patterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                $score -= 35;
                break;
            }
        }
        
        return [
            'status' => $score >= 55 ? 'approved' : 'pending',
            'score' => max(0, min(100, $score)),
            'reason' => $score >= 55 ? 'Parece una reseña útil.' : 'La reseña es muy corta o contiene patrones sospechosos.',
        ];
    }
}