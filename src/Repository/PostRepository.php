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
    public function findByRubrique(Rubrique $rubrique, ?\App\Entity\User $user = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a') // ✅ EAGER : évite N+1 sur auteur en Twig
            ->addSelect('a')
            ->andWhere('p.rubrique = :rubrique')
            ->setParameter('rubrique', $rubrique);

        // On autorise "published" et "pending_review" pour tout le monde
        // "archived" reste réservé à l'auteur
        $allowedStatuses = ['published', 'pending_review'];

        if ($user) {
            $qb->andWhere('p.statut IN (:public_statuses) OR (p.statut = :archived AND p.auteur = :user)')
                ->setParameter('public_statuses', $allowedStatuses)
                ->setParameter('archived', 'archived')
                ->setParameter('user', $user);
        } else {
            $qb->andWhere('p.statut IN (:public_statuses)')
                ->setParameter('public_statuses', $allowedStatuses);
        }

        return $qb->orderBy('p.dateCreation', 'DESC')
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
    public function findLikedByUser(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.likedBy', 'u')
            ->where('u = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findCandidatesForRecommendation(\App\Entity\User $user, int $limit = 30): array
    {
        // Subquery to get IDs of posts already liked by the user
        $likedIds = $this->createQueryBuilder('p2')
            ->select('p2.id')
            ->innerJoin('p2.likedBy', 'u2')
            ->where('u2 = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $likedIdsArr = array_column($likedIds, 'id');

        $qb = $this->createQueryBuilder('p')
            ->where('p.statut = :status')
            ->setParameter('status', 'published')
            ->andWhere('p.auteur != :user')
            ->setParameter('user', $user);

        if (!empty($likedIdsArr)) {
            $qb->andWhere('p.id NOT IN (:likedIds)')
                ->setParameter('likedIds', $likedIdsArr);
        }

        return $qb->orderBy('p.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentPopularPosts(int $limit = 4, ?\App\Entity\User $user = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')   // ✅ EAGER : évite N+1 auteur
            ->addSelect('a')
            ->leftJoin('p.rubrique', 'r') // ✅ EAGER : évite N+1 rubrique
            ->addSelect('r')
            ->where('p.statut = :status')
            ->setParameter('status', 'published');

        if ($user) {
            // Exclude posts liked by the user
            $likedIds = $this->createQueryBuilder('p2')
                ->select('p2.id')
                ->innerJoin('p2.likedBy', 'u2')
                ->where('u2 = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();

            $likedIdsArr = array_column($likedIds, 'id');

            if (!empty($likedIdsArr)) {
                $qb->andWhere('p.id NOT IN (:likedIds)')
                    ->setParameter('likedIds', $likedIdsArr);
            }
        }

        return $qb->orderBy('p.nbVues', 'DESC')
            ->addOrderBy('p.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts posts by type, optionally filtered by a search query.
     * 
     * @param string|null $q
     * @return array Array of arrays with 'type' and 'count' keys
     */
    public function countByType(?string $q = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.typePost as type, COUNT(p.id) as count')
            ->groupBy('p.typePost');

        if ($q !== null && $q !== '') {
            $qb->andWhere('p.titre LIKE :q OR p.contenu LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
