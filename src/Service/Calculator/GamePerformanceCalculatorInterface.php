<?php

namespace App\Service\Calculator;

use App\Dto\GamerPerformanceDto;

interface GamePerformanceCalculatorInterface
{
    /**
     * @param array $matches History of matches to analyze
     * @param array $rankData Optional rank information (Tier, Division, etc.)
     */
    public function calculate(array $matches, array $rankData = []): GamerPerformanceDto;
}
