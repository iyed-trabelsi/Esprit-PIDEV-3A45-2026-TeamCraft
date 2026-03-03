<?php

namespace App\Service\Calculator;

use App\Dto\GamerPerformanceDto;

interface GamePerformanceCalculatorInterface
{
    /**
     * @param array $matches History of matches to analyze
     * @param array $rankData Optional rank information (Tier, Division, etc.)
     */
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
    public function calculate(array $matches, array $rankData = []): GamerPerformanceDto;
}
