<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use App\Repository\FriendRequestRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private $friendRequestRepository;
    private $security;

    public function __construct(FriendRequestRepository $friendRequestRepository, Security $security)
    {
        $this->friendRequestRepository = $friendRequestRepository;
        $this->security = $security;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_navbar_data', [$this, 'getNavbarData']),
        ];
    }

    public function getNavbarData(): array
    {
        $user = $this->security->getUser();

        if (!$user) {
            return [
                'friendRequestsCount' => 0,
                'notificationsCount' => 0,
            ];
        }

        $friendRequestsCount = $this->friendRequestRepository->count([
            'receiver' => $user,
            'status' => 'pending'
        ]);

        return [
            'friendRequestsCount' => $friendRequestsCount,
            'notificationsCount' => 0, // Placeholder for future notifications system
        ];
    }
    public function getFilters(): array
    {
        return [
            new TwigFilter('ago', [$this, 'timeAgo']),
        ];
    }

    public function timeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $interval = $now->diff($date);

        if ($interval->y > 0) {
            return $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
        }
        if ($interval->m > 0) {
            return $interval->m . ' mois';
        }
        if ($interval->d > 0) {
            return $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
        }
        if ($interval->h > 0) {
            return $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
        }
        if ($interval->i > 0) {
            return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        }
        
        return 'à l\'instant';
    }
}



    