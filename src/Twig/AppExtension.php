<?php

namespace App\Twig;

use App\Repository\FriendRequestRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
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
}
