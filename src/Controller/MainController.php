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
    public function players(): Response
    {
        $players = [
            ['name' => 'ViperStrike', 'role' => 'Duelist / Entry', 'game' => 'VALORANT', 'gameColor' => 'valorant', 'rank' => 'DIAMOND 3', 'rankColor' => 'purple', 'initial' => 'VS'],
            ['name' => 'Kira.exe', 'role' => 'Support / Healer', 'game' => 'OVERWATCH', 'gameColor' => 'overwatch', 'rank' => 'MASTER', 'rankColor' => 'yellow', 'initial' => 'K'],
            ['name' => 'GhostOp', 'role' => 'Jungler', 'game' => 'LOL', 'gameColor' => 'lol', 'rank' => 'DIAMOND 1', 'rankColor' => 'purple', 'initial' => 'G'],
            ['name' => 'StratKing', 'role' => 'IGL / Captain', 'game' => 'CS2', 'gameColor' => 'cs2', 'rank' => 'GLOBAL', 'rankColor' => 'green', 'initial' => 'S'],
            ['name' => 'NovaBlade', 'role' => 'Mid Lane', 'game' => 'LOL', 'gameColor' => 'lol', 'rank' => 'MASTER', 'rankColor' => 'yellow', 'initial' => 'N'],
            ['name' => 'ShadowAim', 'role' => 'AWP / Sniper', 'game' => 'CS2', 'gameColor' => 'cs2', 'rank' => 'DIAMOND 3', 'rankColor' => 'purple', 'initial' => 'SA'],
        ];
        return $this->render('frontoffice/players/index.html.twig', ['players' => $players]);
    }

    #[Route('/teams', name: 'app_teams', methods: ['GET'])]
    public function teams(): Response
    {
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
    public function offres(\App\Repository\OfferRepository $offerRepository): Response
    {
        $offres = $offerRepository->findAll();
        return $this->render('frontoffice/offres/index.html.twig', ['offres' => $offres]);
    }

    #[Route('/offres/{id}', name: 'app_offres_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function offresShow(int $id, \App\Repository\OfferRepository $offerRepository): Response
    {
        $offre = $offerRepository->find($id);

        if (!$offre) {
            throw $this->createNotFoundException('Offre non trouvée.');
        }
        return $this->render('frontoffice/offres/show.html.twig', ['offre' => $offre]);
    }

    #[Route('/events', name: 'app_events', methods: ['GET'])]
    public function events(): Response
    {
        $events = [
            ['id' => 1, 'title' => 'VALORANT Regional Cup', 'subtitle' => 'Tournoi ouvert • 32 équipes', 'game' => 'VALORANT', 'gameColor' => 'valorant', 'date' => '15 Mars 2026', 'dateBadge' => 'purple', 'initial' => 'VR'],
            ['id' => 2, 'title' => 'CS2 Scrim Night', 'subtitle' => 'Soirée scrims • BO3', 'game' => 'CS2', 'gameColor' => 'cs2', 'date' => '22 Fév 2026', 'dateBadge' => 'green', 'initial' => 'CS'],
            ['id' => 3, 'title' => 'LOL Academy League', 'subtitle' => 'Phase 2 • Inscriptions ouvertes', 'game' => 'LOL', 'gameColor' => 'lol', 'date' => '1 Avr 2026', 'dateBadge' => 'purple', 'initial' => 'LA'],
            ['id' => 4, 'title' => 'Overwatch 2 Community Cup', 'subtitle' => 'Amateur & semi-pro', 'game' => 'OVERWATCH', 'gameColor' => 'overwatch', 'date' => '8 Mars 2026', 'dateBadge' => 'yellow', 'initial' => 'OW'],
            ['id' => 5, 'title' => 'TeamCraft Showdown', 'subtitle' => 'LAN virtuelle • Multi-jeux', 'game' => 'VALORANT', 'gameColor' => 'valorant', 'date' => '12 Avr 2026', 'dateBadge' => 'orange', 'initial' => 'TS'],
            ['id' => 6, 'title' => 'CS2 Rank S Qualifiers', 'subtitle' => 'Qualifications ligue pro', 'game' => 'CS2', 'gameColor' => 'cs2', 'date' => '28 Fév 2026', 'dateBadge' => 'green', 'initial' => 'RS'],
        ];
        return $this->render('frontoffice/events/index.html.twig', ['events' => $events]);
    }
}
