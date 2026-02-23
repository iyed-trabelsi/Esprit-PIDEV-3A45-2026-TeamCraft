<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // Compte tous les utilisateurs
    public function countAllUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('count(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Compte selon le statut actif/inactif
    public function countActiveUsers(bool $isActive): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('count(u.id)')
            ->where('u.isActive = :status')
            ->setParameter('status', $isActive)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Récupère la répartition par genre (format pour Chart.js)
    public function countByGender(): array
    {
        $results = $this->createQueryBuilder('u')
            ->select('u.sexe as label, count(u.id) as value')
            ->groupBy('u.sexe')
            ->getQuery()
            ->getResult();

        // Formate pour que le Twig puisse lire {{ genderDistribution.homme }}
        $stats = ['homme' => 0, 'femme' => 0];
        foreach ($results as $res) {
            $label = strtolower($res['label'] ?? '');
            if (isset($stats[$label])) {
                $stats[$label] = (int) $res['value'];
            }
        }
        return $stats;
    }
    public function countBannedUsers(): int
    {
        // On se base sur le champ status de l'entité Player lié à l'User
        return (int) $this->createQueryBuilder('u')
            ->select('count(u.id)')
            ->innerJoin('u.playerProfile', 'p')
            ->where('p.status = :status')
            ->setParameter('status', 'Banned')
            ->getQuery()
            ->getSingleScalarResult();
    }
}