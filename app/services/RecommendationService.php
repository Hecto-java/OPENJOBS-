<?php
declare(strict_types=1);
require_once __DIR__ . '/GeminiService.php';
require_once __DIR__ . '/../../config/database.php';

class RecommendationService {
    
    /**
     * Recomienda vacantes para un usuario
     */
    public static function recommend(int|array $userOrId, ?array $jobs = null): array {
        if (is_int($userOrId)) {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT u.id, u.name, tp.skills, tp.experience_years, tp.location, tp.bio, tp.headline
                FROM users u
                LEFT JOIN talent_profiles tp ON tp.user_id = u.id
                WHERE u.id = ? LIMIT 1');
            $stmt->execute([$userOrId]);
            $user = $stmt->fetch() ?: [];
            if (!$user) return [];
            if ($jobs === null) {
                $jobs = $pdo->query('SELECT j.id, j.title, j.description, j.technology, j.modality, j.experience_required, j.location, c.name as company_name
                    FROM jobs j 
                    LEFT JOIN companies c ON c.id = j.company_id
                    WHERE j.status = "active" 
                    ORDER BY j.id DESC')->fetchAll();
            }
        } else {
            $user = $userOrId;
            $jobs ??= [];
        }

        if (!$user || !$jobs) return [];

        // Intentar con Gemini AI
        $parsed = GeminiService::recommendJobs($user, $jobs);
        if (is_array($parsed['matches'] ?? null)) {
            $byId = [];
            foreach ($parsed['matches'] as $row) {
                if (isset($row['id'])) {
                    $byId[(int)$row['id']] = [
                        'id' => (int)$row['id'],
                        'title' => self::getJobTitleById((int)$row['id'], $jobs),
                        'score' => (int)($row['score'] ?? 0),
                        'reason' => (string)($row['reason'] ?? 'Coincide con tu perfil.'),
                        'fit' => (string)($row['fit'] ?? 'medio'),
                        'location' => self::getJobLocationById((int)$row['id'], $jobs),
                        'modality' => self::getJobModalityById((int)$row['id'], $jobs),
                    ];
                }
            }
            if ($byId) return $byId;
        }

        // Fallback manual
        return self::manualRecommendation($user, $jobs);
    }
    
    /**
     * Recomendación manual como fallback
     */
    private static function manualRecommendation(array $user, array $jobs): array {
        $matches = [];
        $skills = strtolower((string)($user['skills'] ?? ''));
        $location = strtolower((string)($user['location'] ?? ''));
        $experience = (int)($user['experience_years'] ?? 0);
        $skillList = array_filter(array_map('trim', explode(',', $skills)));

        foreach ($jobs as $job) {
            $score = 45;
            $haystack = strtolower(($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['technology'] ?? ''));
            
            // Puntos por skills
            foreach ($skillList as $skill) {
                if ($skill !== '' && str_contains($haystack, strtolower($skill))) {
                    $score += 9;
                }
            }
            
            // Puntos por ubicación
            if ($location !== '' && str_contains(strtolower((string)($job['location'] ?? '')), $location)) {
                $score += 8;
            }
            
            // Puntos por modalidad remota
            if (strtolower((string)($job['modality'] ?? '')) === 'remoto') {
                $score += 4;
            }
            
            // Puntos por experiencia
            $req = strtolower((string)($job['experience_required'] ?? 'mid'));
            if (($experience <= 1 && $req === 'junior') || 
                ($experience >= 2 && $experience <= 4 && $req === 'mid') || 
                ($experience >= 5 && $req === 'senior')) {
                $score += 10;
            }
            
            $score = min(100, $score);
            
            $matches[(int)$job['id']] = [
                'id' => (int)$job['id'],
                'title' => $job['title'] ?? 'Vacante',
                'score' => $score,
                'reason' => self::getRecommendationReason($score, $skillList, $haystack),
                'fit' => $score > 70 ? 'alto' : ($score > 40 ? 'medio' : 'bajo'),
                'location' => $job['location'] ?? 'No especificada',
                'modality' => $job['modality'] ?? 'Híbrido',
            ];
        }

        uasort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        return $matches;
    }
    
    private static function getRecommendationReason(int $score, array $skills, string $haystack): string {
        if ($score >= 80) {
            return "Excelente coincidencia con tus habilidades y perfil.";
        } elseif ($score >= 60) {
            return "Buena coincidencia, tus habilidades son relevantes.";
        } elseif ($score >= 40) {
            return "Coincidencia parcial, revisa los requisitos.";
        } else {
            return "Coincidencia baja, considera mejorar tu perfil.";
        }
    }
    
    private static function getJobTitleById(int $id, array $jobs): string {
        foreach ($jobs as $job) {
            if ((int)$job['id'] === $id) {
                return $job['title'] ?? 'Vacante';
            }
        }
        return 'Vacante';
    }
    
    private static function getJobLocationById(int $id, array $jobs): string {
        foreach ($jobs as $job) {
            if ((int)$job['id'] === $id) {
                return $job['location'] ?? 'No especificada';
            }
        }
        return 'No especificada';
    }
    
    private static function getJobModalityById(int $id, array $jobs): string {
        foreach ($jobs as $job) {
            if ((int)$job['id'] === $id) {
                return $job['modality'] ?? 'Híbrido';
            }
        }
        return 'Híbrido';
    }
}