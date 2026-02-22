<?php

namespace App\Command;

use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:close-expired-offers',
    description: 'Automatically close offers that have passed their expiration date',
)]
class CloseExpiredOffersCommand extends Command
{
    public function __construct(
        private OfferRepository $offerRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Find all active offers that have expired
        $qb = $this->offerRepository->createQueryBuilder('o');
        $expiredOffers = $qb
            ->where('o.status = :status')
            ->andWhere('o.dateExpiration < :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        $count = count($expiredOffers);

        if ($count === 0) {
            $io->success('No expired offers found.');
            return Command::SUCCESS;
        }

        // Close each expired offer
        foreach ($expiredOffers as $offer) {
            $offer->setStatus('expired');
            $io->writeln(sprintf(
                'Closing offer #%d: %s (expired on %s)',
                $offer->getId(),
                $offer->getTitle(),
                $offer->getDateExpiration()->format('Y-m-d')
            ));
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully closed %d expired offer(s).', $count));

        return Command::SUCCESS;
    }
}
