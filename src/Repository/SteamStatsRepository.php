<?php

namespace App\Repository;

use App\Entity\SteamStats;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamStats>
 */
class SteamStatsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamStats::class);
    }

    public function findByUser(User $user): ?SteamStats
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findBySteamId(string $steamId): ?SteamStats
    {
        return $this->findOneBy(['steamId' => $steamId]);
    }
}
