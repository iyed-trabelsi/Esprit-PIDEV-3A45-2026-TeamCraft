<?php

namespace App\Tests\Service;

use App\Dto\GamerPerformanceDto;
use App\Entity\Player;
use App\Entity\RiotStats;
use App\Entity\User;
use App\Service\Calculator\LolPerformanceCalculator;
use App\Service\Calculator\ValorantPerformanceCalculator;
use App\Service\Calculator\Cs2PerformanceCalculator;
use App\Service\GamerCvService;
use App\Service\RiotApiService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Tests unitaires pour GamerCvService::buildCvData()
 *
 * Couverture :
 * - Utilisateur sans profil joueur → structure de base retournée
 * - RiotStats absent → données rank à null
 * - RiotStats présents → données rank correctes
 * - Jeu LoL : performance calculée et mise en cache
 * - Jeu Valorant : performance calculée
 * - Jeu CS2 sans steamStats → retour précoce
 * - Toutes les clés attendues présentes
 */
class GamerCvServiceTest extends TestCase
{
    /** @var MockObject&RiotApiService */
    private MockObject $riotApi;

    /** @var MockObject&LolPerformanceCalculator */
    private MockObject $lolCalc;

    /** @var MockObject&ValorantPerformanceCalculator */
    private MockObject $valorantCalc;

    /** @var MockObject&Cs2PerformanceCalculator */
    private MockObject $cs2Calc;

    /** @var MockObject&CacheInterface */
    private MockObject $cache;

    private GamerCvService $service;

    protected function setUp(): void
    {
        $this->riotApi = $this->createMock(RiotApiService::class);
        $this->lolCalc = $this->createMock(LolPerformanceCalculator::class);
        $this->valorantCalc = $this->createMock(ValorantPerformanceCalculator::class);
        $this->cs2Calc = $this->createMock(Cs2PerformanceCalculator::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->service = new GamerCvService(
            $this->riotApi,
            $this->lolCalc,
            $this->valorantCalc,
            $this->cs2Calc,
            $this->cache
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function makeUser(string $username = 'TestUser', ?Player $player = null): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setPseudo('GamerPro');
        $user->setEmail('gamer@test.com');
        if ($player) {
            $user->setPlayerProfile($player);
        }
        return $user;
    }

    private function makeRiotStats(string $puuid = 'test-puuid-123', string $tier = 'GOLD'): RiotStats
    {
        $rs = new RiotStats();
        $rs->setPuuid($puuid);
        $rs->setGameName('Player');
        $rs->setTagLine('EUW');
        $rs->setTier($tier);
        $rs->setDivision('II');
        $rs->setLeaguePoints(75);
        $rs->setWins(50);
        $rs->setLosses(30);
        return $rs;
    }

    private function makeEmptyDto(string $game = 'lol'): GamerPerformanceDto
    {
        return new GamerPerformanceDto(
            game: $game,
            matchCount: 0,
            wins: 0,
            losses: 0,
            winRate: 0.0,
            avgKills: 0.0,
            avgDeaths: 0.0,
            avgAssists: 0.0,
            avgKda: 0.0,
            mostPlayed: ['name' => 'N/A', 'games' => 0, 'winRate' => 0],
            performanceIndicator: 'N/A',
            scoutingSummary: 'No data'
        );
    }

    /**
     * Configure le mock Cache pour exécuter le callback directement (sans cache réel).
     */
    private function configureCachePassthrough(): void
    {
        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter')->willReturnSelf();
                return $callback($item);
            });
    }

    // ──────────────────────────────────────────────────────────────
    // 1. RiotStats manquant → retour précoce (rank = null)
    // ──────────────────────────────────────────────────────────────

