<?php
declare(strict_types=1);
require_once __DIR__ . '/GeminiService.php';

class CVAnalysisService {
    
    /**
     * Extrae texto de un archivo PDF
     */
    public static function extractTextFromPdf(string $absolutePath): string {
        if (!is_file($absolutePath)) {
            return '';
        }

        // Intentar con pdftotext si está disponible
        $cmd = trim((string)@shell_exec('command -v pdftotext 2>/dev/null'));
        if ($cmd !== '') {
            $tmp = tempnam(sys_get_temp_dir(), 'ojcv_');
            if ($tmp) {
                @shell_exec($cmd . ' -layout ' . escapeshellarg($absolutePath) . ' ' . escapeshellarg($tmp));
                $text = @file_get_contents($tmp) ?: '';
                @unlink($tmp);
                $text = trim(preg_replace('/\s+/u', ' ', $text));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        // Fallback: extraer texto simple del binario
        $raw = @file_get_contents($absolutePath) ?: '';
        if ($raw === '') {
            return '';
        }
        // Limpiar caracteres no imprimibles
        $raw = preg_replace('/[^\PC\s]/u', ' ', $raw) ?? '';
        $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';
        return trim($raw);
    }

    /**
     * Analiza el CV del usuario actual
     */
    public static function analyzeCurrentUserCv(array $user, ?array $profile = null): ?array {
        $cvRelative = $profile['cv_file'] ?? null;
        if (!$cvRelative) {
            return null;
        }

        $absolute = dirname(__DIR__, 2) . '/public/' . ltrim((string)$cvRelative, '/');
        $text = self::extractTextFromPdf($absolute);
        
        if ($text === '') {
            return [
                'summary' => 'No se pudo extraer texto del CV. Asegúrate de que el archivo sea un PDF válido.',
                'strengths' => ['Sube un CV en formato PDF válido'],
                'gaps' => ['No se pudo procesar el archivo'],
                'suggested_roles' => [],
                'keywords' => [],
                'score' => 0,
                'error' => 'No se pudo leer el archivo PDF'
            ];
        }

        $targetRole = trim((string)($profile['headline'] ?? ''));
        
        // Llamar a GeminiService para analizar el CV
        $analysis = GeminiService::analyzeCv(
            (string)($user['name'] ?? 'Talento OpenJobs'), 
            $text, 
            $targetRole
        );
        
        // Si Gemini no devolvió análisis válido, usar análisis básico
        if (!$analysis || !is_array($analysis)) {
            return self::basicAnalysis($text, $targetRole);
        }
        
        // Asegurar que todas las claves existan
        return [
            'summary' => $analysis['summary'] ?? 'Análisis completado.',
            'strengths' => $analysis['strengths'] ?? ['Perfil con potencial'],
            'gaps' => $analysis['gaps'] ?? ['Completar más detalles'],
            'suggested_roles' => $analysis['suggested_roles'] ?? ['Revisar perfil'],
            'keywords' => $analysis['keywords'] ?? [],
            'score' => $analysis['score'] ?? 50,
        ];
    }

    /**
     * Análisis básico cuando Gemini no está disponible
     */
    public static function basicAnalysis(string $text, string $targetRole): array {
        $textLower = strtolower($text);
        
        // Detectar palabras clave comunes
        $commonSkills = [
            'php', 'javascript', 'python', 'java', 'react', 'vue', 'angular', 
            'laravel', 'django', 'sql', 'mysql', 'mongodb', 'node', 'html', 'css',
            'ux', 'ui', 'design', 'marketing', 'ventas', 'gerente', 'lider'
        ];
        
        $foundKeywords = [];
        foreach ($commonSkills as $skill) {
            if (str_contains($textLower, $skill)) {
                $foundKeywords[] = $skill;
            }
        }
        
        // Calcular score básico basado en cantidad de palabras y keywords
        $wordCount = str_word_count($text);
        $score = min(100, 30 + count($foundKeywords) * 5 + min(20, floor($wordCount / 50)));
        
        $strengths = [];
        if (!empty($foundKeywords)) {
            $strengths[] = 'Tiene experiencia en: ' . implode(', ', array_slice($foundKeywords, 0, 5));
        }
        if ($wordCount > 200) {
            $strengths[] = 'CV con buena cantidad de detalles';
        }
        if (count($foundKeywords) < 3) {
            $strengths[] = 'Podrías agregar más habilidades específicas';
        }
        
        $gaps = [];
        if (count($foundKeywords) < 5) {
            $gaps[] = 'Agrega más habilidades técnicas relevantes';
        }
        if ($wordCount < 150) {
            $gaps[] = 'Amplía la descripción de tu experiencia';
        }
        if (empty($targetRole)) {
            $gaps[] = 'Define un titular profesional claro';
        }
        
        $suggestedRoles = [];
        if (in_array('php', $foundKeywords) || in_array('laravel', $foundKeywords)) {
            $suggestedRoles[] = 'Desarrollador PHP';
        }
        if (in_array('javascript', $foundKeywords) || in_array('react', $foundKeywords)) {
            $suggestedRoles[] = 'Desarrollador Frontend';
        }
        if (in_array('python', $foundKeywords)) {
            $suggestedRoles[] = 'Desarrollador Python';
        }
        if (empty($suggestedRoles)) {
            $suggestedRoles[] = 'Revisar perfil para identificar roles';
        }
        
        return [
            'summary' => "Se analizó el CV. Se detectaron " . count($foundKeywords) . " habilidades clave. " . ($score > 70 ? "El perfil tiene buen potencial." : "Se recomienda mejorar la descripción de habilidades."),
            'strengths' => $strengths,
            'gaps' => $gaps,
            'suggested_roles' => $suggestedRoles,
            'keywords' => $foundKeywords,
            'score' => $score,
        ];
    }
}