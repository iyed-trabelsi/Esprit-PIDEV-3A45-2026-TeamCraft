<?php

namespace App\Command;

use App\Entity\Offer;
use App\Repository\OfferRepository;
use App\Service\PremiumService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-premium-expiration',
    description: 'Revert expired premium offers to FREE status',
)]
class CheckPremiumExpirationCommand extends Command
{
    public function __construct(
        private OfferRepository $offerRepository,
        private PremiumService $premiumService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTime();

        // Find all premium offers that have expiresAt set
        $premiumOffers = $this->offerRepository->createQueryBuilder('o')
            ->where('o.offerType != :free')
            ->andWhere('o.premiumExpiresAt IS NOT NULL')
            ->setParameter('free', Offer::TYPE_FREE)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($premiumOffers as $offer) {
            if ($offer->getPremiumExpiresAt() < $now) {
                $this->premiumService->revertToFree($offer);
                $io->writeln(sprintf('Offer #%d reverted to FREE.', $offer->getId()));
                $count++;
            }
        }

        $io->success(sprintf('Premium expiration check completed. %d offers reverted.', $count));

        return Command::SUCCESS;
    }
}
