<?php

declare(strict_types=1);

namespace App\Service\SmartMatching;

use App\Entity\Offer;
use App\Entity\Player;

/**
 * Compatibility scoring algorithm for Smart Matching AI.
 *
 * Score breakdown (total max 100):
 * - 40 pts: Same main game (exact match)
 * - 30 pts: Same role (exact match)
 * - up to 20 pts: Rank proximity (closer = higher)
 * - up to 10 pts: Winrate bonus
 *
 * Future: This class can be replaced by a trained ML model (e.g. Python API)
 * to compute scores without changing the rest of the architecture.
 */
class CompatibilityScorer
{
    /**
     * Valorant/LoL-style rank hierarchy (ascending order).
     * Lower index = lower rank, higher index = higher rank.
     */
    private const RANK_HIERARCHY = [
        'iron' => 1,
        'bronze' => 2,
        'silver' => 3,
        'gold' => 4,
        'platinum' => 5,
        'emerald' => 6,
        'diamond' => 7,
        'ascendant' => 8,
        'master' => 9,
        'grandmaster' => 10,
        'challenger' => 11,
        'immortal' => 12,
        'radiant' => 13,
        'global' => 13, // CS2 top rank
    ];

    private const MAX_GAME_SCORE = 40;
    private const MAX_ROLE_SCORE = 30;
    private const MAX_RANK_SCORE = 20;
    private const MAX_WINRATE_SCORE = 10;

    /**
     * Compute the compatibility score (0–100) between a player and an offer.
     */
    public function computeScore(Player $player, Offer $offer): float
    {
        $offerGame = $this->normalizeString($offer->getGame());
        $offerRole = $this->normalizeString($offer->getRole());
        $offerRank = $offer->getRank();

        // Resolve player data: prefer CompetitiveRank for the offer's game, else fallback to Player fields
        $playerGame = $this->getPlayerGameForOffer($player, $offerGame);
        $playerRole = $this->getPlayerRoleForOffer($player, $offerGame);
        $playerRank = $this->getPlayerRankForOffer($player, $offerGame);

        $gameScore = $this->scoreGame($playerGame, $offerGame);
        $roleScore = $this->scoreRole($playerRole, $offerRole);
        $rankScore = $this->scoreRank($playerRank, $offerRank);
        $winrateScore = $this->scoreWinrate($player->getWinrate());

        return min(100.0, $gameScore + $roleScore + $rankScore + $winrateScore);
    }

    /**
     * Game match: 40 pts if exact match, 0 otherwise.
     */
    private function scoreGame(?string $playerGame, ?string $offerGame): float
    {
        if ($playerGame === null || $offerGame === null) {
            return 0.0;
        }

        return $this->gamesMatch($playerGame, $offerGame) ? self::MAX_GAME_SCORE : 0.0;
    }

    /**
     * Role match: 30 pts if exact match, 0 otherwise.
     */
    private function scoreRole(?string $playerRole, ?string $offerRole): float
    {
        if ($playerRole === null || $offerRole === null) {
            return 0.0;
        }

        return $this->rolesMatch($playerRole, $offerRole) ? self::MAX_ROLE_SCORE : 0.0;
    }

    /**
     * Rank proximity: up to 20 pts based on distance in hierarchy.
     * Same rank = 20, adjacent = ~16, 2 steps = ~12, etc.
     */
    private function scoreRank(?string $playerRank, ?string $offerRank): float
    {
        if ($playerRank === null || $offerRank === null) {
            return 0.0;
        }

        $playerLevel = $this->resolveRankLevel($playerRank);
        $offerLevel = $this->resolveRankLevel($offerRank);

        if ($playerLevel === null || $offerLevel === null) {
            // Unknown rank: partial credit if strings are similar
            return $this->normalizeString($playerRank) === $this->normalizeString($offerRank) ? self::MAX_RANK_SCORE : 0.0;
        }

        $distance = abs($playerLevel - $offerLevel);
        $maxDistance = count(self::RANK_HIERARCHY);

        // Linear decay: 20 pts at 0 distance, ~0 at max distance
        $score = self::MAX_RANK_SCORE * (1 - ($distance / ($maxDistance + 1)));

        return max(0.0, round($score, 2));
    }

    /**
     * Winrate bonus: up to 10 pts (100% winrate = 10 pts).
     */
    private function scoreWinrate(?float $winrate): float
    {
        if ($winrate === null || $winrate < 0) {
            return 0.0;
        }

        $winrate = min(100.0, max(0.0, $winrate));
        return round((self::MAX_WINRATE_SCORE * $winrate) / 100, 2);
    }

    private function getPlayerGameForOffer(Player $player, ?string $offerGame): ?string
    {
        foreach ($player->getCompetitiveRanks() as $cr) {
            if ($this->gamesMatch($this->normalizeString($cr->getGame()), $offerGame)) {
                return $this->normalizeString($cr->getGame());
            }
        }

        return $player->getGame() ? $this->normalizeString($player->getGame()) : null;
    }

    private function getPlayerRoleForOffer(Player $player, ?string $offerGame): ?string
    {
        foreach ($player->getCompetitiveRanks() as $cr) {
            if ($this->gamesMatch($this->normalizeString($cr->getGame()), $offerGame)) {
                return $cr->getPrincipalRole() ? $this->normalizeString($cr->getPrincipalRole()) : null;
            }
        }

        return $player->getRole() ? $this->normalizeString($player->getRole()) : null;
    }

    private function getPlayerRankForOffer(Player $player, ?string $offerGame): ?string
    {
        foreach ($player->getCompetitiveRanks() as $cr) {
            if ($this->gamesMatch($this->normalizeString($cr->getGame()), $offerGame)) {
                return $cr->getSkillLevel();
            }
        }

        return $player->getGameRank();
    }

    /**
     * Normalize game names (LoL/League of Legends, CS2/CSGO, etc.).
     */
    public static function normalizeGameName(string $game): string
    {
        $game = strtolower(trim($game));

        return match (true) {
            str_contains($game, 'lol') || str_contains($game, 'league') => 'league of legends',
            str_contains($game, 'cs2') || str_contains($game, 'csgo') || str_contains($game, 'counter') => 'cs2',
            str_contains($game, 'ow') || str_contains($game, 'overwatch') => 'overwatch',
            str_contains($game, 'valorant') || str_contains($game, 'valo') => 'valorant',
            default => $game,
        };
    }

    private function gamesMatch(string $a, string $b): bool
    {
        return self::normalizeGameName($a) === self::normalizeGameName($b);
    }

    private function rolesMatch(string $a, string $b): bool
    {
        $a = $this->normalizeString($a);
        $b = $this->normalizeString($b);
        
        // Exact match or containment (e.g. "Duelist" in "Duelist / Entry")
        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    public function resolveRankLevel(?string $rank): ?int
    {
        if ($rank === null || $rank === '') {
            return null;
        }

        $rank = $this->normalizeString($rank);

        foreach (self::RANK_HIERARCHY as $key => $level) {
            if (str_contains($rank, $key)) {
                return $level;
            }
        }

        return null;
    }

    private function normalizeString(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        return strtolower(trim($s));
    }
}
