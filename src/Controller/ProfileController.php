<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\CompetitiveRank;
use App\Entity\MediaSubmission;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Repository\FriendRequestRepository $friendRequestRepository,
        \App\Repository\SteamStatsRepository $steamStatsRepository,
        \App\Repository\RiotStatsRepository $riotStatsRepository,
        \App\Repository\MediaSubmissionRepository $mediaSubmissionRepository
    ): Response {
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
                    // Social Media Validation
                    $youtube = $request->request->get('youtube');
                    $twitch = $request->request->get('twitch');
                    $kick = $request->request->get('kick');
                    $twitter = $request->request->get('twitter'); // Other Link
                    $discord = $request->request->get('discord');

                    $socialErrors = [];

                    if (!empty($youtube) && !preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/', $youtube)) {
                        $socialErrors['youtube'] = 'Le lien YouTube doit être valide (youtube.com ou youtu.be).';
                    }
                    if (!empty($twitch) && !preg_match('/^(https?:\/\/)?(www\.)?twitch\.tv\/.+$/', $twitch)) {
                        $socialErrors['twitch'] = 'Le lien Twitch doit être valide (twitch.tv).';
                    }
                    if (!empty($kick) && !preg_match('/^(https?:\/\/)?(www\.)?kick\.com\/.+$/', $kick)) {
                        $socialErrors['kick'] = 'Le lien Kick doit être valide (kick.com).';
                    }
                    if (!empty($discord) && !preg_match('/^(https?:\/\/)?(www\.)?(discord\.gg|discord\.com)\/.+$/', $discord)) {
                        $socialErrors['discord'] = 'Le lien Discord doit être valide (discord.gg ou discord.com).';
                    }
                    if (!empty($twitter) && !filter_var($twitter, FILTER_VALIDATE_URL)) {
                        $socialErrors['twitter'] = 'Le lien "Autre" doit être une URL valide (https://...).';
                    } elseif (!empty($twitter) && !preg_match('/^https:\/\//', $twitter)) {
                        $socialErrors['twitter'] = 'Le lien doit commencer par https://.';
                    }

                    if (!empty($socialErrors)) {
                        // Set values on user so form repopulates (memory only, no flush)
                        $user->setYoutube($youtube);
                        $user->setTwitch($twitch);
                        $user->setKick($kick);
                        $user->setTwitter($twitter);
                        $user->setDiscord($discord);

                        // Re-set other fields so they don't get lost in the view
                        $user->setPseudo($pseudo);
                        $user->setName($name);
                        $user->setEmail($email);
                        $user->setSexe($request->request->get('sexe'));
                        $user->setBio($request->request->get('bio'));
                        $user->setCountry($request->request->get('country'));

                        // Pass errors to view
                        return $this->render('frontoffice/profile/index.html.twig', array_merge(
                            $this->prepareProfileData($user, $friendRequestRepository, $steamStatsRepository, $riotStatsRepository, $mediaSubmissionRepository),
                            ['socialErrors' => $socialErrors]
                        ));
                    }

                    $user->setYoutube($youtube);
                    $user->setTwitch($twitch);
                    $user->setKick($kick);
                    $user->setTwitter($twitter);
                    $user->setDiscord($discord);


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
                    if (!$player) {
                        $player = new \App\Entity\Player();
                        $player->setUser($user);
                        $entityManager->persist($player);
                    }

                    $games = $request->request->all('games');
                    $player->setSelectedGames($games);
                    $player->setGame($request->request->get('game'));
                    $player->setRole($request->request->get('role'));
                    $player->setRegion($request->request->get('region'));
                    $player->setAvailability($request->request->get('availability'));
                    $player->setStatus($request->request->get('status'));

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

        return $this->render('frontoffice/profile/index.html.twig', $this->prepareProfileData($user, $friendRequestRepository, $steamStatsRepository, $riotStatsRepository, $mediaSubmissionRepository));
    }

    #[Route('/profile/{id}', name: 'app_profile_show', methods: ['GET'])]
    public function show(
        \App\Entity\User $user,
        \App\Repository\FriendRequestRepository $friendRequestRepository,
        \App\Repository\SteamStatsRepository $steamStatsRepository,
        \App\Repository\RiotStatsRepository $riotStatsRepository,
        \App\Repository\MediaSubmissionRepository $mediaSubmissionRepository
    ): Response {
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        return $this->render('frontoffice/profile/index.html.twig', $this->prepareProfileData($user, $friendRequestRepository, $steamStatsRepository, $riotStatsRepository, $mediaSubmissionRepository));
    }

    private function prepareProfileData(
        \App\Entity\User $user,
        \App\Repository\FriendRequestRepository $friendRequestRepository,
        \App\Repository\SteamStatsRepository $steamStatsRepository,
        \App\Repository\RiotStatsRepository $riotStatsRepository,
        \App\Repository\MediaSubmissionRepository $mediaSubmissionRepository
    ): array {
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

        // Fetch Friends
        $friends = [];
        $currentUser = $this->getUser();

        // Only fetch friends if the logged-in user is the owner of the profile
        if ($currentUser && $currentUser->getUserIdentifier() === $user->getUserIdentifier()) {
            $acceptedAsSender = $friendRequestRepository->findBy(['sender' => $user, 'status' => 'accepted']);
            $acceptedAsReceiver = $friendRequestRepository->findBy(['receiver' => $user, 'status' => 'accepted']);
            $friendsRequests = array_merge($acceptedAsSender, $acceptedAsReceiver);

            foreach ($friendsRequests as $fr) {
                $friendUser = ($fr->getSender() === $user) ? $fr->getReceiver() : $fr->getSender();
                $friends[] = [
                    'id' => $friendUser->getId(),
                    'pseudo' => $friendUser->getPseudo() ?? $friendUser->getUsername(),
                    'profilePicture' => $friendUser->getProfilePicture(),
                ];
            }
        }


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

        // Fetch Steam Stats
        $steamStats = null;
        $userSteamStats = $steamStatsRepository->findByUser($user);

        if ($userSteamStats) {
            $steamStats = [
                'steamId' => $userSteamStats->getSteamId(),
                'totalMatches' => $userSteamStats->getTotalMatches(),
                'totalPlaytime' => $userSteamStats->getTotalPlaytime(),
                'wins' => $userSteamStats->getWins(),
                'losses' => $userSteamStats->getLosses(),
                'kills' => $userSteamStats->getKills(),
                'deaths' => $userSteamStats->getDeaths(),
                'assists' => $userSteamStats->getAssists(),
                'headshots' => $userSteamStats->getHeadshots(),
                'kdRatio' => $userSteamStats->getKdRatio(),
                'winRate' => $userSteamStats->getWinRate(),
                'headshotPercentage' => $userSteamStats->getHeadshotPercentage(),
                'lastUpdated' => $userSteamStats->getLastUpdated(),
            ];
        }

        // Fetch Riot Stats
        $riotStatsRaw = $riotStatsRepository->findByUser($user);
        $riotStats = [];
        foreach ($riotStatsRaw as $stat) {
            $riotStats[$stat->getGame()] = [
                'game' => $stat->getGame(),
                'riotId' => $stat->getRiotId(),
                'tier' => $stat->getTier(),
                'division' => $stat->getDivision(),
                'leaguePoints' => $stat->getLeaguePoints(),
                'wins' => $stat->getWins(),
                'losses' => $stat->getLosses(),
                'winRate' => $stat->getWinRate(),
                'recentMatches' => $stat->getRecentMatches(),
                'lastUpdated' => $stat->getLastUpdated(),
            ];
        }

        // Check if viewing own profile
        $isOwner = $currentUser && $currentUser->getUserIdentifier() === $user->getUserIdentifier();

        return [
            'playerInfo' => $playerInfo,
            'playerPreferences' => $playerPreferences,
            'friends' => $friends, // Passed instead of teamStatus
            'competitiveRanks' => $competitiveRanks,
            'user' => $user, // Pass the user object for the form
            'steamStats' => $steamStats, // Pass Steam stats
            'riotStats' => $riotStats, // Pass all Riot stats keyed by game
            'highlights' => array_map(function ($m) use ($currentUser) {
                return [
                    'id' => $m->getId(),
                    'type' => $m->getType(),
                    'url' => $m->getUrl(),
                    'thumbnailUrl' => $m->getThumbnailUrl(),
                    'title' => $m->getTitle(),
                    'description' => $m->getDescription(),
                    'likes' => $m->getLikesCount(),
                    'views' => $m->getViewsCount(),
                    'duration' => $m->getDuration(),
                    'createdAt' => $m->getCreatedAt(),
                    'isLiked' => $currentUser ? $m->getLikes()->exists(function ($key, $element) use ($currentUser) {
                        return $element->getUser() === $currentUser;
                    }) : false,
                ];
            }, $mediaSubmissionRepository->findLatestForUser($user, $isOwner, 5)),
        ];
    }

    #[Route('/profile/media/upload', name: 'app_profile_media_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadMedia(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('upload_media', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 400);
        }

        $files = $request->files->get('mediaFiles');
        if (!$files) {
            $files = $request->files->all()['mediaFiles'] ?? [];
        }
        if (!is_array($files)) {
            $files = [$files];
        }

        $allowedMimes = ['image/png', 'image/jpeg', 'video/mp4', 'video/webm'];
        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDir = $projectDir . '/public/uploads/media/' . $user->getId();
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $title = $request->request->get('title', null);
        $description = $request->request->get('description', null);
        $visibility = $request->request->get('visibility', 'public');

        $items = [];

        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            $mime = $file->getMimeType();
            if (!in_array($mime, $allowedMimes, true)) {
                continue;
            }
            $size = $file->getSize();
            $ext = $file->guessExtension() ?: ($file->getClientOriginalExtension() ?: 'bin');
            $filename = uniqid('', true) . '.' . $ext;
            $file->move($targetDir, $filename);
            $relativePath = '/uploads/media/' . $user->getId() . '/' . $filename;
            $absolutePath = $targetDir . '/' . $filename;

            $type = str_starts_with($mime, 'image/') ? 'image' : 'video';
            $thumb = $type === 'image' ? $relativePath : null;
            $width = null;
            $height = null;

            if ($type === 'image') {
                $imageSize = getimagesize($absolutePath);
                if ($imageSize) {
                    $width = $imageSize[0];
                    $height = $imageSize[1];
                }
            }

            $media = new MediaSubmission();
            $media->setUser($user)
                ->setType($type)
                ->setTitle($title)
                ->setDescription($description)
                ->setVisibility($visibility)
                ->setSize($size)
                ->setWidth($width)
                ->setHeight($height)
                ->setUrl($relativePath)
                ->setThumbnailUrl($thumb)
                ->setStatus('published'); // Auto-publish for now

            $entityManager->persist($media);
            $items[] = [
                'url' => $relativePath,
                'type' => $type,
            ];
        }
        $entityManager->flush();

        return new JsonResponse(['items' => $items]);
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

    #[Route('/profile/steam/fetch', name: 'app_profile_steam_fetch', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function fetchSteamStats(
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\SteamService $steamService,
        \App\Repository\SteamStatsRepository $steamStatsRepository,
        \Symfony\Contracts\Cache\CacheInterface $cache
    ): Response {
        $steamId = $request->request->get('steam_id');

        if (empty($steamId)) {
            $this->addFlash('error', 'Please provide a valid Steam ID.');
            return $this->redirectToRoute('app_profile');
        }

        // Validate Steam ID format
        if (!$steamService->validateSteamId($steamId)) {
            $this->addFlash('error', 'Invalid Steam ID format. Please provide a 17-digit Steam ID.');
            return $this->redirectToRoute('app_profile');
        }

        // Fetch CS2 profile data from Steam API
        $profileData = $steamService->getCS2Profile($steamId);

        if (!$profileData) {
            $this->addFlash('error', 'Unable to fetch Steam stats. Please check your Steam ID and ensure your profile is public.');
            return $this->redirectToRoute('app_profile');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Check if stats already exist for this user
        $steamStats = $steamStatsRepository->findByUser($user);

        if (!$steamStats) {
            // Create new SteamStats entity
            $steamStats = new \App\Entity\SteamStats();
            $steamStats->setUser($user);
            $entityManager->persist($steamStats);
        }

        // Update stats
        $steamStats->setSteamId($steamId);
        $steamStats->setTotalMatches($profileData['totalMatches'] ?? 0);
        $steamStats->setTotalPlaytime($profileData['totalPlaytime'] ?? 0);
        $steamStats->setWins($profileData['wins'] ?? 0);
        $steamStats->setLosses($profileData['losses'] ?? 0);
        $steamStats->setKills($profileData['kills'] ?? 0);
        $steamStats->setDeaths($profileData['deaths'] ?? 0);
        $steamStats->setAssists($profileData['assists'] ?? 0);
        $steamStats->setHeadshots($profileData['headshots'] ?? 0);
        $steamStats->setLastUpdated(new \DateTime());

        // Update user's Steam ID
        $user->setSteamId($steamId);

        $entityManager->flush();

        // Clear CV cache for CS2
        $cacheKey = sprintf('gamer_cv_perf_v3_%d_%s', $user->getId(), 'cs2');
        $cache->delete($cacheKey);

        $this->addFlash('success', 'Steam stats successfully updated!');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/riot/fetch', name: 'app_profile_riot_fetch', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function fetchRiotStats(
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\RiotApiService $riotApiService,
        \App\Repository\RiotStatsRepository $riotStatsRepository,
        \Symfony\Contracts\Cache\CacheInterface $cache
    ): Response {
        $riotIdInput = $request->request->get('riot_id');
        $region = $request->request->get('region');
        $game = $request->request->get('game'); // 'lol' or 'valorant'

        if (empty($riotIdInput) || empty($region) || empty($game)) {
            $this->addFlash('error', 'Please provide Riot ID, Region, and Game.');
            return $this->redirectToRoute('app_profile');
        }

        // Parse Riot ID (GameName#TagLine)
        $parts = explode('#', $riotIdInput);
        if (count($parts) !== 2) {
            $this->addFlash('error', 'Invalid Riot ID format. Use Name#Tag.');
            return $this->redirectToRoute('app_profile');
        }
        $gameName = $parts[0];
        $tagLine = $parts[1];

        // 1. Get PUUID
        $puuid = $riotApiService->getPuuid($gameName, $tagLine, $region);

        if (!$puuid) {
            $this->addFlash('error', 'Player not found. Check ID and Region.');
            return $this->redirectToRoute('app_profile');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // 2. Fetch Stats based on game
        $statsData = null;
        if ($game === 'lol') {
            $statsData = $riotApiService->getLeagueStats($puuid, $region);
        } elseif ($game === 'valorant') {
            $statsData = $riotApiService->getValorantStats($puuid, $region);
        }

        // 3. Save/Update RiotStats (always save account linkage even without ranked data)
        $riotStats = $riotStatsRepository->findByUserAndGame($user, $game);
        if (!$riotStats) {
            $riotStats = new \App\Entity\RiotStats();
            $riotStats->setUser($user);
            $riotStats->setGame($game);
            $entityManager->persist($riotStats);
        }

        $riotStats->setPuuid($puuid);
        $riotStats->setGameName($gameName);
        $riotStats->setTagLine($tagLine);
        $riotStats->setRegion($region);
        $riotStats->setLastUpdated(new \DateTime());

        if ($game === 'lol') {
            if ($statsData) {
                $riotStats->setTier($statsData['tier'] ?? null);
                $riotStats->setRank($statsData['rank'] ?? null);
                $riotStats->setDivision($statsData['rank'] ?? null);
                $riotStats->setLeaguePoints($statsData['leaguePoints'] ?? 0);
                $riotStats->setWins($statsData['wins'] ?? 0);
                $riotStats->setLosses($statsData['losses'] ?? 0);
            }

            // FETCH RECENT MATCHES (Steps B & C) — works even if unranked
            $matchIds = $riotApiService->getMatchIds($puuid, $region, 0, 20);
            $recentMatchesStats = [];
            foreach ($matchIds as $matchId) {
                $matchData = $riotApiService->getMatchDetails($matchId, $region);
                if ($matchData) {
                    $participantStats = $riotApiService->getParticipantStats($matchData, $puuid);
                    if ($participantStats) {
                        $recentMatchesStats[] = array_merge($participantStats, ['matchId' => $matchId]);
                    }
                }
            }
            $riotStats->setRecentMatches($recentMatchesStats);

            if (!$statsData) {
                $this->addFlash('warning', 'Compte Riot lié ! Aucun rang trouvé pour cette saison, mais l\'historique des matchs a été récupéré.');
            }
        } elseif ($game === 'valorant') {
            if ($statsData) {
                $riotStats->setTier($statsData['tier'] ?? null);
                $riotStats->setRank($statsData['rank'] ?? null);
                $riotStats->setDivision($statsData['rank'] ?? null); // For Valorant, rank 1-3
                $riotStats->setLeaguePoints($statsData['rankedRating'] ?? 0);
                $riotStats->setWins($statsData['wins'] ?? 0);
                $riotStats->setLosses($statsData['losses'] ?? 0);
                $riotStats->setRecentMatches($statsData['recentMatches'] ?? []);
            }
            if (!$statsData) {
                $this->addFlash('warning', 'Compte Valorant lié. Stats détaillées non disponibles avec une clé dev standard.');
            }
        }

        $entityManager->flush();

        // Clear CV cache for Riot Games (LoL/Val)
        $cacheKeyLoL = sprintf('gamer_cv_perf_v3_%d_%s', $user->getId(), 'lol');
        $cacheKeyVal = sprintf('gamer_cv_perf_v3_%d_%s', $user->getId(), 'valorant');
        $cache->delete($cacheKeyLoL);
        $cache->delete($cacheKeyVal);
        if ($statsData) {
            $this->addFlash('success', 'Stats Riot mises à jour avec succès !');
        }
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/cv/download/{game}', name: 'app_profile_cv_download', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadCv(
        string $game,
        Request $request,
        \Symfony\Contracts\Cache\CacheInterface $cache,
        \App\Service\PdfCvService $pdfCvService,
        \App\Repository\RiotStatsRepository $riotStatsRepository,
        \App\Repository\SteamStatsRepository $steamStatsRepository
    ): Response {
        $allowedGames = ['lol', 'valorant', 'cs2'];
        if (!in_array($game, $allowedGames, true)) {
            throw $this->createNotFoundException('Jeu non supporté : ' . $game);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Optional: Force cache clear if requested via ?force=1
        if ($request->query->get('force')) {
            $cacheKey = sprintf('gamer_cv_perf_v2_%d_%s', $user->getId(), $game);
            $cache->delete($cacheKey);
        }

        $riotStats = null;
        $steamStats = null;

        if ($game === 'lol' || $game === 'valorant') {
            $riotStats = $riotStatsRepository->findOneBy(['user' => $user, 'game' => $game]);
        } elseif ($game === 'cs2') {
            $steamStats = $steamStatsRepository->findByUser($user);
        }

        try {
            $pdfContent = $pdfCvService->generatePlayerCv($user, $riotStats, $steamStats, $game);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de la génération du CV : ' . $e->getMessage());
            return $this->redirectToRoute('app_profile');
        }

        $gameLabel = match ($game) {
            'lol' => 'LeagueOfLegends',
            'valorant' => 'Valorant',
            'cs2' => 'CS2',
        };
        $filename = 'teamcraft_cv_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $user->getPseudo()) . '_' . $gameLabel . '.pdf';

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
