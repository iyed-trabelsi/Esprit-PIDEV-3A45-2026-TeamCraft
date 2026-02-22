<?php

namespace App\Dto;

class GamerPerformanceDto
{
    public function __construct(
        public readonly string $game,
        public readonly int $matchCount,
        public readonly int $wins,
        public readonly int $losses,
        public readonly float $winRate,
        public readonly float $avgKills,
        public readonly float $avgDeaths,
        public readonly float $avgAssists,
        public readonly float $avgKda,
        public readonly array $mostPlayed,
        public readonly string $performanceIndicator, // Elite, Strong, Developing, Inconsistent
        public readonly string $scoutingSummary,
        public readonly float $performanceScore = 0.0,
        public readonly string $overallRating = 'N/A',
        public readonly float $normalizedKda = 0.0,
        public readonly array $normalizedMetrics = [],
        public readonly array $advancedMetrics = [],
        public array $recentMatches = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'game' => $this->game,
            'matchCount' => $this->matchCount,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'winRate' => $this->winRate,
            'avgKills' => $this->avgKills,
            'avgDeaths' => $this->avgDeaths,
            'avgAssists' => $this->avgAssists,
            'avgKda' => $this->avgKda,
            'mostPlayed' => $this->mostPlayed,
            'performanceIndicator' => $this->performanceIndicator,
            'scoutingSummary' => $this->scoutingSummary,
            'performanceScore' => $this->performanceScore,
            'overallRating' => $this->overallRating,
            'normalizedKda' => $this->normalizedKda,
            'normalizedMetrics' => $this->normalizedMetrics,
            'advancedMetrics' => $this->advancedMetrics,
            'recentMatches' => $this->recentMatches,
        ];
    }
}
