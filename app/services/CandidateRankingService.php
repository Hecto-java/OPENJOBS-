<?php
declare(strict_types=1);
require_once __DIR__ . '/GeminiService.php';

class CandidateRankingService {
    
    /**
     * Rankea candidatos para una vacante
     */
    public static function rank(array $job, array $candidates): array {
        if (!$candidates) return [];
        
        $schema = [
            'type' => 'object',
            'properties' => [
                'ranking' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'candidate_id' => ['type' => 'integer'],
                            'score' => ['type' => 'integer'],
                            'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'summary' => ['type' => 'string'],
                        ],
                        'required' => ['candidate_id', 'score', 'strengths', 'risks', 'summary'],
                    ],
                ],
            ],
            'required' => ['ranking'],
        ];
        
        // Preparar datos para Gemini
        $jobData = [
            'title' => $job['title'] ?? '',
            'description' => $job['description'] ?? '',
            'technology' => $job['technology'] ?? '',
            'experience_required' => $job['experience_required'] ?? 'Mid',
        ];
        
        $candidatesData = [];
        foreach ($candidates as $c) {
            $candidatesData[] = [
                'id' => $c['id'] ?? 0,
                'name' => $c['name'] ?? 'Candidato',
                'skills' => $c['skills'] ?? '',
                'experience_years' => $c['experience_years'] ?? 0,
                'xp' => $c['xp'] ?? 0,
                'headline' => $c['headline'] ?? '',
            ];
        }
        
        $input = "Vacante: " . json_encode($jobData, JSON_UNESCAPED_UNICODE) . "\nCandidatos: " . json_encode($candidatesData, JSON_UNESCAPED_UNICODE);
        $instructions = 'Eres un reclutador senior para OpenJobs. Analiza la vacante y los candidatos. Devuelve JSON con ranking. score de 0 a 100. Sé objetivo y basado en skills y experiencia.';
        
        $parsed = GeminiService::requestJson($input, $instructions, $schema);
        
        if (is_array($parsed['ranking'] ?? null)) {
            return $parsed['ranking'];
        }
        
        // Fallback manual si Gemini no responde
        return self::manualRanking($job, $candidates);
    }
    
    /**
     * Ranking manual como fallback
     */
    private static function manualRanking(array $job, array $candidates): array {
        $jobTech = strtolower($job['technology'] ?? '');
        $jobTitle = strtolower($job['title'] ?? '');
        
        $ranked = [];
        foreach ($candidates as $c) {
            $score = 40;
            $skills = strtolower((string)($c['skills'] ?? ''));
            
            // Puntos por experiencia
            $score += min((int)($c['xp'] ?? 0) / 10, 20);
            $score += min((int)($c['experience_years'] ?? 0) * 5, 20);
            
            // Puntos por skills
            if ($jobTech !== '') {
                $techTerms = explode(',', $jobTech);
                foreach ($techTerms as $term) {
                    $term = trim($term);
                    if ($term !== '' && str_contains($skills, strtolower($term))) {
                        $score += 8;
                    }
                }
            }
            
            // Puntos por título similar
            $headline = strtolower((string)($c['headline'] ?? ''));
            if (str_contains($headline, $jobTitle) || str_contains($jobTitle, $headline)) {
                $score += 10;
            }
            
            $score = min(100, $score);
            
            $ranked[] = [
                'candidate_id' => $c['id'] ?? 0,
                'score' => $score,
                'strengths' => self::getStrengths($c, $score),
                'risks' => self::getRisks($c, $score),
                'summary' => self::getSummary($c, $score),
            ];
        }
        
        usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
        return $ranked;
    }
    
    private static function getStrengths(array $candidate, int $score): array {
        $strengths = [];
        if (!empty($candidate['skills'] ?? '')) {
            $strengths[] = 'Habilidades: ' . mb_strimwidth($candidate['skills'], 0, 50, '...');
        }
        if (($candidate['experience_years'] ?? 0) > 2) {
            $strengths[] = ($candidate['experience_years'] ?? 0) . ' años de experiencia';
        }
        if (($candidate['xp'] ?? 0) > 500) {
            $strengths[] = 'Alta actividad en OpenJobs';
        }
        if ($score > 70) {
            $strengths[] = 'Buen encaje con la vacante';
        }
        if (empty($strengths)) {
            $strengths[] = 'Perfil con potencial';
        }
        return $strengths;
    }
    
    private static function getRisks(array $candidate, int $score): array {
        $risks = [];
        if (empty($candidate['skills'] ?? '')) {
            $risks[] = 'Habilidades no especificadas';
        }
        if (($candidate['experience_years'] ?? 0) < 1) {
            $risks[] = 'Poca experiencia profesional';
        }
        if ($score < 50) {
            $risks[] = 'Validar ajuste en entrevista técnica';
        }
        if (empty($risks)) {
            $risks[] = 'Validar disponibilidad e interés';
        }
        return $risks;
    }
    
    private static function getSummary(array $candidate, int $score): string {
        $name = $candidate['name'] ?? 'Candidato';
        if ($score >= 80) {
            return "$name es un candidato muy compatible con la vacante.";
        } elseif ($score >= 60) {
            return "$name tiene buen potencial, validar en entrevista.";
        } elseif ($score >= 40) {
            return "$name cumple algunos requisitos, revisar con más detalle.";
        } else {
            return "$name tiene poca coincidencia con la vacante.";
        }
    }
}