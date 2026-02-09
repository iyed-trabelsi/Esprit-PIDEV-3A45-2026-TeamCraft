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

    #[Route('/forum', name: 'app_forum', methods: ['GET'])]
    public function forum(): Response
    {
        $posts = $this->getForumPosts();
        return $this->render('frontoffice/forum/index.html.twig', ['posts' => $posts]);
    }

    #[Route('/forum/{id}', name: 'app_forum_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function forumShow(int $id): Response
    {
        $posts = $this->getForumPosts();
        $post = null;
        foreach ($posts as $p) {
            if ((int) $p['id'] === $id) {
                $post = $p;
                break;
            }
        }
        if (!$post) {
            throw $this->createNotFoundException('Post not found.');
        }
        $comments = $this->getForumCommentsForPost($id);
        return $this->render('frontoffice/forum/show.html.twig', [
            'post' => $post,
            'comments' => $comments,
        ]);
    }

    private function getForumPosts(): array
    {
        return [
            ['id' => 1, 'title' => 'general discussion', 'subtitle' => 'chat', 'game' => 'VALORANT', 'gameColor' => 'valorant', 'rank' => 'DIAMOND 3', 'rankColor' => 'purple', 'body' => 'Welcome to the general discussion thread. Feel free to share your thoughts, ask questions, or chat with the community about anything related to esports and recruiting.'],
            ['id' => 2, 'title' => 'Actuality', 'subtitle' => 'Support / Healer', 'game' => 'OVERWATCH', 'gameColor' => 'overwatch', 'rank' => 'MASTER', 'rankColor' => 'yellow', 'body' => 'Latest news and updates from the competitive Overwatch scene. Discuss meta shifts, patch notes, and team roster changes here.'],
            ['id' => 3, 'title' => 'GhostOp', 'subtitle' => 'Jungler', 'game' => 'LOL', 'gameColor' => 'lol', 'rank' => 'DIAMOND 1', 'rankColor' => 'purple', 'body' => 'Looking for scrim partners and jungle pathing tips. Main Elise, Graves, and Vi. Open to coaching and VOD reviews.'],
            ['id' => 4, 'title' => 'StratKing', 'subtitle' => 'IGL / Captain', 'game' => 'CS2', 'gameColor' => 'cs2', 'rank' => 'GLOBAL', 'rankColor' => 'green', 'body' => 'Experienced IGL looking to share strats and discuss in-game leadership. Post your best executes and we can break them down.'],
            ['id' => 5, 'title' => 'LF Team', 'subtitle' => 'Duelist main', 'game' => 'VALORANT', 'gameColor' => 'valorant', 'rank' => 'RADIANT', 'rankColor' => 'orange', 'body' => 'Radiant duelist (Jett, Raze) looking for a serious team for upcoming tournaments. Available for tryouts most evenings.'],
            ['id' => 6, 'title' => 'Scrims', 'subtitle' => 'Weekend only', 'game' => 'CS2', 'gameColor' => 'cs2', 'rank' => 'GLOBAL', 'rankColor' => 'green', 'body' => 'Organizing weekend scrim blocks. Comment if your team is interested. We run BO3 format, EU servers.'],
        ];
    }

    private function getForumCommentsForPost(int $postId): array
    {
        $all = [
            1 => [
                ['author' => 'ViperStrike', 'date' => '2 days ago', 'text' => 'Great thread! Anyone else struggling with the new meta?'],
                ['author' => 'Kira.exe', 'date' => '1 day ago', 'text' => 'Yeah the last patch changed a lot. Happy to run some games if anyone wants to practice.'],
                ['author' => 'GhostOp', 'date' => '12 hours ago', 'text' => 'Down for scrims this weekend. Add me in-game.'],
            ],
            2 => [
                ['author' => 'StratKing', 'date' => '3 days ago', 'text' => 'Support meta is so strong right now. Mercy and Lucio everywhere.'],
                ['author' => 'NovaBlade', 'date' => '2 days ago', 'text' => 'Agreed. What do you think about the upcoming balance changes?'],
            ],
            3 => [
                ['author' => 'ShadowAim', 'date' => '1 day ago', 'text' => 'Elise is broken in the right hands. What runes are you running?'],
                ['author' => 'GhostOp', 'date' => '1 day ago', 'text' => 'Conqueror with inspiration second. I can share my op.gg if you want.'],
            ],
            4 => [
                ['author' => 'PhoenixSquad', 'date' => '5 days ago', 'text' => 'We need more IGL content on this forum. Thanks for posting.'],
                ['author' => 'StormGaming', 'date' => '4 days ago', 'text' => 'Our team could use some of these strats. When is the next session?'],
            ],
            5 => [
                ['author' => 'TeamAlpha', 'date' => '1 day ago', 'text' => 'We are looking for a duelist. Can you send your tracker link?'],
                ['author' => 'RadiantOne', 'date' => '10 hours ago', 'text' => 'What region are you in? We might have a spot.'],
            ],
            6 => [
                ['author' => 'EU_ScrimHub', 'date' => '2 days ago', 'text' => 'We are in. Our team is free Saturday afternoon.'],
                ['author' => 'CS2_Pro', 'date' => '1 day ago', 'text' => 'Same here. Let\'s set it up in the Discord.'],
            ],
        ];
        return $all[$postId] ?? [];
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
