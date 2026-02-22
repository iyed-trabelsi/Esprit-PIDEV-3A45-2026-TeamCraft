<?php

namespace App\Service\Calculator;

use App\Dto\GamerPerformanceDto;

class LolPerformanceCalculator implements GamePerformanceCalculatorInterface
{
    public function calculate(array $matches, array $rankData = []): GamerPerformanceDto
    {
        $count = count($matches);
        if ($count === 0) {
            return $this->createEmptyDto();
        }

        $wins = 0;
        $kills = 0;
        $deaths = 0;
        $assists = 0;
        $totalCs = 0;
        $totalDamage = 0;
        $totalGold = 0;
        $totalVision = 0;
        $champions = [];

        foreach ($matches as $m) {
            if ($m['win'])
                $wins++;
            $kills += $m['kills'] ?? 0;
            $deaths += $m['deaths'] ?? 0;
            $assists += $m['assists'] ?? 0;
            $totalCs += $m['csPerMin'] ?? 0;
            $totalDamage += $m['totalDamage'] ?? 0;
            $totalGold += $m['goldPerMin'] ?? 0;
            $totalVision += $m['visionScore'] ?? 0;

            $champ = $m['championName'] ?? 'Unknown';
            if (!isset($champions[$champ])) {
                $champions[$champ] = ['games' => 0, 'wins' => 0];
            }
            $champions[$champ]['games']++;
            if ($m['win'])
                $champions[$champ]['wins']++;
        }

        $winRate = ($wins / $count) * 100;
        $avgKda = ($kills + $assists) / max(1, $deaths);
        $avgCs = $totalCs / $count;

        // --- Normalization ---
        $normalizedKda = min($avgKda, 5);
        $normalizedCs = min($avgCs, 8);

        // --- Scoring (LoL Score) ---
        // Score = (Winrate * 0.4) + ((NormalizedKda * 20) * 0.3) + ((NormalizedCs * 10) * 0.3)
        $score = ($winRate * 0.4) + (($normalizedKda * 20) * 0.3) + (($normalizedCs * 10) * 0.3);

        // --- Classification (Overall Rating) ---
        $overallRating = $this->determineRating($winRate);

        uasort($champions, fn($a, $b) => $b['games'] <=> $a['games']);
        $mostPlayedChamp = array_key_first($champions);
        $mostPlayedData = [
            'name' => $mostPlayedChamp,
            'games' => $champions[$mostPlayedChamp]['games'],
            'winRate' => ($champions[$mostPlayedChamp]['wins'] / $champions[$mostPlayedChamp]['games']) * 100
        ];

        return new GamerPerformanceDto(
            game: 'lol',
            matchCount: $count,
            wins: $wins,
            losses: $count - $wins,
            winRate: round($winRate, 2),
            avgKills: round($kills / $count, 2),
            avgDeaths: round($deaths / $count, 2),
            avgAssists: round($assists / $count, 2),
            avgKda: round($avgKda, 2),
            mostPlayed: $mostPlayedData,
            performanceIndicator: $overallRating, // Harmonized with overallRating for LoL
            scoutingSummary: $this->generateSummary($winRate, $avgKda, $avgCs, $mostPlayedChamp),
            performanceScore: round($score, 2),
            overallRating: $overallRating,
            normalizedKda: round($normalizedKda, 2),
            normalizedMetrics: [
                'normalizedCs' => round($normalizedCs, 2)
            ],
            advancedMetrics: [
                'avgCsMin' => round($avgCs, 2),
                'avgDamage' => round($totalDamage / $count),
                'avgGoldMin' => round($totalGold / $count),
                'avgVision' => round($totalVision / $count, 1),
            ],
            recentMatches: $matches
        );
    }

    private function determineRating(float $winRate): string
    {
        if ($winRate >= 65)
            return 'ELITE';
        if ($winRate >= 55)
            return 'STRONG';
        if ($winRate >= 45)
            return 'DEVELOPING';
        return 'INCONSISTENT';
    }

    private function generateSummary(float $winRate, float $kda, float $cs, string $champ): string
    {
        $style = ($kda > 3.5) ? "agressif et dominant" : "calculé et stable";
        $highlights = [];
        if ($winRate >= 60)
            $highlights[] = "un excellent taux de victoire (" . round($winRate) . "%)";
        if ($cs >= 7.5)
            $highlights[] = "un farming de précision supérieure (" . round($cs, 1) . " CS/min)";

        $summary = "Joueur LoL avec un style $style. ";
        if (!empty($highlights)) {
            $summary .= "Affiche " . implode(" et ", $highlights) . ". ";
        }
        $summary .= "Champion de prédilection : $champ.";

        return $summary;
    }

    private function createEmptyDto(): GamerPerformanceDto
    {
        return new GamerPerformanceDto(
            'lol',
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
            'Données insuffisantes.'
        );
    }
}
