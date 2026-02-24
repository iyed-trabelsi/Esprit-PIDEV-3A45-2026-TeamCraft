<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function searchByName(?string $query, string $sort = 'nomEvenement', string $direction = 'ASC', ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($query) {
            $qb->andWhere('e.nomEvenement LIKE :query')
               ->setParameter('query', $query . '%');
        }

        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('e.' . $sort, $direction)
            ->getQuery()
            ->getResult();
    }

    public function updateExpiredStatuses(): void
    {
        $this->createQueryBuilder('e')
            ->update()
            ->set('e.status', ':over')
            ->where('e.dateFin < :now')
            ->andWhere('e.status != :over')
            ->setParameter('over', 'over')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    //    /**
    //     * @return Evenement[] Returns an array of Evenement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Evenement
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
