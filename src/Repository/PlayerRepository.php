<?php

namespace App\Repository;

use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }
    //    /**
//     * @return Player[] Returns an array of Player objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }
    //    public function findOneBySomeField($value): ?Player
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findForAdmin(?string $q = null, ?string $sort = null, int $limit = 10, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u');

        // 1. Recherche (Pseudo, Jeu, Rang, Rôle)
        if ($q) {
            $qb->andWhere('u.pseudo LIKE :q OR p.game LIKE :q OR p.gameRank LIKE :q OR p.role LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // 2. Filtre par Statut OU Tri par colonne
        if ($sort) {
            if (in_array($sort, ['Active', 'Inactive', 'Banned'])) {
                // Si c'est un statut, on filtre
                $qb->andWhere('p.status = :status')
                   ->setParameter('status', $sort);
                $qb->orderBy('p.id', 'DESC'); 
            } else {
                // ✅ SÉCURITÉ : Whitelist par match pour éviter ORDER BY injection
                match ($sort) {
                    'p.id' => $qb->orderBy('p.id', 'ASC'),
                    'u.pseudo' => $qb->orderBy('u.pseudo', 'ASC'),
                    'p.game' => $qb->orderBy('p.game', 'ASC'),
                    'p.gameRank' => $qb->orderBy('p.gameRank', 'ASC'),
                    'p.role' => $qb->orderBy('p.role', 'ASC'),
                    default => $qb->orderBy('p.id', 'DESC'),
                };
            }
        } else {
            // Tri par défaut
            $qb->orderBy('p.id', 'DESC');
        }

        $qb->setMaxResults($limit)
           ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    public function countForAdmin(?string $q = null, ?string $sort = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('count(p.id)')
            ->leftJoin('p.user', 'u');

        if ($q) {
            $qb->andWhere('u.pseudo LIKE :q OR p.game LIKE :q OR p.gameRank LIKE :q OR p.role LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // IMPORTANT : Le compte doit aussi filtrer par statut si un filtre est actif
        if ($sort && in_array($sort, ['Active', 'Inactive', 'Banned'])) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $sort);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find player candidates for Smart Matching (optimized query).
     * Pre-filters by game to avoid loading all players.
     * Includes players whose main game OR CompetitiveRank matches ONE of the offer's game aliases.
     *
     * @param string[] $gameAliases
     * @return Player[]
     */
    public function findCandidatesForOffer(array $gameAliases, int $limit = 50): array
    {
        if (empty($gameAliases)) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->leftJoin('p.competitiveRanks', 'cr')
            ->addSelect('cr');

        $orX = $qb->expr()->orX();

        foreach ($gameAliases as $i => $alias) {
            $pattern = '%' . strtolower(trim($alias)) . '%';
            $orX->add('LOWER(p.game) LIKE :game_' . $i);
            $orX->add('LOWER(cr.game) LIKE :game_' . $i);
            $qb->setParameter('game_' . $i, $pattern);
        }

        $qb->andWhere($orX)
           ->orderBy('p.id', 'DESC')
           ->setMaxResults($limit)
           ->distinct();

        return $qb->getQuery()->getResult();
    }
}
