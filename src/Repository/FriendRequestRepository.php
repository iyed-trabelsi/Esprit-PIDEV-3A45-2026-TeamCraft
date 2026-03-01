<?php

namespace App\Repository;

use App\Entity\FriendRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FriendRequest>
 *
 * @method FriendRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method FriendRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method FriendRequest[]    findAll()
 * @method FriendRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FriendRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FriendRequest::class);
    }

    public function findFriendship(User $user1, User $user2): ?FriendRequest
    {
        return $this->createQueryBuilder('fr')
            ->where('(fr.sender = :user1 AND fr.receiver = :user2) OR (fr.sender = :user2 AND fr.receiver = :user1)')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPendingRequests(User $user): array
    {
        return $this->createQueryBuilder('fr')
            ->where('fr.receiver = :user')
            ->andWhere('fr.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->orderBy('fr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingRequests(User $user): int
    {
        return $this->createQueryBuilder('fr')
            ->select('count(fr.id)')
            ->where('fr.receiver = :user')
            ->andWhere('fr.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return User[]
     */
    public function findFriends(User $user): array
    {
        $requests = $this->createQueryBuilder('fr')
            ->where('(fr.sender = :user OR fr.receiver = :user)')
            ->andWhere('fr.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getResult();

        $friends = [];
        foreach ($requests as $request) {
            if ($request->getSender() === $user) {
                $friends[] = $request->getReceiver();
            } else {
                $friends[] = $request->getSender();
            }
        }

        return $friends;
    }
}
