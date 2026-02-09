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
                // Sinon, on trie dynamiquement par la colonne reçue
                $qb->orderBy($sort, 'ASC');
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
}
