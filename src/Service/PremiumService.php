<?php

namespace App\Service;

use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;

class PremiumService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {
    }

    public function upgradeToFeatured(Offer $offer): void
    {
        $offer->setOfferType(Offer::TYPE_FEATURED);
        $offer->setVisibilityScore($offer->getVisibilityScore() + 300); // Base boost for featured
        
        $expiry = new \DateTime();
        $expiry->modify('+7 days');
        $offer->setPremiumExpiresAt($expiry);

        $this->entityManager->flush();

        $this->notificationService->createNotification(
            $offer->getTeam()->getOwner(),
            'OFFER_UPGRADED',
            sprintf('Success! Your offer "%s" has been upgraded to FEATURED for 7 days.', $offer->getTitle())
        );
    }

    public function upgradeToSponsored(Offer $offer, int $days = 30): void
    {
        $offer->setOfferType(Offer::TYPE_SPONSORED);
        $offer->setVisibilityScore($offer->getVisibilityScore() + 500); // Higher boost for sponsored
        
        $expiry = new \DateTime();
        $expiry->modify("+{$days} days");
        $offer->setPremiumExpiresAt($expiry);

        $this->entityManager->flush();

        $this->notificationService->createNotification(
            $offer->getTeam()->getOwner(),
            'OFFER_UPGRADED',
            sprintf('Congratulations! Your offer "%s" is now SPONSORED for %d days.', $offer->getTitle(), $days)
        );
    }

    public function revertToFree(Offer $offer): void
    {
        // Calculate the boost to remove
        $boost = $offer->isSponsored() ? 500 : ($offer->isFeatured() ? 300 : 0);
        
        $offer->setOfferType(Offer::TYPE_FREE);
        $offer->setVisibilityScore(max(0, $offer->getVisibilityScore() - $boost));
        $offer->setPremiumExpiresAt(null);

        $this->entityManager->flush();

        $this->notificationService->createNotification(
            $offer->getTeam()->getOwner(),
            'PREMIUM_EXPIRED',
            sprintf('The premium period for your offer "%s" has ended. It is now a FREE listing.', $offer->getTitle())
        );
    }
}
