<?php

namespace App\Tests\Service\SmartMatching;

use App\Entity\Offer;
use App\Entity\Player;
use App\Service\SmartMatching\CompatibilityScorer;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CompatibilityScorer::computeScore()
 *
 * Score max = 100 pts :
 *   40 — jeu identique
 *   30 — rôle identique
 *   20 — rang proche
 *   10 — winrate
 *
 * Couverture :
 * - Correspondance parfaite → score élevé
 * - Jeu différent → 0 pts sur le jeu
 * - Rang exact → 20 pts
 * - Normalisation des noms (lol/League of Legends, cs2/CSGO)
 * - resolveRankLevel() sur hiérarchie connue et inconnue
 * - normalizeGameName() static
 */
class CompatibilityScorerTest extends TestCase
{
    private CompatibilityScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new CompatibilityScorer();
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function makePlayer(
        string $game,
        string $role,
        string $rank,
        float $winrate = 60.0
    ): Player {
        $player = new Player();
        $player->setGame($game);
        $player->setRole($role);
        $player->setGameRank($rank);
        $player->setWinrate($winrate);
        // Vider la collection CompetitiveRanks (ArrayCollection vide par défaut)
        return $player;
    }

    private function makeOffer(string $game, string $role, string $rank): Offer
    {
        $offer = new Offer();
        $offer->setGame($game);
        $offer->setRole($role);
        $offer->setRank($rank);
        return $offer;
    }

    // ──────────────────────────────────────────────────────────────
    // 1. Correspondance parfaite → score ≥ 80
    // ──────────────────────────────────────────────────────────────

