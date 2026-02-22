<?php

namespace App\Service\Calculator;

use App\Dto\GamerPerformanceDto;

class Cs2PerformanceCalculator implements GamePerformanceCalculatorInterface
{
    public function calculate(array $matches, array $rankData = []): GamerPerformanceDto
    {
        // For CS2, we expect stats in rankData if matches is empty (since it's aggregate data)
        $stats = $rankData['raw_stats'] ?? null;
        if (!$stats) {
            return $this->createEmptyDto();
        }

        $totalMatches = $stats['totalMatches'] ?? 1;
        $wins = $stats['wins'] ?? 0;
        $losses = $stats['losses'] ?? 0;
        $kills = $stats['kills'] ?? 0;
        $deaths = $stats['deaths'] ?? 1;
        $hsCount = $stats['headshots'] ?? 0;

        $winRate = ($wins / max(1, $totalMatches)) * 100;
        $kd = $kills / max(1, $deaths);
        $hsPercent = ($kills > 0) ? ($hsCount / $kills) * 100 : 0;

        $avgKills = $kills / max(1, $totalMatches);
        $avgDeaths = $deaths / max(1, $totalMatches);

        // --- Impact Score ---
        // Impact = (K/D * 0.6) + (HS% * 0.4)
        // Normalize HS for impact: 0.4 means 40% HS is 100% impact contribution
        $normalizedHsForImpact = min($hsPercent / 40, 1.2);
        $impactScore = ($kd * 0.6) + ($normalizedHsForImpact * 0.4);

        // --- Scoring (CS2 Score) ---
        // Score = (Winrate * 0.3) + (K/D * 40 * 0.4) + (HS% * 0.3)
        $score = ($winRate * 0.3) + ($kd * 40 * 0.4) + ($hsPercent * 0.3);

        // --- Overall Rating ---
        $overallRating = $this->determineOverallRating($score);

        // --- Classification (Performance Indicator) ---
        $indicator = $this->determineIndicator($kd);

        return new GamerPerformanceDto(
            game: 'cs2',
            matchCount: $totalMatches,
            wins: $wins,
            losses: $losses,
            winRate: round($winRate, 2),
            avgKills: round($avgKills, 2),
            avgDeaths: round($avgDeaths, 2),
            avgAssists: round(($stats['assists'] ?? 0) / max(1, $totalMatches), 2),
            avgKda: round($kd, 2),
            mostPlayed: ['name' => 'Tactical Rifleman'], // Default for CS2
            performanceIndicator: $indicator,
            scoutingSummary: $this->generateSummary($kd, $hsPercent, $winRate, $impactScore),
            performanceScore: round($score, 2),
            overallRating: $overallRating,
            normalizedKda: round(min($kd, 2.0), 2),
            normalizedMetrics: [
                'impactScore' => round($impactScore, 2),
                'hsPercent' => round($hsPercent, 2)
            ],
            advancedMetrics: [
                'hsPercent' => round($hsPercent, 2),
                'impactScore' => round($impactScore, 2)
            ],
            recentMatches: []
        );
    }

    private function determineIndicator(float $kd): string
    {
        if ($kd >= 1.2)
            return 'High Impact';
        if ($kd >= 1.0)
            return 'Stable Performer';
        if ($kd >= 0.85)
            return 'Developing';
        return 'Needs Improvement';
    }

    private function determineOverallRating(float $score): string
    {
        if ($score >= 70)
            return 'ELITE';
        if ($score >= 55)
            return 'STRONG';
        if ($score >= 40)
            return 'DEVELOPING';
        return 'INCONSISTENT';
    }

    private function generateSummary(float $kd, float $hs, float $wr, float $impact): string
    {
        return sprintf(
            "Analyse CS2 : K/D de %.2f avec %.1f%% de tirs à la tête. Winrate global de %.1f%%. Score d'impact calculé à %.2f.",
            $kd,
            $hs,
            $wr,
            $impact
        );
    }

    private function createEmptyDto(): GamerPerformanceDto
    {
        return new GamerPerformanceDto(
            'cs2',
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            ['name' => 'N/A', 'games' => 0, 'winRate' => 0],
            'N/A',
            'Données Steam manquantes.'
        );
    }
}
