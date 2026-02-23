<?php

namespace App\Command;

use App\Entity\Offer;
use App\Repository\OfferRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-offer-expiration',
    description: 'Check for expired offers and handle team inactivity',
)]
class CheckOfferExpirationCommand extends Command
{
    public function __construct(
        private OfferRepository $offerRepository,
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        // 1. Handle Expiration (30 days from activation if ACTIVE)
        $activeOffers = $this->offerRepository->findBy(['status' => Offer::STATUS_ACTIVE]);
        
        foreach ($activeOffers as $offer) {
            $activatedAt = $offer->getActivatedAt();
            if (!$activatedAt) {
                // Should not happen if logic is correct, but let's be safe
                continue;
            }

            $diff = $now->diff($activatedAt)->days;

            // Auto-expire after 30 days
            if ($diff >= 30) {
                $offer->setStatus(Offer::STATUS_EXPIRED);
                $this->notificationService->createNotification(
                    $offer->getTeam()->getOwner(),
                    'OFFER_EXPIRED',
                    sprintf('Your offer "%s" has expired after 30 days.', $offer->getTitle())
                );
                $io->writeln(sprintf('Offer #%d expired.', $offer->getId()));
                continue; // Move to next offer
            }

            // Notify 3 days before expiration (at day 27)
            if ($diff === 27) {
                // Check if already notified to avoid spam if command runs multiple times
                // Simplification for now: send if it's exactly 27 days diff in days calc
                $this->notificationService->createNotification(
                    $offer->getTeam()->getOwner(),
                    'OFFER_EXPIRING_SOON',
                    sprintf('Your offer "%s" will expire in 3 days.', $offer->getTitle())
                );
                $io->writeln(sprintf('Notified owner of offer #%d about upcoming expiration.', $offer->getId()));
            }

            // 2. Handle Team Inactivity (15 days inactive owner)
            $owner = $offer->getTeam()->getOwner();
            $lastActivity = $owner->getLastActivityAt();
            
            if ($lastActivity) {
                $inactivityDays = $now->diff($lastActivity)->days;
                if ($inactivityDays >= 15) {
                    $offer->setStatus(Offer::STATUS_PAUSED);
                    $this->notificationService->createNotification(
                        $owner,
                        'OFFER_AUTO_PAUSED',
                        sprintf('Your offer "%s" has been paused due to 15 days of inactivity.', $offer->getTitle())
                    );
                    $io->writeln(sprintf('Offer #%d paused due to inactivity of owner.', $offer->getId()));
                }
            }
        }

        $this->entityManager->flush();
        $io->success('Offer lifecycle check completed.');

        return Command::SUCCESS;
    }
}
