<?php

namespace App\Repository;

use App\Entity\Rubrique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rubrique>
 */
class RubriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rubrique::class);
    }

    /**
     * @return Rubrique[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.etat = :etat')
            ->setParameter('etat', 'active')
            ->orderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Rubrique[]
     */
    public function findByAuteur(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.auteur = :user')
            ->setParameter('user', $user)
            ->orderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countForAdmin(?string $q): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');
        $this->applySearch($qb, $q);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Rubrique[]
     */
    public function findForAdmin(?string $q, ?string $sort, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('r');
        $this->applySearch($qb, $q);
        $this->applySort($qb, $sort);
        return $qb
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    private function applySearch(\Doctrine\ORM\QueryBuilder $qb, ?string $q): void
    {
        if ($q !== null && $q !== '') {
            $qb->andWhere('r.nomRubrique LIKE :q OR r.description LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }
    }

    private function applySort(\Doctrine\ORM\QueryBuilder $qb, ?string $sort): void
    {
        switch ($sort) {
            case 'name_asc':
                $qb->orderBy('r.nomRubrique', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('r.nomRubrique', 'DESC');
                break;
            case 'date_asc':
                $qb->orderBy('r.dateCreation', 'ASC');
                break;
            case 'date_desc':
            default:
                $qb->orderBy('r.dateCreation', 'DESC');
                break;
        }
    }
}
