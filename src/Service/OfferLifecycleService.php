<?php

namespace App\Service;

use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;

class OfferLifecycleService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private PremiumService $premiumService
    ) {
    }

    public function changeStatus(Offer $offer, string $newStatus): void
    {
        $oldStatus = $offer->getStatus();
        if ($oldStatus === $newStatus) {
            return;
        }

        $offer->setStatus($newStatus);

        // Record activation time if moving to ACTIVE
        if ($newStatus === Offer::STATUS_ACTIVE && !$offer->getActivatedAt()) {
            $offer->setActivatedAt(new \DateTime());
        }

        $this->entityManager->flush();

        $this->notificationService->createNotification(
            $offer->getTeam()->getOwner(),
            'OFFER_STATUS_CHANGED',
            sprintf('The status of your offer "%s" has been changed to %s.', $offer->getTitle(), $newStatus)
        );
    }

    public function handlePremiumExpiration(Offer $offer): void
    {
        if ($offer->getPremiumExpiresAt() && $offer->getPremiumExpiresAt() < new \DateTime()) {
            $this->premiumService->revertToFree($offer);
        }
    }
}
