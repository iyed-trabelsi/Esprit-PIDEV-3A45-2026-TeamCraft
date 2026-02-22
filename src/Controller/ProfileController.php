<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\CompetitiveRank;

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
            // Only update user profile if these fields are present (Edit Profile form)
            if ($request->request->has('pseudo')) {
                $pseudo = $request->request->get('pseudo');
                $name = $request->request->get('name');
                $email = $request->request->get('email');

                // Validation
                $params = ['pseudo' => $pseudo, 'name' => $name, 'email' => $email];
                $validationErrors = [];

                if (empty($pseudo)) {
                    $validationErrors[] = 'Pseudo is required.';
                } elseif (strlen($pseudo) < 3 || strlen($pseudo) > 20) {
                    $validationErrors[] = 'Pseudo must be between 3 and 20 characters.';
                } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $pseudo)) {
                    $validationErrors[] = 'Pseudo must contain only letters, numbers, and underscores.';
                }

                if (empty($name)) {
                    $validationErrors[] = 'Name is required.';
                }
                if (empty($email)) {
                    $validationErrors[] = 'Email is required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validationErrors[] = 'Invalid email format.';
                }

                if (!empty($validationErrors)) {
                    foreach ($validationErrors as $error) {
                        $this->addFlash('error', $error);
                    }
                    // Do not redirect, let it fall through to render the view with the user's input/modified state
                    // The $user object has the modified values (pseudo, name, email) set above.
                    // We prevent flushing to DB below.
                } else {
                    $user->setPseudo($pseudo);
                    $user->setName($name);
                    $user->setEmail($email);
                    $user->setSexe($request->request->get('sexe'));
                    $user->setBio($request->request->get('bio'));
                    $user->setCountry($request->request->get('country'));

                    // Handle profile picture upload
                    $profilePicture = $request->files->get('profilePicture');
                    if ($profilePicture) {
                        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                        if (!is_dir($uploadsDir)) {
                            mkdir($uploadsDir, 0777, true);
                        }

                        $filename = uniqid() . '.' . $profilePicture->guessExtension();
                        $profilePicture->move($uploadsDir, $filename);
                        $user->setProfilePicture('/uploads/profiles/' . $filename);
                    }

                    $player = $user->getPlayerProfile();
                    if ($player) {
                        $games = $request->request->all('games');
                        $player->setSelectedGames($games);
                    }

                    $entityManager->flush();
                    $this->addFlash('success', 'Profile updated successfully!');
                    return $this->redirectToRoute('app_profile');
                }
            } elseif ($request->request->has('competitive_game')) {
                $game = $request->request->get('competitive_game');
                $role = $request->request->get('principal_role');
                $level = $request->request->get('skill_level');
                $experience = $request->request->get('experience');
                $availability = $request->request->get('availability');
                $region = $request->request->get('region');

                $rankErrors = [];
                if (empty($game))
                    $rankErrors[] = 'Le jeu est requis.';
                if (empty($role))
                    $rankErrors[] = 'Le rôle principal est requis.';
                if (empty($level))
                    $rankErrors[] = 'Le niveau de jeu est requis.';
                if (empty($experience))
                    $rankErrors[] = 'L\'expérience est requise.';
                if (empty($availability))
                    $rankErrors[] = 'La disponibilité est requise.';
                if (empty($region))
                    $rankErrors[] = 'La région est requise.';

                if (!empty($rankErrors)) {
                    foreach ($rankErrors as $error) {
                        $this->addFlash('error', $error);
                    }
                } else {
                    $competitiveRank = new \App\Entity\CompetitiveRank();
                    $competitiveRank->setGame($game);
                    $competitiveRank->setPrincipalRole($role);
                    $competitiveRank->setSkillLevel($level);
                    $competitiveRank->setExperience($experience);
                    $competitiveRank->setAvailability($availability);
                    $competitiveRank->setRegion($region);

                    $player = $user->getPlayerProfile();
                    if (!$player) {
                        $player = new \App\Entity\Player();
                        $player->setUser($user);
                        $entityManager->persist($player);
                    }

                    $player->addCompetitiveRank($competitiveRank);
                    $entityManager->persist($competitiveRank);
                    $entityManager->flush();
                    $this->addFlash('success', 'Competitive rank created successfully!');
                    return $this->redirectToRoute('app_profile');
                }
            }
        }

        return $this->render('frontoffice/profile/index.html.twig', $this->prepareProfileData($user));
    }

    #[Route('/profile/{id}', name: 'app_profile_show', methods: ['GET'])]
    public function show(\App\Entity\Player $player): Response
    {
        $user = $player->getUser();
        if (!$user) {
            throw $this->createNotFoundException('User not found for this player.');
        }

        return $this->render('frontoffice/profile/index.html.twig', $this->prepareProfileData($user));
    }

    private function prepareProfileData(\App\Entity\User $user): array
    {
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


        // Fetch competitive ranks from DB
        $competitiveRanks = [];
        if ($player) {
            $ranks = $player->getCompetitiveRanks();
            if (!$ranks->isEmpty()) {
                $gameLogos = [
                    'valorant' => '/images/games/valorant.png',
                    'lol' => '/images/games/lol.png',
                    'cs2' => '/images/games/cs2.png',
                ];

                foreach ($ranks as $rank) {
                    $competitiveRanks[] = [
                        'id' => $rank->getId(),
                        'game' => strtoupper($rank->getGame()),
                        'game_key' => $rank->getGame(), // Keep original for logos/logic
                        'rank' => $rank->getSkillLevel(),
                        'role' => $rank->getPrincipalRole(),
                        'experience' => $rank->getExperience(),
                        'availability' => $rank->getAvailability(),
                        'logo' => $gameLogos[$rank->getGame()] ?? null,
                        'region' => $rank->getRegion(),
                    ];
                }
            }
        }

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

        return [
            'playerInfo' => $playerInfo,
            'playerPreferences' => $playerPreferences,
            'teamStatus' => $teamStatus,
            'competitiveRanks' => $competitiveRanks,
            'postHistory' => $postHistory,
            'forumHistory' => $forumHistory,
            'user' => $user, // Pass the user object for the form
        ];
    }

    #[Route('/profile/rank/edit/{id}', name: 'app_profile_rank_edit', methods: ['POST'])]
    public function editRank(Request $request, CompetitiveRank $rank, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('edit' . $rank->getId(), $request->request->get('_token'))) {
            $rank->setPrincipalRole($request->request->get('principal_role', $rank->getPrincipalRole()));
            $rank->setSkillLevel($request->request->get('skill_level', $rank->getSkillLevel()));
            $rank->setExperience($request->request->get('experience', $rank->getExperience()));
            $rank->setAvailability($request->request->get('availability', $rank->getAvailability()));
            $rank->setRegion($request->request->get('region', $rank->getRegion()));

            $entityManager->flush();
            $this->addFlash('success', 'Rank updated successfully.');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/rank/delete/{id}', name: 'app_profile_rank_delete', methods: ['POST'])]
    public function deleteRank(Request $request, CompetitiveRank $rank, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $rank->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rank);
            $entityManager->flush();
            $this->addFlash('success', 'Rank deleted successfully.');
        }

        return $this->redirectToRoute('app_profile');
    }
}