    public function testPerfectMatchReturnsHighScore(): void
    {
        $player = $this->makePlayer('Valorant', 'Duelist', 'Diamond', 65.0);
        $offer = $this->makeOffer('Valorant', 'Duelist', 'Diamond');

        $score = $this->scorer->computeScore($player, $offer);

        // Game(40) + Role(30) + Rank(20) + Winrate(6.5) = 96.5
        $this->assertGreaterThanOrEqual(80.0, $score);
        $this->assertLessThanOrEqual(100.0, $score);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. Jeu différent → score réduit de 40 pts
    // ──────────────────────────────────────────────────────────────

    public function testDifferentGameReducesScoreBy40(): void
    {
        $playerSameGame = $this->makePlayer('Valorant', 'Duelist', 'Gold', 50.0);
        $playerDiffGame = $this->makePlayer('League of Legends', 'Duelist', 'Gold', 50.0);
        $offer = $this->makeOffer('Valorant', 'Duelist', 'Gold');

        $scoreSame = $this->scorer->computeScore($playerSameGame, $offer);
        $scoreDiff = $this->scorer->computeScore($playerDiffGame, $offer);

        $this->assertEqualsWithDelta(40.0, $scoreSame - $scoreDiff, 1.0);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Rôle différent → score réduit de 30 pts
    // ──────────────────────────────────────────────────────────────

    public function testDifferentRoleReducesScoreBy30(): void
    {
        $playerSameRole = $this->makePlayer('Valorant', 'Duelist', 'Silver', 50.0);
        $playerDiffRole = $this->makePlayer('Valorant', 'Controller', 'Silver', 50.0);
        $offer = $this->makeOffer('Valorant', 'Duelist', 'Silver');

        $scoreSame = $this->scorer->computeScore($playerSameRole, $offer);
        $scoreDiff = $this->scorer->computeScore($playerDiffRole, $offer);

        $this->assertEqualsWithDelta(30.0, $scoreSame - $scoreDiff, 1.0);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Rang identique → 20 pts de rang
    // ──────────────────────────────────────────────────────────────

    public function testSameRankGivesMaxRankScore(): void
    {
        $player = $this->makePlayer('CS2', 'Entry', 'Gold', 0.0);
        $offer = $this->makeOffer('CS2', 'Entry', 'Gold');

        // Game(40) + Role(30) + Rank(20) + Winrate(0) = 90
        $score = $this->scorer->computeScore($player, $offer);

        $this->assertEqualsWithDelta(90.0, $score, 1.0);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Rang très éloigné → pénalité
    // ──────────────────────────────────────────────────────────────

    public function testVeryDifferentRankReducesScore(): void
    {
        $playerIron = $this->makePlayer('Valorant', 'Duelist', 'Iron', 0.0);
        $playerRadiant = $this->makePlayer('Valorant', 'Duelist', 'Radiant', 0.0);
        $offer = $this->makeOffer('Valorant', 'Duelist', 'Radiant');

        $scoreClose = $this->scorer->computeScore($playerRadiant, $offer);
        $scoreFar = $this->scorer->computeScore($playerIron, $offer);

        $this->assertGreaterThan($scoreFar, $scoreClose);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Winrate 100% → 10 pts bonus
    // ──────────────────────────────────────────────────────────────

    public function testMaxWinrateGives10Points(): void
    {
        $playerMax = $this->makePlayer('Valorant', 'Duelist', 'Gold', 100.0);
        $playerZero = $this->makePlayer('Valorant', 'Duelist', 'Gold', 0.0);
        $offer = $this->makeOffer('Valorant', 'Duelist', 'Gold');

        $scoreMax = $this->scorer->computeScore($playerMax, $offer);
        $scoreZero = $this->scorer->computeScore($playerZero, $offer);

        $this->assertEqualsWithDelta(10.0, $scoreMax - $scoreZero, 0.1);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. Score plafonné à 100
    // ──────────────────────────────────────────────────────────────

    public function testScoreNeverExceeds100(): void
    {
        $player = $this->makePlayer('Valorant', 'Duelist', 'Radiant', 100.0);
        $offer = $this->makeOffer('Valorant', 'Duelist', 'Radiant');

        $score = $this->scorer->computeScore($player, $offer);

        $this->assertLessThanOrEqual(100.0, $score);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. normalizeGameName() — LoL aliases
    // ──────────────────────────────────────────────────────────────

    public function testNormalizeGameNameLolAliases(): void
    {
        $this->assertSame('league of legends', CompatibilityScorer::normalizeGameName('lol'));
        $this->assertSame('league of legends', CompatibilityScorer::normalizeGameName('League of Legends'));
        $this->assertSame('league of legends', CompatibilityScorer::normalizeGameName('LEAGUE'));
    }

    // ──────────────────────────────────────────────────────────────
    // 9. normalizeGameName() — CS2 aliases
    // ──────────────────────────────────────────────────────────────

    public function testNormalizeGameNameCs2Aliases(): void
    {
        $this->assertSame('cs2', CompatibilityScorer::normalizeGameName('cs2'));
        $this->assertSame('cs2', CompatibilityScorer::normalizeGameName('CSGO'));
        $this->assertSame('cs2', CompatibilityScorer::normalizeGameName('Counter-Strike'));
    }

    // ──────────────────────────────────────────────────────────────
    // 10. resolveRankLevel() — rangs connus
    // ──────────────────────────────────────────────────────────────

    public function testResolveRankLevelForKnownRanks(): void
    {
        $this->assertSame(1, $this->scorer->resolveRankLevel('Iron'));
        $this->assertSame(4, $this->scorer->resolveRankLevel('Gold'));
        $this->assertSame(7, $this->scorer->resolveRankLevel('Diamond'));
        $this->assertSame(13, $this->scorer->resolveRankLevel('Radiant'));
        $this->assertSame(9, $this->scorer->resolveRankLevel('Master'));
    }

    // ──────────────────────────────────────────────────────────────
    // 11. resolveRankLevel() — rang inconnu → null
    // ──────────────────────────────────────────────────────────────

    public function testResolveRankLevelForUnknownRankReturnsNull(): void
    {
        // Ces mots ne sont pas des substrings de la hiérarchie (iron/bronze/silver/gold/...)
        $this->assertNull($this->scorer->resolveRankLevel('Unranked'));
        $this->assertNull($this->scorer->resolveRankLevel(''));
        $this->assertNull($this->scorer->resolveRankLevel(null));
    }

    // ──────────────────────────────────────────────────────────────
    // 12. Jeu nul → score nul
    // ──────────────────────────────────────────────────────────────

    public function testNullGameGivesZeroGameScore(): void
    {
        $player = $this->makePlayer('', 'Duelist', 'Gold', 50.0);
        $offer = $this->makeOffer('Valorant', 'Duelist', 'Gold');

        $score = $this->scorer->computeScore($player, $offer);

        // Pas de match jeu → score réduit de 40
        $this->assertLessThan(80.0, $score);
    }
}
