<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Offer;
use App\Repository\PlayerRepository;
use App\Service\SmartMatching\CompatibilityScorer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Smart Matching AI Service.
 *
 * Recommends the top N most compatible players for a given offer
 * based on game, role, rank proximity, and winrate.
 *
 * Architecture note: The scoring logic is delegated to CompatibilityScorer.
 * This can later be replaced by a trained ML model (e.g. Python API)
 * without changing this service's public interface.
 */
class SmartMatchingService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const TOP_LIMIT = 3;
    private const CANDIDATE_LIMIT = 50;

    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly CompatibilityScorer $scorer,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Get the top 3 most compatible players for an offer.
     *
     * @return array<int, array{player: \App\Entity\Player, score: float}>
     */
    public function getTopPlayersForOffer(Offer $offer): array
    {
        $cacheKey = 'smart_matching_offer_' . $offer->getId();

        if ($this->cache !== null) {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($offer) {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->computeTopPlayers($offer);
            });
        }

        return $this->computeTopPlayers($offer);
    }

    /**
     * Compute top players (no cache).
     *
     * @return array<int, array{player: \App\Entity\Player, score: float}>
     */
    /**
     * Compute top players (no cache).
     *
     * @return array<int, array{player: \App\Entity\Player, score: float}>
     */
    private function computeTopPlayers(Offer $offer): array
    {
        $game = $offer->getGame() ?? '';
        $aliases = $this->getGameAliases($game);
        
        // Pass array of aliases instead of single string
        $candidates = $this->playerRepository->findCandidatesForOffer($aliases, self::CANDIDATE_LIMIT);

        $scored = [];
        foreach ($candidates as $player) {
            $score = $this->scorer->computeScore($player, $offer);
            
            // Only include relevant candidates (score > 0)
            if ($score > 0) {
                $scored[] = ['player' => $player, 'score' => round($score, 1)];
            }
        }

        usort($scored, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, self::TOP_LIMIT);
    }

    /**
     * Generate common aliases for a game to improve search reach.
     * 
     * @return string[]
     */
    private function getGameAliases(string $game): array
    {
        $game = CompatibilityScorer::normalizeGameName($game);
        
        return match ($game) {
            'league of legends' => ['league of legends', 'lol', 'league'],
            'cs2' => ['cs2', 'csgo', 'counter-strike', 'cs:go'],
            'overwatch' => ['overwatch', 'ow', 'ow2'],
            'valorant' => ['valorant', 'valo'],
            default => [$game],
        };
    }
}
