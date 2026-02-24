<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\LoginHistoryRepository;

class SecurityScorer
{
    private $historyRepository;

    public function __construct(LoginHistoryRepository $historyRepository)
    {
        $this->historyRepository = $historyRepository;
    }

    public function getRiskScore(User $user, string $currentIp, string $currentCity, string $currentUA): array
    {
        // hedheya test : return ['score' => 100, 'reasons' => ['TEST FORCE']];
        $history = $this->historyRepository->findBy(['user' => $user], ['createdAt' => 'DESC'], 5);
        
        $score = 0;
        $reasons = [];

        // Si historique vide, on simule une ancienne ville différente pour déclencher le score
        $knownCities = count($history) > 0 ? array_map(fn($h) => $h->getCity(), $history) : ['Ancienne Ville'];
        
        // 1. Analyse de la Ville (Poids : 100 points pour le test)
        if (!in_array($currentCity, $knownCities)) {
            $score += 100;
            $reasons[] = "Nouvelle ville détectée : $currentCity";
        }

        // 2. Analyse du Navigateur
        $knownUAs = array_map(fn($h) => $h->getUserAgent(), $history);
        if (count($history) > 0 && !in_array($currentUA, $knownUAs)) {
            $score += 30;
            $reasons[] = "Nouvel appareil";
        }

        return [
            'score' => min($score, 100),
            'reasons' => $reasons
        ];
    }
}