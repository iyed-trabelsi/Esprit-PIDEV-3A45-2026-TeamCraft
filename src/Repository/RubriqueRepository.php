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
    /**
     * @return Rubrique[]
     */
    public function findAllActive(string $sortDirection = 'DESC'): array
    {
        $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';

        return $this->createQueryBuilder('r')
            ->andWhere('r.etat = :etat')
            ->setParameter('etat', 'active')
            ->orderBy('r.dateCreation', $sortDirection)
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
    public function findDistinctTopics(): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('DISTINCT r.topic as topic')
            ->where('r.topic IS NOT NULL')
            ->andWhere("r.topic != ''")
            ->orderBy('r.topic', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'topic');
    }

    public function countByTopic(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.topic as topic, COUNT(r.id) as count')
            ->where('r.topic IS NOT NULL')
            ->andWhere("r.topic != ''")
            ->groupBy('r.topic')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function countByState(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.etat as state, COUNT(r.id) as count')
            ->groupBy('r.etat')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne toutes les rubriques avec le COUNT de leurs posts en UNE seule requête.
     * Élimine le N+1 de AdminController::syncCounts() qui faisait findAll()
     * puis count($rubrique->getPosts()) pour chaque rubrique (N requêtes lazy).
     *
     * @return array<int, array{rubrique: \App\Entity\Rubrique, postCount: int}>
     */
    public function findAllWithPostsCount(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r, COUNT(p.id) AS postCount')
            ->leftJoin('r.posts', 'p')
            ->groupBy('r.id')
            ->orderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
