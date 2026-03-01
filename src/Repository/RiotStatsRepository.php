<?php

namespace App\Repository;

use App\Entity\RiotStats;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RiotStats>
 */
class RiotStatsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RiotStats::class);
    }

    /**
     * Find stats by user and game
     */
    public function findByUserAndGame(User $user, string $game): ?RiotStats
    {
        return $this->findOneBy(['user' => $user, 'game' => $game]);
    }

    /**
     * Find all stats for a user
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    /**
     * Find stats by PUUID
     */
    public function findByPuuid(string $puuid): ?RiotStats
    {
        return $this->findOneBy(['puuid' => $puuid]);
    }

    /**
     * Find stats by Riot ID and game
     */
    public function findByRiotId(string $gameName, string $tagLine, string $game): ?RiotStats
    {
        return $this->findOneBy([
            'gameName' => $gameName,
            'tagLine' => $tagLine,
            'game' => $game
        ]);
    }
}
