<?php

namespace App\Service\Calculator;

use App\Dto\GamerPerformanceDto;

class ValorantPerformanceCalculator implements GamePerformanceCalculatorInterface
{
    /**
     * @param array<int, array<string, mixed>> $matches
     * @param array<string, mixed> $rankData
     */
    /**
     * @param array<int, array<string, mixed>> $matches
     * @param array<string, mixed> $rankData
     */
    /**
     * @param array<int, array<string, mixed>> $matches
     * @param array<string, mixed> $rankData
     */
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
        $totalHs = 0;
        $totalAcs = 0;
        $agents = [];

        foreach ($matches as $m) {
            if ($m['win'])
                $wins++;
            $kills += $m['kills'] ?? 0;
            $deaths += $m['deaths'] ?? 0;
            $assists += $m['assists'] ?? 0;
            $totalHs += $m['hsPercent'] ?? 0;
            $totalAcs += $m['acs'] ?? 0;

            $agent = $m['agent'] ?? 'Unknown';
            if (!isset($agents[$agent])) {
                $agents[$agent] = ['games' => 0, 'wins' => 0];
            }
            $agents[$agent]['games']++;
            if ($m['win'])
                $agents[$agent]['wins']++;
        }

        $winRate = ($wins / $count) * 100;
        $kda = ($kills + $assists) / max(1, $deaths);
        $avgHs = $totalHs / $count;
        $avgAcs = $totalAcs / $count;

        // --- Normalization ---
        $normalizedKda = min($kda, 4);
        $normalizedHs = min($avgHs, 40);

        // --- Scoring (Valorant Score) ---
        // Score = (Winrate * 0.4) + ((NormalizedKda * 25) * 0.3) + ((NormalizedHs * 2) * 0.3)
        $score = ($winRate * 0.4) + (($normalizedKda * 25) * 0.3) + (($normalizedHs * 2) * 0.3);

        // --- Overall Rating ---
        $overallRating = $this->determineOverallRating($score);

        uasort($agents, fn($a, $b) => $b['games'] <=> $a['games']);
        $mostPlayedAgent = array_key_first($agents);
        $mostPlayedData = [
            'name' => $mostPlayedAgent,
            'games' => $agents[$mostPlayedAgent]['games'],
            'winRate' => ($agents[$mostPlayedAgent]['wins'] / $agents[$mostPlayedAgent]['games']) * 100
        ];

        return new GamerPerformanceDto(
            game: 'valorant',
            matchCount: $count,
            wins: $wins,
            losses: $count - $wins,
            winRate: round($winRate, 2),
            avgKills: round($kills / $count, 2),
            avgDeaths: round($deaths / $count, 2),
            avgAssists: round($assists / $count, 2),
            avgKda: round($kda, 2),
            mostPlayed: $mostPlayedAgent ? $mostPlayedData : ['name' => 'N/A'],
            performanceIndicator: $overallRating,
            scoutingSummary: $this->generateSummary($score, $kda, $avgHs, $mostPlayedAgent),
            performanceScore: round($score, 2),
            overallRating: $overallRating,
            normalizedKda: round($normalizedKda, 2),
            normalizedMetrics: [
                'normalizedHs' => round($normalizedHs, 2)
            ],
            advancedMetrics: [
                'avgAcs' => round($avgAcs),
                'avgHsPercent' => round($avgHs, 2)
            ],
            recentMatches: $matches
        );
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

    private function generateSummary(float $score, float $kda, float $hs, string $agent): string
    {
        $roleInfo = $this->guessRole($agent);
        $impact = ($score >= 60) ? "une présence décisive" : "un apport constant";

        $summary = "Expert Valorant opérant principalement sur $agent ($roleInfo). ";
        $summary .= "Démontre $impact avec un KDA de " . round($kda, 2);
        if ($hs >= 20) {
            $summary .= " et une précision chirurgicale (" . round($hs, 1) . "% HS).";
        } else {
            $summary .= ".";
        }

        return $summary;
    }

    private function guessRole(string $agent): string
    {
        $roles = [
            'Duelist' => ['Jett', 'Phoenix', 'Raze', 'Reyna', 'Yoru', 'Neon', 'Iso'],
            'Controller' => ['Brimstone', 'Omen', 'Viper', 'Astra', 'Harbor', 'Clove'],
            'Initiator' => ['Sova', 'Breach', 'Skye', 'KAY/O', 'Fade', 'Gekko'],
            'Sentinel' => ['Sage', 'Cypher', 'Killjoy', 'Chamber', 'Deadlock', 'Vyse']
        ];
        foreach ($roles as $role => $members) {
            if (in_array($agent, $members))
                return $role;
        }
        return "Flex";
    }

    private function createEmptyDto(): GamerPerformanceDto
    {
        return new GamerPerformanceDto(
            'valorant',
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
            'Analyse impossible sans historique.'
        );
    }
}
