<?php

namespace App\Repository;

use App\Entity\MediaSubmission;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MediaSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaSubmission::class);
    }

    public function findLatestForUser(User $user, bool $isOwner, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (!$isOwner) {
            $qb->andWhere('m.visibility = :visibility')
                ->setParameter('visibility', 'public');
        }

        return $qb->getQuery()->getResult();
    }
}