    public function testBuildCvDataWithoutRiotStatsReturnsEarlyWithNullRank(): void
    {
        $user = $this->makeUser();

        $result = $this->service->buildCvData($user, null, 'lol');

        $this->assertIsArray($result);
        $this->assertSame('lol', $result['game']);
        $this->assertNull($result['rank']);
        $this->assertNull($result['perf']);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. RiotStats sans PUUID → retour précoce
    // ──────────────────────────────────────────────────────────────

    public function testBuildCvDataWithRiotStatsButNoPuuidReturnsEarly(): void
    {
        $user = $this->makeUser();
        $riotStats = new RiotStats(); // pas de PUUID

        $result = $this->service->buildCvData($user, $riotStats, 'lol');

        $this->assertNull($result['rank']);
        $this->assertNull($result['perf']);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Jeu LoL — données rank correctes
    // ──────────────────────────────────────────────────────────────

    public function testBuildCvDataForLolContainsCorrectRankData(): void
    {
        $this->configureCachePassthrough();

        $riotStats = $this->makeRiotStats('puuid-abc', 'PLATINUM');
        $this->riotApi->method('getMatchIds')->willReturn([]);
        $this->lolCalc->method('calculate')->willReturn($this->makeEmptyDto('lol'));

        $user = $this->makeUser();
        $result = $this->service->buildCvData($user, $riotStats, 'lol');

        $this->assertIsArray($result);
        $this->assertSame('lol', $result['game']);
        $this->assertNotNull($result['rank']);
        $this->assertSame('PLATINUM', $result['rank']['tier']);
        $this->assertSame('II', $result['rank']['division']);
        $this->assertSame(75, $result['rank']['lp']);
        $this->assertSame(50, $result['rank']['wins']);
        $this->assertSame(30, $result['rank']['losses']);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Jeu LoL — rang UNRANKED → lp/wins/losses = null
    // ──────────────────────────────────────────────────────────────

    public function testBuildCvDataForLolUnrankedHasNullLpWinsLosses(): void
    {
        $this->configureCachePassthrough();

        $riotStats = $this->makeRiotStats('puuid-xyz', 'UNRANKED');
        $this->riotApi->method('getMatchIds')->willReturn([]);
        $this->lolCalc->method('calculate')->willReturn($this->makeEmptyDto('lol'));

        $result = $this->service->buildCvData($this->makeUser(), $riotStats, 'lol');

        $this->assertNull($result['rank']['lp']);
        $this->assertNull($result['rank']['wins']);
        $this->assertNull($result['rank']['losses']);
        $this->assertSame('', $result['rank']['division']);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Jeu Valorant — utilise ValorantCalculator
    // ──────────────────────────────="────────────────────────────────

    public function testBuildCvDataForValorantUsesValorantCalculator(): void
    {
        $this->configureCachePassthrough();

        $riotStats = $this->makeRiotStats('puuid-val', 'GOLD');
        $riotStats->setRecentMatches([
            ['win' => true, 'kills' => 20, 'deaths' => 5, 'assists' => 4, 'agent' => 'Jett'],
        ]);

        $expectedDto = $this->makeEmptyDto('valorant');
        $this->valorantCalc
            ->expects($this->once())
            ->method('calculate')
            ->willReturn($expectedDto);

        $this->lolCalc->expects($this->never())->method('calculate');

        $result = $this->service->buildCvData($this->makeUser(), $riotStats, 'valorant');

        $this->assertSame('valorant', $result['game']);
        $this->assertNotNull($result['perf']);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Jeu CS2 sans steamStats → retour précoce
    // ──────────────────────────────────────────────────────────────

    public function testBuildCvDataForCs2WithoutSteamStatsReturnsEarly(): void
    {
        $result = $this->service->buildCvData($this->makeUser(), null, 'cs2', null);

        $this->assertSame('cs2', $result['game']);
        $this->assertNull($result['rank']);
        $this->assertNull($result['perf']);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. Données utilisateur correctement incluses
    // ──────────────────────────────────────────────────────────────

    public function testBuildCvDataIncludesCorrectUserInfo(): void
    {
        $player = new Player();
        $player->setGame('Valorant');
        $player->setRole('Duelist');
        $player->setRegion('EU');

        $user = $this->makeUser('aziz123', $player);

        $result = $this->service->buildCvData($user, null, 'valorant');

        $this->assertArrayHasKey('user', $result);
        $this->assertSame('aziz123', $result['user']['username']);
        $this->assertSame('GamerPro', $result['user']['pseudo']);
        $this->assertSame('Valorant', $result['user']['mainGame']);
        $this->assertSame('Duelist', $result['user']['mainRole']);
        $this->assertSame('EU', $result['user']['region']);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. Clés de base toujours présentes
    // ──────────────────────────────────────────────────────────────

    public function testBuildCvDataAlwaysHasBaseKeys(): void
    {
        $result = $this->service->buildCvData($this->makeUser(), null, 'lol');

        foreach (['game', 'user', 'rank', 'perf'] as $key) {
            $this->assertArrayHasKey($key, $result, "Clé '$key' absente du résultat");
        }

        foreach (['username', 'pseudo', 'email', 'mainGame', 'mainRole', 'region', 'country'] as $userKey) {
            $this->assertArrayHasKey($userKey, $result['user'], "Clé user['$userKey'] absente");
        }
    }
}
