<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/music', name: 'api_music_')]
#[IsGranted('ROLE_USER')]
class MusicController extends AbstractController
{
    #[Route('/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        // If state is provided, use it. Otherwise toggle.
        if (isset($data['enabled'])) {
            $user->setIsMusicEnabled($data['enabled']);
        } else {
            $user->setIsMusicEnabled(!$user->isMusicEnabled());
        }

        $entityManager->flush();

        return new JsonResponse([
            'enabled' => $user->isMusicEnabled()
        ]);
    }
}
