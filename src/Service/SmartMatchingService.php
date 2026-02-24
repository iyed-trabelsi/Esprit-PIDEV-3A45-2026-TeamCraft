<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Offer;
use App\Entity\Player;
use App\Repository\PlayerRepository;
use App\Service\SmartMatching\CompatibilityScorer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Smart Matching AI Service.
 *
 * Recommends the top N most compatible players for a given offer
 * based on game, role, rank proximity, and winrate.
 *
 * Integrated with FastAPI Microservice for predictive scoring.
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
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
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

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($offer) {
            $item->expiresAfter(self::CACHE_TTL);
            return $this->computeTopPlayers($offer);
        });
    }

    /**
     * Predict match score using the AI microservice.
     */
    public function predictMatch(Player $player, Offer $offer): array
    {
        $apiUrl = $this->parameterBag->get('ai_service_url') ?: 'http://127.0.0.1:8000';
        
        try {
            $response = $this->httpClient->request('POST', $apiUrl . '/predict', [
                'json' => [
                    'player_rank' => $this->scorer->resolveRankLevel($player->getGameRank()) ?? 1,
                    'player_kd' => $player->getKd() ?? 1.0,
                    'player_winrate' => $player->getWinrate() ?? 50.0,
                    'player_role' => $player->getRole() ?? 'N/A',
                    'offer_required_rank' => $this->scorer->resolveRankLevel($offer->getRank()) ?? 1,
                    'offer_role' => $offer->getRole() ?? 'N/A',
                ],
                'timeout' => 2.0,
            ]);

            if ($response->getStatusCode() === 200) {
                return $response->toArray();
            }

            $this->logger->error('AI Service returned error status: ' . $response->getStatusCode());
        } catch (\Exception $e) {
            $this->logger->error('AI Service communication failed: ' . $e->getMessage());
        }

        // Fallback to internal PHP logic
        $score = $this->scorer->computeScore($player, $offer);
        return [
            'match_score' => $score,
            'compatibility_level' => $this->getCompatibilityLevelFromScore($score),
            'fallback' => true,
        ];
    }

    /**
     * Predict match scores for multiple offers in a single request.
     * 
     * @param Offer[] $offers
     * @return array<int, array{match_score: float, compatibility_level: string}>
     */
    public function predictMatchesBatch(Player $player, array $offers): array
    {
        if (empty($offers)) {
            return [];
        }

        $apiUrl = $this->parameterBag->get('ai_service_url') ?: 'http://127.0.0.1:8000';
        
        $offerData = array_map(fn(Offer $o) => [
            'id' => $o->getId(),
            'rank' => $this->scorer->resolveRankLevel($o->getRank()) ?? 1,
            'role' => $o->getRole() ?? 'N/A',
        ], $offers);

        try {
            $response = $this->httpClient->request('POST', $apiUrl . '/predict_batch', [
                'json' => [
                    'player_rank' => $this->scorer->resolveRankLevel($player->getGameRank()) ?? 1,
                    'player_kd' => $player->getKd() ?? 1.0,
                    'player_winrate' => $player->getWinrate() ?? 50.0,
                    'player_role' => $player->getRole() ?? 'N/A',
                    'offers' => $offerData,
                ],
                'timeout' => 5.0, // Slightly longer for batch
            ]);

            if ($response->getStatusCode() === 200) {
                return $response->toArray();
            }

            $this->logger->error('AI Service (Batch) returned error status: ' . $response->getStatusCode());
        } catch (\Exception $e) {
            $this->logger->error('AI Service (Batch) communication failed: ' . $e->getMessage());
        }

        // Fallback: Compute individually using internal logic
        $results = [];
        foreach ($offers as $o) {
            $score = $this->scorer->computeScore($player, $o);
            $results[(string)$o->getId()] = [
                'match_score' => $score,
                'compatibility_level' => $this->getCompatibilityLevelFromScore($score),
                'fallback' => true,
            ];
        }

        return $results;
    }

    private function getCompatibilityLevelFromScore(float $score): string
    {
        if ($score >= 80) return 'High';
        if ($score >= 50) return 'Medium';
        return 'Low';
    }

    /**
     * Compute top players (no cache).
     *
     * @return array<int, array{player: \App\Entity\Player, score: float}>
     */
    private function computeTopPlayers(Offer $offer): array
    {
        $game = $offer->getGame() ?? '';
        $aliases = $this->getGameAliases($game);
        
        $candidates = $this->playerRepository->findCandidatesForOffer($aliases, self::CANDIDATE_LIMIT);
        if (empty($candidates)) {
            return [];
        }

        // We need specialized batch logic for players vs one offer
        // Let's adapt predictMatchesBatch logic or just use it by creating a fake batch
        // Actually, it's easier to just add a batch predict for players
        
        // Since I've already updated the FastAPI to allow multiple offers for ONE player,
        // it doesn't quite fit the candidates (multiple players) for ONE offer case.
        // But the FastAPI logic is just a loop, so let's update it to be more flexible or add a new endpoint.
        
        // OR: Update Symfony to just loop if the number of candidates is small, 
        // but 50 might be too much.
        
        // Let's update FastAPI once more to handle batch players for one offer.
        return $this->computeTopPlayersLegacy($offer, $candidates);
    }

    private function computeTopPlayersLegacy(Offer $offer, array $candidates): array
    {
        $scored = [];
        foreach ($candidates as $player) {
            $prediction = $this->predictMatch($player, $offer);
            $score = (float) $prediction['match_score'];
            
            if ($score > 0) {
                $scored[] = [
                    'player' => $player, 
                    'score' => round($score, 1),
                    'compatibility_level' => $prediction['compatibility_level']
                ];
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
