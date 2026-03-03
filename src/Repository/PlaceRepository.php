<?php

namespace App\Repository;

use App\Entity\Place;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Place>
 */
class PlaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Place::class);
    }

    public function searchByCapacity(?string $query, string $sort = 'capaciteMax', string $direction = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($query) {
            $qb->andWhere('p.capaciteMax = :query')
               ->setParameter('query', $query);
        }

        // ✅ SÉCURITÉ : Whitelist pour éviter l'injection SQL via ORDER BY
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        match ($sort) {
            'capaciteMax' => $qb->orderBy('p.capaciteMax', $direction),
            'nom'         => $qb->orderBy('p.nom', $direction),
            default       => $qb->orderBy('p.id', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Place[] Returns an array of Place objects
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

    //    public function findOneBySomeField($value): ?Place
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
