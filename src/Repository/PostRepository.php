<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\Rubrique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * @return Post[]
     */
    public function findByRubrique(Rubrique $rubrique): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.rubrique = :rubrique')
            ->andWhere('p.statut = :statut')
            ->setParameter('rubrique', $rubrique)
            ->setParameter('statut', 'published')
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countForAdmin(?string $q): int
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)');
        $this->applySearch($qb, $q);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Post[]
     */
    public function findForAdmin(?string $q, ?string $sort, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.rubrique', 'r')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('r', 'a');
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
            $qb->andWhere('p.titre LIKE :q OR p.contenu LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }
    }

    private function applySort(\Doctrine\ORM\QueryBuilder $qb, ?string $sort): void
    {
        switch ($sort) {
            case 'title_asc':
                $qb->orderBy('p.titre', 'ASC');
                break;
            case 'title_desc':
                $qb->orderBy('p.titre', 'DESC');
                break;
            case 'date_asc':
                $qb->orderBy('p.dateCreation', 'ASC');
                break;
            case 'date_desc':
            default:
                $qb->orderBy('p.dateCreation', 'DESC');
                break;
        }
    }
}
