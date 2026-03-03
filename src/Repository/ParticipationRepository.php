<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * @return Participation[]
     */
    public function findAllWithEventAndUser(string $sort = 'p.dateInscription', string $direction = 'DESC', ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.evenement', 'e')
            ->addSelect('e')
            ->leftJoin('p.user', 'u')
            ->addSelect('u');

        if ($query) {
            $qb->andWhere('u.username LIKE :query')
               ->setParameter('query', $query . '%');
        }

        // ✅ SÉCURITÉ : Whitelist pour éviter l'injection SQL via ORDER BY
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        match ($sort) {
            'p.dateInscription' => $qb->orderBy('p.dateInscription', $direction),
            'u.username'        => $qb->orderBy('u.username', $direction),
            'e.nomEvenement'    => $qb->orderBy('e.nomEvenement', $direction),
            default             => $qb->orderBy('p.id', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Participation[] Returns an array of Participation objects
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

    //    public function findOneBySomeField($value): ?Participation
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
