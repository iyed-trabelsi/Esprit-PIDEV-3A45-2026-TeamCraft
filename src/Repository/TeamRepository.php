<?php

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    //    /**
    //     * @return Team[] Returns an array of Team objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Team
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function searchByNameOrGame(?string $query, ?string $game, ?string $sort, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.owner', 'o'); // Join owner

        if ($query) {
            $orX = $qb->expr()->orX();
            $orX->add($qb->expr()->like('t.name', ':val'));
            $orX->add($qb->expr()->like('t.games', ':val'));
            $orX->add($qb->expr()->like('o.username', ':val'));

            // Check if query matches a game label
            $gameMap = [
                'lol' => ['league', 'legend', 'lol'],
                'wow' => ['warcraft', 'wow'],
                'csgo' => ['cs', 'go', 'counter'],
                'valorant' => ['valorant'],
                'overwatch' => ['overwatch'],
                'fortnite' => ['fortnite']
            ];

            foreach ($gameMap as $code => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($query, $keyword) !== false) {
                        $orX->add($qb->expr()->like('t.games', ':game_' . $code));
                        $qb->setParameter('game_' . $code, '%' . $code . '%');
                        break; 
                    }
                }
            }

            $qb->andWhere($orX)
                ->setParameter('val', '%' . $query . '%');
        }

        if ($game) {
            $qb->andWhere('t.games LIKE :game')
                ->setParameter('game', '%' . $game . '%');
        }

        // Sorting
        if ($sort === 'name_asc') {
            $qb->orderBy('t.name', 'ASC');
        } elseif ($sort === 'name_desc') {
            $qb->orderBy('t.name', 'DESC');
        } else {
            $qb->orderBy('t.id', 'DESC');
        }

        $qb->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    public function countSearchByNameOrGame(?string $query, ?string $game): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->leftJoin('t.owner', 'o');

        if ($query) {
            $orX = $qb->expr()->orX();
            $orX->add($qb->expr()->like('t.name', ':val'));
            $orX->add($qb->expr()->like('t.games', ':val'));
            $orX->add($qb->expr()->like('o.username', ':val'));

            // Check if query matches a game label
            $gameMap = [
                'lol' => ['league', 'legend', 'lol'],
                'wow' => ['warcraft', 'wow'],
                'csgo' => ['cs', 'go', 'counter'],
                'valorant' => ['valorant'],
                'overwatch' => ['overwatch'],
                'fortnite' => ['fortnite']
            ];

            foreach ($gameMap as $code => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($query, $keyword) !== false) {
                        $orX->add($qb->expr()->like('t.games', ':game_' . $code));
                        $qb->setParameter('game_' . $code, '%' . $code . '%');
                        break; 
                    }
                }
            }

            $qb->andWhere($orX)
                ->setParameter('val', '%' . $query . '%');
        }

        if ($game) {
            $qb->andWhere('t.games LIKE :game')
                ->setParameter('game', '%' . $game . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Team[] Returns an array of Team objects where user is a member
     */
    public function findTeamsByMember(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.members', 'm')
            ->where('m.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getResult();
    }
}
