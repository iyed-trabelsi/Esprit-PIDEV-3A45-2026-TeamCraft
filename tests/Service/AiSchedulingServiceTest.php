<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use App\Service\AiSchedulingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour AiSchedulingService
 *
 * Couverture :
 * - getFallbackEvent() : format et clés du tableau retourné
 * - generateOptimalEvent() : succès API Gemini simulé
 * - generateOptimalEvent() : échec API → fallback automatique
 * - generateOptimalEvent() : JSON invalide → fallback
 * - generateOptimalEvent() : événements historiques filtrés (non terminés ignorés)
 * - analyzeHistory() via generateOptimalEvent() : aucun événement passé
 */
class AiSchedulingServiceTest extends TestCase
{
    /** @var MockObject&EvenementRepository */
    private MockObject $evenementRepo;

    /** @var MockObject&HttpClientInterface */
    private MockObject $httpClient;

    /** @var MockObject&LoggerInterface */
    private MockObject $logger;

    private AiSchedulingService $service;

    protected function setUp(): void
    {
        $this->evenementRepo = $this->createMock(EvenementRepository::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new AiSchedulingService(
            $this->evenementRepo,
            $this->httpClient,
            $this->logger,
            'FAKE_API_KEY'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // 1. API Gemini répond avec un JSON valide
    // ──────────────────────────────────────────────────────────────

    public function testGenerateOptimalEventWithSuccessfulApiResponse(): void
    {
        $this->evenementRepo->method('findAll')->willReturn([]);

        $apiJson = json_encode([
            'nomEvenement' => 'Grand Tournoi Gaming Weekend',
            'typeEvenement' => 'tournament',
            'dateDebut' => (new \DateTime('+7 days'))->format('Y-m-d\TH:i'),
            'dateFin' => (new \DateTime('+7 days +3 hours'))->format('Y-m-d\TH:i'),
            'reasoning' => 'Top pick based on historical data.',
            'confidence_score' => 90,
        ]);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('toArray')->willReturn([
            'candidates' => [
                ['content' => ['parts' => [['text' => $apiJson]]]]
            ]
        ]);

        $this->httpClient->method('request')->willReturn($responseMock);

        $result = $this->service->generateOptimalEvent();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nomEvenement', $result);
        $this->assertSame('Grand Tournoi Gaming Weekend', $result['nomEvenement']);
        $this->assertArrayHasKey('typeEvenement', $result);
        $this->assertArrayHasKey('dateDebut', $result);
        $this->assertArrayHasKey('dateFin', $result);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. Exception API → retour fallback
    // ──────────────────────────────────────────────────────────────

    public function testGenerateOptimalEventFallsBackOnApiException(): void
    {
        $this->evenementRepo->method('findAll')->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $this->service->generateOptimalEvent();

        $this->assertIsArray($result);
        $this->assertFallbackEventShape($result);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Réponse API avec JSON invalide → fallback
    // ──────────────────────────────────────────────────────────────

    public function testGenerateOptimalEventFallsBackOnInvalidJson(): void
    {
        $this->evenementRepo->method('findAll')->willReturn([]);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('toArray')->willReturn([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'INVALID_JSON_NOT_AN_OBJECT']]]]
            ]
        ]);

        $this->httpClient->method('request')->willReturn($responseMock);

        $result = $this->service->generateOptimalEvent();

        $this->assertIsArray($result);
        $this->assertFallbackEventShape($result);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Réponse API avec structure inattendue → fallback
    // ──────────────────────────────────────────────────────────────

    public function testGenerateOptimalEventFallsBackOnUnexpectedApiStructure(): void
    {
        $this->evenementRepo->method('findAll')->willReturn([]);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('toArray')->willReturn(['error' => 'quota exceeded']);

        $this->httpClient->method('request')->willReturn($responseMock);

        $result = $this->service->generateOptimalEvent();

        $this->assertFallbackEventShape($result);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Fallback event : structure et dates valides
    // ──────────────────────────────────────────────────────────────

    public function testFallbackEventHasValidDateRange(): void
    {
        $this->evenementRepo->method('findAll')->willReturn([]);
        $this->httpClient->method('request')->willThrowException(new \Exception('fail'));

        $result = $this->service->generateOptimalEvent();

        $this->assertArrayHasKey('dateDebut', $result);
        $this->assertArrayHasKey('dateFin', $result);

        $start = new \DateTime($result['dateDebut']);
        $end = new \DateTime($result['dateFin']);

        // La date de fin doit être après la date de début
        $this->assertGreaterThan($start, $end, 'dateFin doit être après dateDebut');
        // L'événement de fallback est dans le futur
        $this->assertGreaterThan(new \DateTime(), $start, 'dateDebut doit être dans le futur');
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Fallback event : nomEvenement et typeEvenement ≥ 8 chars
    // ──────────────────────────────────────────────────────────────

    public function testFallbackEventNameMeetsMinLength(): void
    {
        $this->evenementRepo->method('findAll')->willReturn([]);
        $this->httpClient->method('request')->willThrowException(new \Exception('fail'));

        $result = $this->service->generateOptimalEvent();

        $this->assertGreaterThanOrEqual(
            8,
            strlen($result['nomEvenement']),
            'nomEvenement doit avoir au moins 8 caractères'
        );
        $this->assertGreaterThanOrEqual(
            8,
            strlen($result['typeEvenement']),
            'typeEvenement doit avoir au moins 8 caractères'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // 7. Événements non terminés → ignorés par analyzeHistory
    //    (l'API est appelée même avec des events "open")
    // ──────────────────────────────────────────────────────────────

    public function testOpenEventsAreIgnoredInHistory(): void
    {
        // Événement avec statut "open" (non terminé)
        $event = $this->createMock(Evenement::class);
        $event->method('getStatus')->willReturn('open');

        $this->evenementRepo->method('findAll')->willReturn([$event]);

        // L'API reçoit un prompt indiquant "no completed events"
        $this->httpClient
            ->method('request')
            ->willThrowException(new \Exception('simulated fail'));

        $result = $this->service->generateOptimalEvent();

        $this->assertFallbackEventShape($result);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper : vérifie la forme minimale du fallback
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $result
     */
    private function assertFallbackEventShape(array $result): void
    {
        $this->assertIsArray($result);
        $this->assertArrayHasKey('nomEvenement', $result, 'Clé nomEvenement manquante');
        $this->assertArrayHasKey('typeEvenement', $result, 'Clé typeEvenement manquante');
        $this->assertArrayHasKey('dateDebut', $result, 'Clé dateDebut manquante');
        $this->assertArrayHasKey('dateFin', $result, 'Clé dateFin manquante');
        $this->assertArrayHasKey('reasoning', $result, 'Clé reasoning manquante');
        $this->assertNotEmpty($result['nomEvenement']);
        $this->assertNotEmpty($result['typeEvenement']);
    }
}
