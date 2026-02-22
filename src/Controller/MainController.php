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
    public function index(): Response
    {
        return $this->render('frontoffice/home/index.html.twig');
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
                'id' => $p->getId(),
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
    public function offres(Request $request, \App\Repository\OfferRepository $offerRepository): Response
    {
        $search = $request->query->get('search');
        $game = $request->query->get('game');
        $rank = $request->query->get('rank');

        $qb = $offerRepository->createQueryBuilder('o')
            ->leftJoin('o.team', 't');

        if ($search) {
            $qb->andWhere('o.title LIKE :search OR o.description LIKE :search OR t.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($game) {
            $qb->andWhere('o.game = :game')
                ->setParameter('game', $game);
        }

        if ($rank) {
            $qb->andWhere('o.rank LIKE :rank')
                ->setParameter('rank', '%' . $rank . '%');
        }

        $qb->orderBy('o.dateCreation', 'DESC');

        $offres = $qb->getQuery()->getResult();

        if ($request->isXmlHttpRequest()) {
            return $this->render('frontoffice/offres/_offer_cards.html.twig', [
                'offres' => $offres
            ]);
        }

        return $this->render('frontoffice/offres/index.html.twig', ['offres' => $offres]);
    }

    #[Route('/offres/{id}', name: 'app_offres_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function offresShow(int $id, \App\Repository\OfferRepository $offerRepository, \App\Repository\PostulationRepository $postulationRepository): Response
    {
        $offre = $offerRepository->find($id);

        if (!$offre) {
            throw $this->createNotFoundException('Offre non trouvée.');
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

        return $this->render('frontoffice/offres/show.html.twig', [
            'offre' => $offre,
            'hasApplied' => $hasApplied,
            'form' => $form->createView()
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


}
