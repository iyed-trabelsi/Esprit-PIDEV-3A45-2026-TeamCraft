<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Données simulées pour le tableau de bord
        $players = $this->getPlayers();
        $teams = $this->getTeams();
        $offers = $this->getOffres();
        $events = $this->getEvents();
        $posts = $this->getForumPosts();

        return $this->render('backoffice/dashboard.html.twig', [
            'players' => $players,
            'teams' => $teams,
            'offers' => $offers,
            'events' => $events,
            'posts' => $posts,
        ]);
    }

    #[Route('/admin/players', name: 'admin_players', methods: ['GET'])]
    public function players(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $allPlayers = $this->getPlayers();
        $limit = 2; // Testing limit to show pagination
        $page = max(1, $request->query->getInt('page', 1));
        $totalPlayers = count($allPlayers);
        $maxPages = (int) ceil($totalPlayers / $limit);

        // Ensure page matches valid range
        if ($page > $maxPages && $maxPages > 0) {
            $page = $maxPages;
        }

        $offset = ($page - 1) * $limit;
        $paginatedPlayers = array_slice($allPlayers, $offset, $limit);

        return $this->render('backoffice/players.html.twig', [
            'players' => $paginatedPlayers,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalPlayers' => $totalPlayers
        ]);
    }

    #[Route('/admin/teams', name: 'admin_teams', methods: ['GET'])]
    public function teams(\Symfony\Component\HttpFoundation\Request $request, \App\Repository\TeamRepository $teamRepository): Response
    {
        $limit = 5;
        $page = max(1, $request->query->getInt('page', 1));
        $query = $request->query->get('q');
        $game = $request->query->get('game');
        $sort = $request->query->get('sort');

        // Count total matching query
        $totalTeams = $teamRepository->countSearchByNameOrGame($query, $game);
        $maxPages = (int) ceil($totalTeams / $limit);

        if ($page > $maxPages && $maxPages > 0) {
            $page = $maxPages;
        }

        $offset = ($page - 1) * $limit;

        // Fetch paginated results matching query
        $paginatedTeams = $teamRepository->searchByNameOrGame($query, $game, $sort, $limit, $offset);

        if ($request->isXmlHttpRequest()) {
            return $this->render('backoffice/_teams_list.html.twig', [
                'teams' => $paginatedTeams,
                'currentPage' => $page,
                'maxPages' => $maxPages,
                'totalTeams' => $totalTeams
            ]);
        }

        return $this->render('backoffice/teams.html.twig', [
            'teams' => $paginatedTeams,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalTeams' => $totalTeams
        ]);
    }

    #[Route('/admin/offers', name: 'admin_offers', methods: ['GET'])]
    public function offers(\Symfony\Component\HttpFoundation\Request $request, \App\Repository\OfferRepository $offerRepository): Response
    {
        $allOffers = $offerRepository->findAll();
        $limit = 5;
        $page = max(1, $request->query->getInt('page', 1));
        $totalOffers = count($allOffers);
        $maxPages = (int) ceil($totalOffers / $limit);

        if ($page > $maxPages && $maxPages > 0) {
            $page = $maxPages;
        }

        $offset = ($page - 1) * $limit;
        $paginatedOffers = array_slice($allOffers, $offset, $limit);

        return $this->render('backoffice/offers.html.twig', [
            'offers' => $paginatedOffers,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalOffers' => $totalOffers
        ]);
    }

    /*
    #[Route('/admin/events', name: 'admin_events', methods: ['GET'])]
    public function events(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $allEvents = $this->getEvents();
        $limit = 2;
        $page = max(1, $request->query->getInt('page', 1));
        $totalEvents = count($allEvents);
        $maxPages = (int) ceil($totalEvents / $limit);

        if ($page > $maxPages && $maxPages > 0) {
            $page = $maxPages;
        }

        $offset = ($page - 1) * $limit;
        $paginatedEvents = array_slice($allEvents, $offset, $limit);

        return $this->render('backoffice/events.html.twig', [
            'events' => $paginatedEvents,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalEvents' => $totalEvents
        ]);
    }
    */

    #[Route('/admin/forum', name: 'admin_forum', methods: ['GET'])]
    public function forum(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $allPosts = $this->getForumPosts();
        $limit = 2;
        $page = max(1, $request->query->getInt('page', 1));
        $totalPosts = count($allPosts);
        $maxPages = (int) ceil($totalPosts / $limit);

        if ($page > $maxPages && $maxPages > 0) {
            $page = $maxPages;
        }

        $offset = ($page - 1) * $limit;
        $paginatedPosts = array_slice($allPosts, $offset, $limit);

        return $this->render('backoffice/forum.html.twig', [
            'posts' => $paginatedPosts,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalPosts' => $totalPosts
        ]);
    }

    /**
     * Les méthodes suivantes fournissent des données statiques.
     * Elles pourront être remplacées plus tard par des entités Doctrine + base de données.
     */
    private function getPlayers(): array
    {
        return [
            ['id' => 1, 'name' => 'ViperStrike', 'role' => 'Duelist / Entry', 'game' => 'VALORANT', 'rank' => 'DIAMOND 3', 'region' => 'EUW'],
            ['id' => 2, 'name' => 'Kira.exe', 'role' => 'Support / Healer', 'game' => 'OVERWATCH', 'rank' => 'MASTER', 'region' => 'EU'],
            ['id' => 3, 'name' => 'GhostOp', 'role' => 'Jungler', 'game' => 'LOL', 'rank' => 'DIAMOND 1', 'region' => 'EUW'],
            ['id' => 4, 'name' => 'StratKing', 'role' => 'IGL / Captain', 'game' => 'CS2', 'rank' => 'GLOBAL', 'region' => 'EU'],
        ];
    }

    private function getTeams(): array
    {
        return [
            ['id' => 1, 'name' => 'Phoenix Squad', 'game' => 'VALORANT', 'level' => 'Semi-pro', 'status' => 'Actif'],
            ['id' => 2, 'name' => 'Storm Gaming', 'game' => 'CS2', 'level' => 'Pro', 'status' => 'Actif'],
            ['id' => 3, 'name' => 'Structure LOL', 'game' => 'League of Legends', 'level' => 'Academy', 'status' => 'En pause'],
        ];
    }

    private function getOffres(): array
    {
        return [
            ['id' => 1, 'title' => 'Duelist recherché', 'team' => 'Phoenix Squad', 'game' => 'VALORANT', 'status' => 'En ligne'],
            ['id' => 2, 'title' => 'IGL / Captain', 'team' => 'Storm Gaming', 'game' => 'CS2', 'status' => 'En ligne'],
            ['id' => 3, 'title' => 'Jungler', 'team' => 'Structure LOL', 'game' => 'League of Legends', 'status' => 'Archivée'],
        ];
    }

    private function getEvents(): array
    {
        return [
            ['id' => 1, 'title' => 'VALORANT Regional Cup', 'game' => 'VALORANT', 'date' => '15 Mars 2026', 'status' => 'Ouvert'],
            ['id' => 2, 'title' => 'CS2 Scrim Night', 'game' => 'CS2', 'date' => '22 Fév 2026', 'status' => 'Complet'],
            ['id' => 3, 'title' => 'LOL Academy League', 'game' => 'LOL', 'date' => '1 Avr 2026', 'status' => 'Bientôt'],
        ];
    }

    private function getForumPosts(): array
    {
        return [
            ['id' => 1, 'title' => 'General discussion', 'game' => 'VALORANT', 'replies' => 12, 'status' => 'Ouvert'],
            ['id' => 2, 'title' => 'Actuality', 'game' => 'OVERWATCH', 'replies' => 5, 'status' => 'Ouvert'],
            ['id' => 3, 'title' => 'LF Team', 'game' => 'VALORANT', 'replies' => 8, 'status' => 'Fermé'],
        ];
    }
}

