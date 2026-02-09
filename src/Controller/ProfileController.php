<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Check if the user is a player and not an admin
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($request->isMethod('POST')) {
            $user->setPseudo($request->request->get('pseudo'));
            $user->setName($request->request->get('name'));
            $user->setEmail($request->request->get('email'));
            $user->setSexe($request->request->get('sexe'));
            $user->setBio($request->request->get('bio'));
            $user->setCountry($request->request->get('country'));

            $player = $user->getPlayerProfile();
            if ($player) {
                $player->setSelectedGames($request->request->all('games'));
            }

            $entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_profile');
        }

        // Real data from entity
        $playerInfo = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'country' => $user->getCountry() ?? 'France',
            'joinedAt' => '12 Janvier 2024', // Static for now
        ];

        $player = $user->getPlayerProfile();
        $selectedGames = $player ? $player->getSelectedGames() : [];

        $playerPreferences = [
            'activeGames' => $selectedGames,
            'availability' => 'Soirs & week-end',
        ];

        $teamStatus = [
            'hasTeam' => true,
            'teamName' => 'Phoenix Squad',
            'players' => [
                ['name' => 'Miky', 'role' => 'Duelist', 'status' => 'Online'],
                ['name' => 'ViperStrike', 'role' => 'Initiator', 'status' => 'In Game'],
                ['name' => 'GhostOp', 'role' => 'Controller', 'status' => 'Offline'],
                ['name' => 'Kira.exe', 'role' => 'Sentinel', 'status' => 'Online'],
            ]
        ];

        $competitiveRank = [
            'game' => 'VALORANT',
            'rank' => 'Diamond 1',
            'role' => 'Duelist / Entry',
            'peakRank' => 'Diamond II',
        ];

        $postHistory = [
            [
                'title' => 'bbbbbbbbbbb',
                'excerpt' => 'Recherche de scrims ou d’une équipe pour le tournoi du 15 Mars.',
                'views' => 34,
                'likes' => 7,
                'status' => 'Actif',
                'image' => null,
            ]
        ];

        $forumHistory = [
            [
                'category' => 'General',
                'topic' => 'general discussion',
                'description' => 'Discuss the latest changes in the esports scene here.',
                'comments' => [
                    ['author' => 'ViperStrike', 'time' => '42 min ago', 'content' => 'Does anyone else feel like the current meta is a bit stagnant?'],
                    ['author' => 'GhostOp', 'time' => '15 min ago', 'content' => 'You mean the LoL meta? I feel like...'],
                ]
            ]
        ];

        return $this->render('frontoffice/profile/index.html.twig', [
            'playerInfo' => $playerInfo,
            'playerPreferences' => $playerPreferences,
            'teamStatus' => $teamStatus,
            'competitiveRank' => $competitiveRank,
            'postHistory' => $postHistory,
            'forumHistory' => $forumHistory,
            'user' => $user, // Pass the user object for the form
        ]);
    }
}
