<?php

namespace App\Tests\Service;

use App\Service\ValorantStatsService;
use App\Dto\GamerPerformanceDto;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ValorantStatsService
 *
 * Couverture :
 * - Calcul KDA, winRate, agent le plus joué
 * - Cas limites : 0 match, 1 match, victoires/défaites
 * - Indicateurs de performance (Elite, Strong, Developing, Inconsistent)
 */
class ValorantStatsServiceTest extends TestCase
{
    private ValorantStatsService $service;

    protected function setUp(): void
    {
        $this->service = new ValorantStatsService();
    }

    // ──────────────────────────────────────────────────────────────
    // 1. Cas : tableau vide → DTO vide
    // ──────────────────────────────────────────────────────────────

    public function testCalculateWithEmptyMatchesReturnsEmptyDto(): void
    {
        $dto = $this->service->calculate([]);

        $this->assertInstanceOf(GamerPerformanceDto::class, $dto);
        $this->assertSame(0, $dto->matchCount);
        $this->assertSame(0, $dto->wins);
        $this->assertSame(0, $dto->losses);
        $this->assertSame(0.0, $dto->winRate);
        $this->assertSame('valorant', $dto->game);
        $this->assertSame('N/A', $dto->performanceIndicator);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. Cas : 1 seule victoire
    // ──────────────────────────────────────────────────────────────

    public function testCalculateWithSingleWin(): void
    {
        $matches = [
            [
                'win' => true,
                'kills' => 20,
                'deaths' => 5,
                'assists' => 8,
                'acs' => 280,
                'hsPercent' => 25,
                'adr' => 150,
                'economy' => 60,
                'agent' => 'Jett',
            ],
        ];

        $dto = $this->service->calculate($matches);

        $this->assertSame(1, $dto->matchCount);
        $this->assertSame(1, $dto->wins);
        $this->assertSame(0, $dto->losses);
        $this->assertEquals(100.0, $dto->winRate);
        $this->assertEquals(20.0, $dto->avgKills);
        $this->assertEquals(5.0, $dto->avgDeaths);
        $this->assertEquals(8.0, $dto->avgAssists);
        // KDA = (kills + assists) / max(1, deaths) = (20+8)/5 = 5.6
        $this->assertEqualsWithDelta(5.6, $dto->avgKda, 0.01);
        $this->assertSame('Jett', $dto->mostPlayed['name']);
        $this->assertSame(1, $dto->mostPlayed['games']);
        $this->assertEquals(100.0, $dto->mostPlayed['winRate']);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Cas : plusieurs matchs, calcul winRate
    // ──────────────────────────────────────────────────────────────

    public function testCalculateWinRateAcrossMultipleMatches(): void
    {
        $matches = $this->buildMatches(wins: 3, losses: 2, agent: 'Reyna');

        $dto = $this->service->calculate($matches);

        $this->assertSame(5, $dto->matchCount);
        $this->assertSame(3, $dto->wins);
        $this->assertSame(2, $dto->losses);
        $this->assertSame(60.0, $dto->winRate);
        $this->assertSame('valorant', $dto->game);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Cas : KDA avec deaths = 0 (pas de division par zéro)
    // ──────────────────────────────────────────────────────────────

    public function testCalculateKdaWithZeroDeaths(): void
    {
        $matches = [
            [
                'win' => true,
                'kills' => 30,
                'deaths' => 0,
                'assists' => 10,
                'acs' => 300,
                'agent' => 'Omen',
            ],
        ];

        $dto = $this->service->calculate($matches);

        // KDA = (30+10)/max(1,0) = 40
        $this->assertEqualsWithDelta(40.0, $dto->avgKda, 0.01);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Agent le plus joué — multi-agents
    // ──────────────────────────────────────────────────────────────

    public function testMostPlayedAgentIsCorrectlyDetected(): void
    {
        $matches = [
            ['win' => true, 'kills' => 15, 'deaths' => 5, 'assists' => 3, 'acs' => 200, 'agent' => 'Jett'],
            ['win' => false, 'kills' => 10, 'deaths' => 8, 'assists' => 2, 'acs' => 180, 'agent' => 'Sage'],
            ['win' => true, 'kills' => 18, 'deaths' => 4, 'assists' => 5, 'acs' => 220, 'agent' => 'Jett'],
            ['win' => false, 'kills' => 12, 'deaths' => 7, 'assists' => 3, 'acs' => 190, 'agent' => 'Jett'],
        ];

        $dto = $this->service->calculate($matches);

        $this->assertSame('Jett', $dto->mostPlayed['name']);
        $this->assertSame(3, $dto->mostPlayed['games']);
        // Jett : 2 victoires sur 3 → 66.67%
        $this->assertEqualsWithDelta(66.67, $dto->mostPlayed['winRate'], 0.1);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Indicateur de performance : Elite (≥60% WR ET acs ≥ 240)
    // ──────────────────────────────────────────────────────────────

    public function testPerformanceIndicatorElite(): void
    {
        $matches = [
            ['win' => true, 'kills' => 25, 'deaths' => 3, 'assists' => 7, 'acs' => 280, 'agent' => 'Jett'],
            ['win' => true, 'kills' => 22, 'deaths' => 4, 'assists' => 5, 'acs' => 260, 'agent' => 'Jett'],
            ['win' => true, 'kills' => 28, 'deaths' => 2, 'assists' => 9, 'acs' => 300, 'agent' => 'Jett'],
        ];

        $dto = $this->service->calculate($matches);

        $this->assertSame(100.0, $dto->winRate);
        $this->assertSame('Elite', $dto->performanceIndicator);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. Indicateur de performance : Inconsistent (< 48% WR)
    // ──────────────────────────────────────────────────────────────

    public function testPerformanceIndicatorInconsistent(): void
    {
        $matches = $this->buildMatches(wins: 1, losses: 4, agent: 'Phoenix', acs: 150);

        $dto = $this->service->calculate($matches);

        // WinRate = 20%
        $this->assertSame(20.0, $dto->winRate);
        $this->assertSame('Inconsistent', $dto->performanceIndicator);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. Les métriques avancées sont calculées
    // ──────────────────────────────────────────────────────────────

    public function testAdvancedMetricsArePresentAndValid(): void
    {
        $matches = [
            [
                'win' => true,
                'kills' => 20,
                'deaths' => 5,
                'assists' => 6,
                'acs' => 250,
                'hsPercent' => 22,
                'adr' => 140,
                'economy' => 55,
                'agent' => 'Viper',
            ],
        ];

        $dto = $this->service->calculate($matches);

        $this->assertArrayHasKey('avgAcs', $dto->advancedMetrics);
        $this->assertArrayHasKey('avgHsPercent', $dto->advancedMetrics);
        $this->assertArrayHasKey('avgAdr', $dto->advancedMetrics);
        $this->assertArrayHasKey('avgEconomy', $dto->advancedMetrics);
        $this->assertEquals(250, $dto->advancedMetrics['avgAcs']);
        $this->assertEquals(22.0, $dto->advancedMetrics['avgHsPercent']);
    }

    // ──────────────────────────────────────────────────────────────
    // 9. toArray() retourne toutes les clés attendues
    // ──────────────────────────────────────────────────────────────

    public function testDtoToArrayContainsAllExpectedKeys(): void
    {
        $dto = $this->service->calculate([
            ['win' => true, 'kills' => 10, 'deaths' => 3, 'assists' => 5, 'agent' => 'Sage'],
        ]);

        $array = $dto->toArray();

        foreach ([
            'game',
            'matchCount',
            'wins',
            'losses',
            'winRate',
            'avgKills',
            'avgDeaths',
            'avgAssists',
            'avgKda',
            'mostPlayed',
            'performanceIndicator',
            'advancedMetrics'
        ] as $key) {
            $this->assertArrayHasKey($key, $array, "La clé '$key' est absente de toArray()");
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMatches(int $wins, int $losses, string $agent = 'Jett', int $acs = 200): array
    {
        $matches = [];
        for ($i = 0; $i < $wins; $i++) {
            $matches[] = ['win' => true, 'kills' => 15, 'deaths' => 4, 'assists' => 5, 'acs' => $acs, 'agent' => $agent];
        }
        for ($i = 0; $i < $losses; $i++) {
            $matches[] = ['win' => false, 'kills' => 10, 'deaths' => 7, 'assists' => 3, 'acs' => $acs, 'agent' => $agent];
        }
        return $matches;
    }
}
