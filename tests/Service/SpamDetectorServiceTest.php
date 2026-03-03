<?php

namespace App\Tests\Service;

use App\Entity\Comment;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Service\SpamDetectorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SpamDetectorService::checkSpam()
 *
 * Couverture :
 * - Contenu propre → pas spam
 * - Publication trop rapide
 * - Contenu dupliqué
 * - Liens répétés (>3 fois)
 * - Mots suspects (casino, bitcoin, etc.)
 * - Combinaison de règles
 */
class SpamDetectorServiceTest extends TestCase
{
    private SpamDetectorService $service;

    /** @var MockObject&CommentRepository */
    private MockObject $commentRepo;

    private User $user;

    protected function setUp(): void
    {
        $this->commentRepo = $this->createMock(CommentRepository::class);
        $this->service = new SpamDetectorService($this->commentRepo);
        $this->user = new User();
    }

    // ──────────────────────────────────────────────────────────────
    // 1. Contenu normal → pas spam
    // ──────────────────────────────────────────────────────────────

    public function testCleanTextIsNotSpam(): void
    {
        $this->commentRepo
            ->method('findLastCommentByUser')
            ->willReturn(null); // aucun précédent commentaire

        $this->commentRepo
            ->method('findDuplicateComment')
            ->willReturn(null);

        $result = $this->service->checkSpam($this->user, 'Bonjour tout le monde !', 'comment');

        $this->assertFalse($result['isSpam']);
        $this->assertNull($result['reason']);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. Publication trop rapide (< 15 secondes)
    // ──────────────────────────────────────────────────────────────

    public function testFastPostingIsDetectedAsSpam(): void
    {
        $lastComment = $this->createMock(Comment::class);
        $lastComment
            ->method('getDateCommentaire')
            ->willReturn(new \DateTime('-5 seconds')); // posté il y a 5 secondes

        $this->commentRepo
            ->method('findLastCommentByUser')
            ->willReturn($lastComment);

        $result = $this->service->checkSpam($this->user, 'Message normal', 'comment');

        $this->assertTrue($result['isSpam']);
        $this->assertStringContainsString('rapide', $result['reason']);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Publication lente (> 15 secondes) → OK
    // ──────────────────────────────────────────────────────────────

    public function testSlowPostingIsNotSpam(): void
    {
        $lastComment = $this->createMock(Comment::class);
        $lastComment
            ->method('getDateCommentaire')
            ->willReturn(new \DateTime('-30 seconds'));

        $this->commentRepo
            ->method('findLastCommentByUser')
            ->willReturn($lastComment);

        $this->commentRepo
            ->method('findDuplicateComment')
            ->willReturn(null);

        $result = $this->service->checkSpam($this->user, 'Contenu original', 'post');

        $this->assertFalse($result['isSpam']);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Contenu dupliqué → spam
    // ──────────────────────────────────────────────────────────────

    public function testDuplicateContentIsSpam(): void
    {
        $this->commentRepo
            ->method('findLastCommentByUser')
            ->willReturn(null);

        $dupComment = $this->createMock(Comment::class);
        $this->commentRepo
            ->method('findDuplicateComment')
            ->willReturn($dupComment);

        $result = $this->service->checkSpam($this->user, 'Même texte copié-collé', 'comment');

        $this->assertTrue($result['isSpam']);
        $this->assertStringContainsString('doublons', $result['reason']);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Liens répétés (> 3 fois le même lien)
    // ──────────────────────────────────────────────────────────────

    public function testRepeatedLinksIsSpam(): void
    {
        $this->commentRepo->method('findLastCommentByUser')->willReturn(null);
        $this->commentRepo->method('findDuplicateComment')->willReturn(null);

        $link = 'https://spam-site.com/buy-now';
        $text = "$link $link $link $link"; // 4 fois le même lien

        $result = $this->service->checkSpam($this->user, $text, 'comment');

        $this->assertTrue($result['isSpam']);
        $this->assertStringContainsString('liens', $result['reason']);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Liens différents (pas répétés) → pas spam
    // ──────────────────────────────────────────────────────────────

    public function testDifferentLinksAreNotSpam(): void
    {
        $this->commentRepo->method('findLastCommentByUser')->willReturn(null);
        $this->commentRepo->method('findDuplicateComment')->willReturn(null);

        $text = 'Voir https://site1.com et aussi https://site2.com pour plus d\'infos.';

        $result = $this->service->checkSpam($this->user, $text, 'post');

        $this->assertFalse($result['isSpam']);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. Mot suspect : "casino"
    // ──────────────────────────────────────────────────────────────

    public function testSuspiciousWordCasinoIsSpam(): void
    {
        $this->commentRepo->method('findLastCommentByUser')->willReturn(null);
        $this->commentRepo->method('findDuplicateComment')->willReturn(null);

        $result = $this->service->checkSpam($this->user, 'Rejoignez notre casino en ligne !', 'post');

        $this->assertTrue($result['isSpam']);
        $this->assertStringContainsString('spam', $result['reason']);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. Mot suspect : "bitcoin" (insensible à la casse)
    // ──────────────────────────────────────────────────────────────

    public function testSuspiciousWordBitcoinCaseInsensitive(): void
    {
        $this->commentRepo->method('findLastCommentByUser')->willReturn(null);
        $this->commentRepo->method('findDuplicateComment')->willReturn(null);

        $result = $this->service->checkSpam($this->user, 'BITCOIN gratuit pour tous !', 'comment');

        $this->assertTrue($result['isSpam']);
    }

    // ──────────────────────────────────────────────────────────────
    // 9. Mot suspect : "free money"
    // ──────────────────────────────────────────────────────────────

    public function testSuspiciousWordFreeMoneyIsSpam(): void
    {
        $this->commentRepo->method('findLastCommentByUser')->willReturn(null);
        $this->commentRepo->method('findDuplicateComment')->willReturn(null);

        $result = $this->service->checkSpam($this->user, 'Get free money now!', 'post');

        $this->assertTrue($result['isSpam']);
    }

    // ──────────────────────────────────────────────────────────────
    // 10. Texte vide → pas spam (juste un nettoyage trim)
    // ──────────────────────────────────────────────────────────────

    public function testEmptyTextIsNotSpam(): void
    {
        $this->commentRepo->method('findLastCommentByUser')->willReturn(null);
        $this->commentRepo->method('findDuplicateComment')->willReturn(null);

        $result = $this->service->checkSpam($this->user, '   ', 'comment');

        $this->assertFalse($result['isSpam']);
        $this->assertNull($result['reason']);
    }

    // ──────────────────────────────────────────────────────────────
    // 11. Structure du retour : clés présentes
    // ──────────────────────────────────────────────────────────────

    public function testReturnArrayHasRequiredKeys(): void
    {
        $this->commentRepo->method('findLastCommentByUser')->willReturn(null);
        $this->commentRepo->method('findDuplicateComment')->willReturn(null);

        $result = $this->service->checkSpam($this->user, 'Hello world', 'comment');

        $this->assertArrayHasKey('isSpam', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertIsBool($result['isSpam']);
    }
}
