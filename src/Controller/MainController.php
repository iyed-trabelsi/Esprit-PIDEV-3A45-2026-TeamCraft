<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]

public function index(Request $request, \App\Repository\OfferRepository $offerRepository): Response
    {
        // 1. Ta Sécurité : Bloque l'accès si le code de sécurité est requis
        if ($request->getSession()->get('2fa_required')) {
            return $this->redirectToRoute('app_verify_security');
        }

        // 2. Leur Travail : Récupération des offres sponsorisées
        $sponsoredOffers = $offerRepository->findAllSorted(['offerType' => \App\Entity\Offer::TYPE_SPONSORED]);
        
        // On garde les 3 meilleures pour la page d'accueil
        $sponsoredOffers = array_slice($sponsoredOffers, 0, 3);

        // 3. Rendu final avec les variables des collègues
        return $this->render('frontoffice/home/index.html.twig', [
            'sponsoredOffers' => $sponsoredOffers
        ]);
    }

    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('frontoffice/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }



    #[Route('/players', name: 'app_players', methods: ['GET'])]
    public function players(Request $request, \App\Repository\PlayerRepository $playerRepository, \App\Repository\FriendRequestRepository $friendRequestRepository): Response
    {
        $search = $request->query->get('search');
        $gameFilter = $request->query->get('game');
        $statusFilter = $request->query->get('status');
        $sort = $request->query->get('sort');

        $qb = $playerRepository->createQueryBuilder('p')
            ->leftJoin('p.user', 'u');

        if ($search) {
            $qb->andWhere('u.pseudo LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($gameFilter) {
            $qb->andWhere('p.game = :game OR EXISTS (SELECT r FROM App\Entity\CompetitiveRank r WHERE r.player = p AND r.game = :game)')
                ->setParameter('game', $gameFilter);
        }

        if ($statusFilter) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($user = $this->getUser()) {
            /** @var \App\Entity\User $user */
            $qb->andWhere('u.id != :currentUserId')
                ->setParameter('currentUserId', $user->getId());
        }

        // Exclude Admins from the discovery list
        $qb->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('roleAdmin', '%ROLE_ADMIN%');

        // Sorting logic
        switch ($sort) {
            case 'pseudo_asc':
                $qb->orderBy('u.pseudo', 'ASC');
                break;
            case 'pseudo_desc':
                $qb->orderBy('u.pseudo', 'DESC');
                break;
            case 'newest':
                $qb->orderBy('p.id', 'DESC');
                break;
            default:
                $qb->orderBy('p.id', 'DESC');
        }

        $entities = $qb->getQuery()->getResult();
        $players = [];

        foreach ($entities as $p) {
            $user = $p->getUser();
            $name = $user ? $user->getPseudo() : 'Joueur';

            // Try to get info from CompetitiveRanks first, then fallback to Player entity fields
            $ranks = $p->getCompetitiveRanks();

            // If we have a game filter, try to find a rank for that specific game
            $mainRank = null;
            if ($gameFilter) {
                foreach ($ranks as $r) {
                    if (strtolower($r->getGame()) === strtolower($gameFilter)) {
                        $mainRank = $r;
                        break;
                    }
                }
            }

            if (!$mainRank && !$ranks->isEmpty()) {
                $mainRank = $ranks->first();
            }

            $game = $mainRank ? strtoupper($mainRank->getGame()) : strtoupper($p->getGame() ?? 'N/A');
            $role = $mainRank ? $mainRank->getPrincipalRole() : ($p->getRole() ?? 'Joueur');
            $rank = $mainRank ? strtoupper($mainRank->getSkillLevel()) : strtoupper($p->getGameRank() ?? 'N/A');

            // Determine colors and keys for CSS classes
            $gameKey = $mainRank ? strtolower($mainRank->getGame()) : strtolower($p->getGame() ?? '');
            $gameColor = match ($gameKey) {
                'valorant' => 'valorant',
                'lol', 'league of legends' => 'lol',
                'cs2', 'csgo' => 'cs2',
                'overwatch', 'ow2' => 'overwatch',
                default => 'bg-secondary'
            };

            $rankLower = strtolower($rank);
            $rankColor = 'bg-secondary';
            if (str_contains($rankLower, 'iron') || str_contains($rankLower, 'bronze') || str_contains($rankLower, 'silver')) {
                $rankColor = 'grey';
            } elseif (str_contains($rankLower, 'gold') || str_contains($rankLower, 'platinum')) {
                $rankColor = 'yellow';
            } elseif (str_contains($rankLower, 'diamond') || str_contains($rankLower, 'emerald')) {
                $rankColor = 'purple';
            } elseif (str_contains($rankLower, 'master') || str_contains($rankLower, 'grandmaster') || str_contains($rankLower, 'challenger') || str_contains($rankLower, 'immortal') || str_contains($rankLower, 'radiant') || str_contains($rankLower, 'global')) {
                $rankColor = 'green';
            }

            $players[] = [
                'id' => $user ? $user->getId() : $p->getId(),
                'name' => $name,
                'role' => $role,
                'game' => $game,
                'gameColor' => $gameColor,
                'rank' => $rank,
                'rankColor' => $rankColor,
                'initial' => mb_substr($name, 0, 1)
            ];
        }

        // Inject Friend Status
        $friendStatuses = [];
        if ($user = $this->getUser()) {
            /** @var \App\Entity\User $user */
            $friendRequests = $friendRequestRepository->createQueryBuilder('fr')
                ->where('fr.sender = :user OR fr.receiver = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();

            foreach ($friendRequests as $fr) {
                if ($fr->getStatus() === 'accepted') {
                    $otherUser = ($fr->getSender() === $user) ? $fr->getReceiver() : $fr->getSender();
                    $friendStatuses[$otherUser->getId()] = ['status' => 'friends', 'requestId' => $fr->getId()];
                } elseif ($fr->getStatus() === 'pending') {
                    if ($fr->getSender() === $user) {
                        $friendStatuses[$fr->getReceiver()->getId()] = ['status' => 'pending_sent', 'requestId' => $fr->getId()];
                    } else {
                        $friendStatuses[$fr->getSender()->getId()] = ['status' => 'pending_received', 'requestId' => $fr->getId()];
                    }
                }
            }
        }

        // Handle AJAX request for dynamic search
        if ($request->isXmlHttpRequest()) {
            return $this->render('frontoffice/players/_player_cards.html.twig', [
                'players' => $players,
                'friendStatuses' => $friendStatuses
            ]);
        }

        return $this->render('frontoffice/players/index.html.twig', [
            'players' => $players,
            'friendStatuses' => $friendStatuses
        ]);
    }

    #[Route('/teams', name: 'app_teams', methods: ['GET'])]
    public function teams(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $myTeams = [];

        if ($user) {
            $myTeams = $user->getTeams();
        }

        $teams = [
            ['name' => 'ViperStrike', 'role' => 'Duelist / Entry', 'game' => 'VALORANT', 'gameColor' => 'valorant', 'rank' => 'DIAMOND 3', 'rankColor' => 'purple', 'initial' => 'VS'],
            ['name' => 'Kira.exe', 'role' => 'Support / Healer', 'game' => 'OVERWATCH', 'gameColor' => 'overwatch', 'rank' => 'MASTER', 'rankColor' => 'yellow', 'initial' => 'K'],
            ['name' => 'GhostOp', 'role' => 'Jungler', 'game' => 'LOL', 'gameColor' => 'lol', 'rank' => 'DIAMOND 1', 'rankColor' => 'purple', 'initial' => 'G'],
            ['name' => 'StratKing', 'role' => 'IGL / Captain', 'game' => 'CS2', 'gameColor' => 'cs2', 'rank' => 'GLOBAL', 'rankColor' => 'green', 'initial' => 'S'],
            ['name' => 'Phoenix Squad', 'role' => 'Competitive', 'game' => 'VALORANT', 'gameColor' => 'valorant', 'rank' => 'IMMORTAL', 'rankColor' => 'purple', 'initial' => 'PS'],
            ['name' => 'Storm Gaming', 'role' => 'Pro League', 'game' => 'CS2', 'gameColor' => 'cs2', 'rank' => 'GLOBAL', 'rankColor' => 'green', 'initial' => 'SG'],
        ];
        return $this->render('frontoffice/teams/index.html.twig', [
            'teams' => $teams,
            'myTeams' => $myTeams
        ]);
    }

    #[Route('/offres', name: 'app_offres', methods: ['GET'])]
    public function offres(Request $request, \App\Repository\OfferRepository $offerRepository, \Doctrine\ORM\EntityManagerInterface $em, \App\Service\SmartMatchingService $smartMatchingService): Response
    {
        $search = $request->query->get('search');
        $game = $request->query->get('game');
        $rank = $request->query->get('rank');

        $criteria = [];
        if ($game) {
            $criteria['game'] = $game;
        }

        $offres = $offerRepository->findAllSorted($criteria, $search, $rank);

        $favoriteIds = [];
        $matchScores = [];
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user) {
            $favorites = $em->getRepository(\App\Entity\FavoriteOffer::class)->findBy(['user' => $user]);
            $favoriteIds = array_map(fn($f) => $f->getOffer()->getId(), $favorites);

            if ($user->getPlayerProfile()) {
                $matchScores = $smartMatchingService->predictMatchesBatch($user->getPlayerProfile(), $offres);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('frontoffice/offres/_offer_cards.html.twig', [
                'offres' => $offres,
                'favoriteIds' => $favoriteIds,
                'matchScores' => $matchScores
            ]);
        }

        return $this->render('frontoffice/offres/index.html.twig', [
            'offres' => $offres,
            'favoriteIds' => $favoriteIds,
            'matchScores' => $matchScores
        ]);
    }

    #[Route('/offres/{id}', name: 'app_offres_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function offresShow(int $id, \App\Repository\OfferRepository $offerRepository, \App\Repository\PostulationRepository $postulationRepository, \App\Service\SmartMatchingService $smartMatchingService, \Doctrine\ORM\EntityManagerInterface $entityManager, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $offre = $offerRepository->find($id);

        if (!$offre) {
            throw $this->createNotFoundException('Offre non trouvée.');
        }

        // Track view (prevent duplicate counting with session)
        $session = $request->getSession();
        $viewedOffers = $session->get('viewed_offers', []);
        
        // If this offer wasn't viewed in this session in the last 24h, increment view count
        $offerKey = 'offer_' . $id;
        $lastViewed = $viewedOffers[$offerKey] ?? null;
        $now = new \DateTimeImmutable();
        
        if (!$lastViewed || $lastViewed < $now->modify('-24 hours')) {
            $offre->incrementViews();
            $entityManager->flush();
            $viewedOffers[$offerKey] = $now;
            $session->set('viewed_offers', $viewedOffers);
        }

        // Check if current user has already applied
        $hasApplied = false;
        if ($this->getUser()) {
            $existingPostulation = $postulationRepository->findOneBy([
                'user' => $this->getUser(),
                'offer' => $offre
            ]);
            $hasApplied = $existingPostulation !== null;
        }

        $form = $this->createForm(\App\Form\PostulationType::class);

        // Get Smart Matching Recommendations
        $recommendations = $smartMatchingService->getTopPlayersForOffer($offre);
        $recommendedPlayers = [];

        foreach ($recommendations as $rec) {
            /** @var \App\Entity\Player $p */
            $p = $rec['player'];
            $score = $rec['score'];
            $user = $p->getUser();
            $name = $user ? $user->getPseudo() : 'Joueur';

            // Determine colors (similar to players list logic)
            $gameKey = strtolower($p->getGame() ?? '');
            $gameColor = match ($gameKey) {
                'valorant' => 'valorant',
                'lol', 'league of legends' => 'lol',
                'cs2', 'csgo' => 'cs2',
                'overwatch', 'ow2' => 'overwatch',
                default => 'bg-secondary'
            };

            $rankLower = strtolower($p->getGameRank() ?? '');
            $rankColor = 'bg-secondary';
            if (str_contains($rankLower, 'iron') || str_contains($rankLower, 'bronze') || str_contains($rankLower, 'silver')) {
                $rankColor = 'grey';
            } elseif (str_contains($rankLower, 'gold') || str_contains($rankLower, 'platinum')) {
                $rankColor = 'yellow';
            } elseif (str_contains($rankLower, 'diamond') || str_contains($rankLower, 'emerald')) {
                $rankColor = 'purple';
            } elseif (str_contains($rankLower, 'master') || str_contains($rankLower, 'grandmaster') || str_contains($rankLower, 'challenger') || str_contains($rankLower, 'immortal') || str_contains($rankLower, 'radiant') || str_contains($rankLower, 'global')) {
                $rankColor = 'green';
            }

            $recommendedPlayers[] = [
                'id' => $p->getUser()->getId(), // Link to user profile usually
                'name' => $name,
                'role' => $p->getRole() ?? 'N/A',
                'game' => $p->getGame() ?? 'N/A',
                'gameColor' => $gameColor,
                'rank' => $p->getGameRank() ?? 'N/A',
                'rankColor' => $rankColor,
                'initial' => mb_substr($name, 0, 1),
                'score' => $score,
                'compatibilityLevel' => $rec['compatibility_level'] ?? 'Low'
            ];
        }

        // Check if favorited
        $isFavorited = false;
        if ($this->getUser()) {
            $isFavorited = $entityManager->getRepository(\App\Entity\FavoriteOffer::class)->isFavorited($this->getUser(), $offre);
        }

        return $this->render('frontoffice/offres/show.html.twig', [
            'offre' => $offre,
            'hasApplied' => $hasApplied,
            'form' => $form->createView(),
            'recommendedPlayers' => $recommendedPlayers,
            'isFavorited' => $isFavorited
        ]);
    }

    #[Route('/offres/{id}/postuler', name: 'app_offres_postuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postuler(int $id, Request $request, \App\Repository\OfferRepository $offerRepository, \App\Repository\PostulationRepository $postulationRepository, \Doctrine\ORM\EntityManagerInterface $entityManager): Response
    {
        // Check if user is authenticated
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour postuler à une offre.');
            return $this->redirectToRoute('app_login');
        }

        // Security check: Admins cannot apply
        if ($this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Les administrateurs ne peuvent pas postuler aux offres.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $offre = $offerRepository->find($id);

        if (!$offre) {
            throw $this->createNotFoundException('Offre non trouvée.');
        }

        // Check if user has already applied
        $existingPostulation = $postulationRepository->findOneBy([
            'user' => $this->getUser(),
            'offer' => $offre
        ]);

        if ($existingPostulation) {
            $this->addFlash('warning', 'Vous avez déjà postulé à cette offre.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $postulation = new \App\Entity\Postulation();
        $form = $this->createForm(\App\Form\PostulationType::class, $postulation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $postulation->setUser($this->getUser());
            $postulation->setOffer($offre);
            $postulation->setStatus('pending');

            // Calculate and store match score at time of application
            if ($this->getUser() && $this->getUser()->getPlayerProfile()) {
                $prediction = $smartMatchingService->predictMatch($this->getUser()->getPlayerProfile(), $offre);
                $postulation->setMatchScore((float) $prediction['match_score']);
            }

            $entityManager->persist($postulation);
            $entityManager->flush();

            $this->addFlash('success', 'Votre candidature a été envoyée avec succès !');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_offres_show', ['id' => $id]);
    }

    #[Route('/my-applications', name: 'app_my_applications', methods: ['GET'])]
    public function myApplications(\App\Repository\PostulationRepository $postulationRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour voir vos candidatures.');
            return $this->redirectToRoute('app_login');
        }

        $applications = $postulationRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('frontoffice/user/my_applications.html.twig', [
            'applications' => $applications
        ]);
    }

    #[Route('/offres/{id}/favorite', name: 'app_offer_favorite', methods: ['POST'])]
    public function favoriteOffer(int $id, \App\Repository\OfferRepository $offerRepository, \App\Repository\FavoriteOfferRepository $favoriteOfferRepository, \Doctrine\ORM\EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->getUser()) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => 'You must be logged in'], 401);
        }

        $offer = $offerRepository->find($id);
        if (!$offer) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => 'Offer not found'], 404);
        }

        // Check if already favorited
        if ($favoriteOfferRepository->isFavorited($this->getUser(), $offer)) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => 'Already favorited'], 400);
        }

        $favorite = new \App\Entity\FavoriteOffer();
        $favorite->setUser($this->getUser());
        $favorite->setOffer($offer);

        $em->persist($favorite);
        $em->flush();

        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true, 'message' => 'Offer added to favorites']);
    }

    #[Route('/offres/{id}/unfavorite', name: 'app_offer_unfavorite', methods: ['POST'])]
    public function unfavoriteOffer(int $id, \App\Repository\OfferRepository $offerRepository, \App\Repository\FavoriteOfferRepository $favoriteOfferRepository, \Doctrine\ORM\EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->getUser()) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => 'You must be logged in'], 401);
        }

        $offer = $offerRepository->find($id);
        if (!$offer) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => 'Offer not found'], 404);
        }

        $favorite = $favoriteOfferRepository->findOneByUserAndOffer($this->getUser(), $offer);
        if (!$favorite) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'message' => 'Not favorited'], 400);
        }

        $em->remove($favorite);
        $em->flush();

        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true, 'message' => 'Offer removed from favorites']);
    }

    #[Route('/my-favorites', name: 'app_my_favorites', methods: ['GET'])]
    public function myFavorites(\App\Repository\FavoriteOfferRepository $favoriteOfferRepository): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $favorites = $favoriteOfferRepository->findByUser($this->getUser());
        
        // Extract offers from favorites
        $offers = array_map(fn($fav) => $fav->getOffer(), $favorites);

        return $this->render('frontoffice/offres/favorites.html.twig', [
            'offres' => $offers,
            'favorites' => $favorites
        ]);
    }

}
