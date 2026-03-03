<?php

namespace App\Repository;

use App\Entity\Offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offer>
 *
 * @method Offer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Offer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Offer[]    findAll()
 * @method Offer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /**
     * Finds offers sorted by:
     * 1) Type Priority (SPONSORED > FEATURED > FREE)
     *    Note: Using CASE for custom order on strings
     * 2) visibilityScore DESC
     * 3) dateCreation DESC
     *
     * ✅ OPTIMISÉ : addSelect('t') charge la Team en EAGER pour éviter N+1 lazy en Twig.
     */
    public function findAllSorted(array $criteria = [], ?string $search = null, ?string $rank = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.team', 't')
            ->addSelect('t'); // ✅ EAGER : évite N requêtes lazy o.team en Twig

        foreach ($criteria as $field => $value) {
            match ($field) {
                'offerType' => $qb->andWhere('o.offerType = :offerType')->setParameter('offerType', $value),
                'game' => $qb->andWhere('o.game = :game')->setParameter('game', $value),
                'status' => $qb->andWhere('o.status = :status')->setParameter('status', $value),
                default => null,
            };
        }

        if ($search) {
            $qb->andWhere('o.title LIKE :search OR o.description LIKE :search OR t.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($rank) {
            $qb->andWhere('o.rank LIKE :rank')
                ->setParameter('rank', '%' . $rank . '%');
        }

        return $qb
            ->addSelect("(CASE 
                WHEN o.offerType = :spon THEN 3 
                WHEN o.offerType = :feat THEN 2 
                ELSE 1 END) AS HIDDEN typePriority")
            ->setParameter('spon', Offer::TYPE_SPONSORED)
            ->setParameter('feat', Offer::TYPE_FEATURED)
            ->orderBy('typePriority', 'DESC')
            ->addOrderBy('o.visibilityScore', 'DESC')
            ->addOrderBy('o.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
