<?php

namespace App\Repository;

use App\Entity\FavoriteOffer;
use App\Entity\User;
use App\Entity\Offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteOffer>
 */
class FavoriteOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteOffer::class);
    }

    /**
     * Find all favorite offers for a specific user.
     * ✅ EAGER : JOIN sur offer + team pour éviter N+1 lazy en Twig.
     * @return FavoriteOffer[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->leftJoin('f.offer', 'o')   // ✅ EAGER offer
            ->addSelect('o')
            ->leftJoin('o.team', 't')    // ✅ EAGER team via offer
            ->addSelect('t')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne uniquement les IDs des offres favorites d'un utilisateur.
     * ✅ UNE seule requête scalaire — remplace findBy() + N×getOffer()->getId().
     *
     * @return int[]
     */
    public function findOfferIdsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.offer) AS offerId')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'offerId');
    }

    /**
     * Check if a user has favorited a specific offer
     */
    public function isFavorited(User $user, Offer $offer): bool
    {
        $result = $this->findOneByUserAndOffer($user, $offer);
        return $result !== null;
    }

    /**
     * Find a specific favorite by user and offer
     */
    public function findOneByUserAndOffer(User $user, Offer $offer): ?FavoriteOffer
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.offer = :offer')
            ->setParameter('user', $user)
            ->setParameter('offer', $offer)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
