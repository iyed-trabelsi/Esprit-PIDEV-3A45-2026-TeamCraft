<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function searchByName(?string $query, string $sort = 'nomEvenement', string $direction = 'ASC', ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($query) {
            $qb->andWhere('e.nomEvenement LIKE :query')
                ->setParameter('query', $query . '%');
        }

        if ($status) {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        // ✅ SÉCURITÉ : Whitelist pour éviter l'injection SQL via ORDER BY
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        match ($sort) {
            'nomEvenement' => $qb->orderBy('e.nomEvenement', $direction),
            'dateDebut'    => $qb->orderBy('e.dateDebut', $direction),
            'nbParticipants' => $qb->orderBy('e.nbParticipants', $direction),
            default        => $qb->orderBy('e.id', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Chargement EAGER de place + participations pour éviter N+1.
     * Utilisé dans FrontEventController::index() et updateEventStatus().
     *
     * @return Evenement[]
     */
    public function findAllWithPlaceAndParticipations(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.place', 'pl')
            ->addSelect('pl')
            ->leftJoin('e.participations', 'pa')
            ->addSelect('pa')
            ->orderBy('e.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Chargement EAGER pour l'API calendrier : place + participations + user du participant.
     * Élimine le N+1 de calendarData().
     *
     * @return Evenement[]
     */
    public function findAllForCalendar(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.place', 'pl')
            ->addSelect('pl')
            ->leftJoin('e.participations', 'pa')
            ->addSelect('pa')
            ->leftJoin('pa.user', 'u')
            ->addSelect('u')
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Chargement des événements d'un organisateur avec place + participations.
     * Élimine le N+1 de myEventsManage() et index().
     *
     * @return Evenement[]
     */
    public function findByOrganisateurWithRelations(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.organisateur = :user')
            ->setParameter('user', $user)
            ->leftJoin('e.place', 'pl')
            ->addSelect('pl')
            ->leftJoin('e.participations', 'pa')
            ->addSelect('pa')
            ->orderBy('e.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function updateExpiredStatuses(): void
    {
        $this->createQueryBuilder('e')
            ->update()
            ->set('e.status', ':over')
            ->where('e.dateFin < :now')
            ->andWhere('e.status != :over')
            ->setParameter('over', 'over')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}
