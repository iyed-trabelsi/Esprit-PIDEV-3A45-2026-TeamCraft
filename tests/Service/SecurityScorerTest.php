<?php

namespace App\Tests\Service;

use App\Entity\LoginHistory;
use App\Entity\User;
use App\Repository\LoginHistoryRepository;
use App\Service\SecurityScorer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SecurityScorer::getRiskScore()
 *
 * Couverture :
 * - Historique vide → nouvelle ville → score élevé
 * - Même ville ET même UA → score 0 (aucun risque)
 * - Nouvelle ville → +100 pts
 * - Nouvel appareil (UA) → +30 pts
 * - Score plafonné à 100
 */
class SecurityScorerTest extends TestCase
{
    private SecurityScorer $scorer;

    /** @var MockObject&LoginHistoryRepository */
    private MockObject $historyRepo;

    private User $user;

    protected function setUp(): void
    {
        $this->historyRepo = $this->createMock(LoginHistoryRepository::class);
        $this->scorer = new SecurityScorer($this->historyRepo);
        $this->user = new User();
    }

    // ──────────────────────────────────────────────────────────────
    // Helper : crée un LoginHistory mockable
    // ──────────────────────────────────────────────────────────────

    /**
     * @return MockObject&LoginHistory
     */
    private function makeHistory(string $city, string $ua): MockObject
    {
        $h = $this->createMock(LoginHistory::class);
        $h->method('getCity')->willReturn($city);
        $h->method('getUserAgent')->willReturn($ua);
        return $h;
    }

    // ──────────────────────────────────────────────────────────────
    // 1. Historique vide → nouvelle ville détectée (score élevé)
    // ──────────────────────────────────────────────────────────────

    public function testEmptyHistoryTriggersNewCityDetection(): void
    {
        $this->historyRepo
            ->method('findBy')
            ->willReturn([]);

        $result = $this->scorer->getRiskScore(
            $this->user,
            '192.168.1.1',
            'Tunis',
            'Mozilla/5.0 Chrome/120'
        );

        // Avec historique vide, la ville simulée est "Ancienne Ville" → Tunis est nouvelle
        $this->assertGreaterThan(0, $result['score']);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertNotEmpty($result['reasons']);
        $this->assertStringContainsString('Tunis', $result['reasons'][0]);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. Même ville + même UA → score 0 (aucun risque)
    // ──────────────────────────────────────────────────────────────

    public function testKnownCityAndKnownUAReturnsZeroScore(): void
    {
        $knownCity = 'Sfax';
        $knownUA = 'Mozilla/5.0 Firefox/115';

        $history = [$this->makeHistory($knownCity, $knownUA)];

        $this->historyRepo
            ->method('findBy')
            ->willReturn($history);

        $result = $this->scorer->getRiskScore(
            $this->user,
            '10.0.0.1',
            $knownCity,
            $knownUA
        );

        $this->assertSame(0, $result['score']);
        $this->assertEmpty($result['reasons']);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Nouvelle ville → +100 pts
    // ──────────────────────────────────────────────────────────────

    public function testNewCityAdds100Points(): void
    {
        $knownUA = 'Mozilla/5.0 Chrome/119';
        $history = [$this->makeHistory('Sousse', $knownUA)];

        $this->historyRepo
            ->method('findBy')
            ->willReturn($history);

        $result = $this->scorer->getRiskScore(
            $this->user,
            '10.0.0.2',
            'Paris',    // nouvelle ville
            $knownUA    // même appareil
        );

        // Seule la ville est nouvelle → +100, score plafonné à 100
        $this->assertSame(100, $result['score']);
        $this->assertCount(1, $result['reasons']);
        $this->assertStringContainsString('Paris', $result['reasons'][0]);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Même ville + nouvel UA → +30 pts
    // ──────────────────────────────────────────────────────────────

    public function testNewUserAgentAdds30Points(): void
    {
        $knownCity = 'Tunis';
        $knownUA = 'Mozilla/5.0 Chrome/119';
        $history = [$this->makeHistory($knownCity, $knownUA)];

        $this->historyRepo
            ->method('findBy')
            ->willReturn($history);

        $result = $this->scorer->getRiskScore(
            $this->user,
            '10.0.0.3',
            $knownCity,
            'Mozilla/5.0 Safari/17' // nouvel appareil
        );

        $this->assertSame(30, $result['score']);
        $this->assertCount(1, $result['reasons']);
        $this->assertStringContainsString('appareil', $result['reasons'][0]);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Nouvelle ville + nouvel UA → score plafonné à 100
    // ──────────────────────────────────────────────────────────────

    public function testScoreIsCappedAt100(): void
    {
        $history = [$this->makeHistory('Monastir', 'Chrome Old')];

        $this->historyRepo
            ->method('findBy')
            ->willReturn($history);

        $result = $this->scorer->getRiskScore(
            $this->user,
            '10.0.0.4',
            'London',            // nouvelle ville → +100
            'Firefox New/120'    // nouvel appareil → +30
        );

        // 100 + 30 = 130 → plafonné à 100
        $this->assertSame(100, $result['score']);
        $this->assertCount(2, $result['reasons']);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Structure de retour : clés obligatoires
    // ──────────────────────────────────────────────────────────────

    public function testReturnArrayHasRequiredKeys(): void
    {
        $this->historyRepo->method('findBy')->willReturn([]);

        $result = $this->scorer->getRiskScore($this->user, '127.0.0.1', 'Ariana', 'UA');

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertIsInt($result['score']);
        $this->assertIsArray($result['reasons']);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. Multiple historique → ville connue dans la liste
    // ──────────────────────────────────────────────────────────────

    public function testKnownCityInMultipleHistoryRecordsIsOk(): void
    {
        $ua = 'Chrome/120';
        $history = [
            $this->makeHistory('Tunis', $ua),
            $this->makeHistory('Sfax', $ua),
            $this->makeHistory('Sousse', $ua),
        ];

        $this->historyRepo->method('findBy')->willReturn($history);

        $result = $this->scorer->getRiskScore($this->user, '10.0.0.5', 'Sfax', $ua);

        $this->assertSame(0, $result['score']);
    }
}
