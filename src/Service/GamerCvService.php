<?php

namespace App\Service;

use App\Entity\RiotStats;
use App\Entity\User;
use App\Dto\GamerPerformanceDto;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class GamerCvService
{
    public function __construct(
        private readonly RiotApiService $riotApiService,
        private readonly Calculator\LolPerformanceCalculator $lolCalculator,
        private readonly Calculator\ValorantPerformanceCalculator $valorantCalculator,
        private readonly Calculator\Cs2PerformanceCalculator $cs2Calculator,
        private readonly CacheInterface $cache
    ) {
    }

    public function buildCvData(User $user, ?RiotStats $riotStats, string $game = 'lol', ?\App\Entity\SteamStats $steamStats = null): array
    {
        $player = $user->getPlayerProfile();
        $data = [
            'game' => $game,
            'user' => [
                'username' => $user->getUsername(),
                'pseudo' => $user->getPseudo(),
                'country' => $user->getCountry() ?? 'N/A',
                'email' => $user->getEmail(),
                'mainGame' => $player?->getGame() ?? 'N/A',
                'mainRole' => $player?->getRole() ?? 'N/A',
                'region' => $player?->getRegion() ?? 'N/A',
            ],
            'rank' => null,
            'perf' => null,
        ];

        if ($game === 'cs2') {
            if (!$steamStats)
                return $data;
            $tier = $steamStats->getCurrentRank() ?: 'UNRANKED';
            $data['rank'] = [
                'tier' => $tier,
                'division' => '',
                'lp' => ($tier === 'UNRANKED') ? null : 0,
                'wins' => ($tier === 'UNRANKED') ? null : ($steamStats->getWins() ?? 0),
                'losses' => ($tier === 'UNRANKED') ? null : ($steamStats->getLosses() ?? 0),
                'winRate' => ($tier === 'UNRANKED') ? null : ($steamStats->getWinRate() ?? 0),
            ];
        } else {
            if (!$riotStats || !$riotStats->getPuuid())
                return $data;
            $tier = $riotStats->getTier() ?: 'UNRANKED';
            $data['rank'] = [
                'riotId' => $riotStats->getRiotId(),
                'tier' => $tier,
                'division' => ($tier === 'UNRANKED') ? '' : $riotStats->getDivision(),
                'lp' => ($tier === 'UNRANKED') ? null : ($riotStats->getLeaguePoints() ?? 0),
                'wins' => ($tier === 'UNRANKED') ? null : ($riotStats->getWins() ?? 0),
                'losses' => ($tier === 'UNRANKED') ? null : ($riotStats->getLosses() ?? 0),
                'winRate' => ($tier === 'UNRANKED') ? null : $riotStats->getWinRate(),
            ];
        }

        // --- Performance with Caching (v3 for struct/logic change) ---
        $cacheKey = sprintf('gamer_cv_perf_v3_%d_%s', $user->getId(), $game);

        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($riotStats, $steamStats, $game) {
            $item->expiresAfter(3600); // Cache for 1 hour

            if ($game === 'cs2') {
                $rawStats = [
                    'totalMatches' => $steamStats->getTotalMatches(),
                    'wins' => $steamStats->getWins(),
                    'losses' => $steamStats->getLosses(),
                    'kills' => $steamStats->getKills(),
                    'deaths' => $steamStats->getDeaths(),
                    'assists' => $steamStats->getAssists(),
                    'headshots' => $steamStats->getHeadshots()
                ];
                $dto = $this->cs2Calculator->calculate([], ['raw_stats' => $rawStats]);
                return [
                    'perfDto' => $dto,
                    'matches' => []
                ];
            }

            $matches = $this->fetchLatestMatches($riotStats, $game);
            $calculator = ($game === 'valorant') ? $this->valorantCalculator : $this->lolCalculator;
            $dto = $calculator->calculate($matches);

            return [
                'perfDto' => $dto,
                'matches' => $matches
            ];
        });

        $perfDto = $cachedData['perfDto'];
        $matches = $cachedData['matches'];

        // Convert to array early to avoid object/array conflicts
        $perfArray = $perfDto->toArray();
        $perfArray['recentMatches'] = array_slice($matches, 0, 10);

        // Final Enrichment
        $data['perf'] = $perfDto->toArray();
        $data['summary'] = $perfDto->scoutingSummary;

        return $data;
    }

    private function fetchLatestMatches(RiotStats $riotStats, string $game): array
    {
        if ($game === 'valorant') {
            // Use cached matches in DB for Valorant (since we usually mock/proxy them)
            return $riotStats->getRecentMatches() ?? [];
        }

        // Live fetch for LoL
        $puuid = $riotStats->getPuuid();
        $region = $riotStats->getRegion() ?? 'EUW';
        $matchIds = $this->riotApiService->getMatchIds($puuid, $region, 0, 20);

        $matches = [];
        foreach ($matchIds as $matchId) {
            $detail = $this->riotApiService->getMatchDetails($matchId, $region);
            if ($detail) {
                $stats = $this->riotApiService->getParticipantStats($detail, $puuid);
                if ($stats) {
                    $matches[] = $this->enrichLoLStats($stats, $detail, $puuid);
                }
            }
        }

        return $matches;
    }

    private function enrichLoLStats(array $stats, array $matchData, string $puuid): array
    {
        $gameDuration = max(1, ($matchData['info']['gameDuration'] ?? 0) / 60);
        foreach ($matchData['info']['participants'] as $p) {
            if ($p['puuid'] === $puuid) {
                $stats['csPerMin'] = round(($p['totalMinionsKilled'] + ($p['neutralMinionsKilled'] ?? 0)) / $gameDuration, 2);
                $stats['goldPerMin'] = round(($p['goldEarned'] ?? 0) / $gameDuration, 2);
                $stats['visionScore'] = $p['visionScore'] ?? 0;
                $stats['totalDamage'] = $p['totalDamageDealtToChampions'] ?? 0;
                break;
            }
        }
        return $stats;
    }

    // Simplified summary generation (Logic moved to calculators)
}
