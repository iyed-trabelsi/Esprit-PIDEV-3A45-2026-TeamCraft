<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CommentRepository;

class SpamDetectorService
{
    private const FAST_POSTING_THRESHOLD = 15; // seconds
    private const DUPLICATE_WINDOW = 10; // minutes
    private const MAX_REPEATED_LINKS = 3;
    
    private const SUSPICIOUS_WORDS = [
        'free money', 'casino', 'winner', 'click here', 'poker', 
        'earn money', 'bitcoin', 'crypto', 'bonus', 'gift card',
        'congratulations', 'invest', 'cash'
    ];

    public function __construct(
        private CommentRepository $commentRepository
    ) {
    }

    /**
     * Checks if a comment is considered spam.
     * Returns an array with 'isSpam' (bool) and 'reason' (string|null).
     */
    public function checkSpam(User $user, string $text): array
    {
        $text = trim($text);

        // 1. Fast Posting detection
        $lastComment = $this->commentRepository->findLastCommentByUser($user);
        if ($lastComment) {
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $lastComment->getDateCommentaire()->getTimestamp();
            if ($diff < self::FAST_POSTING_THRESHOLD) {
                return [
                    'isSpam' => true,
                    'reason' => 'Vitesse de publication trop rapide (anti-spam). Veuillez attendre quelques secondes.'
                ];
            }
        }

        // 2. Duplicate content detection
        $duplicate = $this->commentRepository->findDuplicateComment($user, $text, self::DUPLICATE_WINDOW);
        if ($duplicate) {
            return [
                'isSpam' => true,
                'reason' => 'Vous avez déjà posté ce contenu récemment. Les doublons sont interdits.'
            ];
        }

        // 3. Repeated Links detection
        if ($this->hasRepeatedLinks($text)) {
            return [
                'isSpam' => true,
                'reason' => 'Votre message contient trop de liens identiques ou suspects.'
            ];
        }

        // 4. Suspicious Words detection
        if ($this->hasSuspiciousWords($text)) {
            return [
                'isSpam' => true,
                'reason' => 'Votre message contient des termes souvent associés au spam.'
            ];
        }

        return ['isSpam' => false, 'reason' => null];
    }

    private function hasRepeatedLinks(string $text): bool
    {
        // Simple regex to find URLs
        $regex = '/https?:\/\/[^\s]+/';
        if (preg_match_all($regex, $text, $matches)) {
            $links = $matches[0];
            $counts = array_count_values($links);
            foreach ($counts as $count) {
                if ($count > self::MAX_REPEATED_LINKS) {
                    return true;
                }
            }
        }
        return false;
    }

    private function hasSuspiciousWords(string $text): bool
    {
        $textLower = mb_strtolower($text, 'UTF-8');
        foreach (self::SUSPICIOUS_WORDS as $word) {
            if (mb_strpos($textLower, $word) !== false) {
                return true;
            }
        }
        return false;
    }
}
