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

        if ($q) {
            $qb->andWhere('u.pseudo LIKE :q OR p.game LIKE :q OR p.gameRank LIKE :q OR p.role LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // Default sort
        $qb->orderBy('p.id', 'DESC');

        $qb->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    public function countForAdmin(?string $q = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('count(p.id)')
            ->leftJoin('p.user', 'u');

        if ($q) {
            $qb->andWhere('u.pseudo LIKE :q OR p.game LIKE :q OR p.gameRank LIKE :q OR p.role LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
