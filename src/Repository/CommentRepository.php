<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @return Comment[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.post', 'p')
            ->leftJoin('p.rubrique', 'r')
            ->addSelect('p', 'r')
            ->orderBy('c.dateCommentaire', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countForAdmin(?string $q): int
    {
        $qb = $this->createQueryBuilder('c')->select('COUNT(c.id)');
        $this->applySearch($qb, $q);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Comment[]
     */
    public function findForAdminPaginated(?string $q, ?string $sort, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.post', 'p')
            ->leftJoin('p.rubrique', 'r')
            ->leftJoin('c.auteur', 'u')
            ->addSelect('p', 'r', 'u');
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
            $qb->andWhere('c.contenu LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }
    }

    private function applySort(\Doctrine\ORM\QueryBuilder $qb, ?string $sort): void
    {
        switch ($sort) {
            case 'date_asc':
                $qb->orderBy('c.dateCommentaire', 'ASC');
                break;
            case 'date_desc':
            default:
                $qb->orderBy('c.dateCommentaire', 'DESC');
                break;
        }
    }

    /**
     * Top-level comments for a post. Hides pending_review comments from others; author sees their own.
     *
     * @return Comment[]
     */
    public function findTopLevelByPost(Post $post, ?\App\Entity\User $currentUser = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.post = :post')
            ->andWhere('c.parent IS NULL')
            ->setParameter('post', $post)
            ->orderBy('c.dateCommentaire', 'ASC');

        if ($currentUser !== null) {
            $qb->andWhere('c.moderationStatus IS NULL OR (c.moderationStatus = :pending AND c.auteur = :user)')
               ->setParameter('pending', 'pending_review')
               ->setParameter('user', $currentUser);
        } else {
            $qb->andWhere('c.moderationStatus IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Finds the last comment by a user to check for fast posting.
     */
    public function findLastCommentByUser(\App\Entity\User $user): ?Comment
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.auteur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCommentaire', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Checks if a user has posted the exact same content recently.
     */
    public function findDuplicateComment(\App\Entity\User $user, string $contenu, int $minutes = 10): ?Comment
    {
        $date = new \DateTime();
        $date->modify("-" . $minutes . " minutes");

        return $this->createQueryBuilder('c')
            ->andWhere('c.auteur = :user')
            ->andWhere('c.contenu = :contenu')
            ->andWhere('c.dateCommentaire >= :date')
            ->setParameter('user', $user)
            ->setParameter('contenu', trim($contenu))
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
