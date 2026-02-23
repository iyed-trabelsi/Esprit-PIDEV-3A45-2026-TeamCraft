<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Comment;
use App\Entity\Player;
use App\Entity\Post;
use App\Entity\Rubrique;
use App\Repository\AdminRepository;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\RubriqueRepository;
use App\Repository\PlayerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Repository\TeamRepository;
use App\Repository\OfferRepository;

class AdminController extends AbstractController
{
   #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        Request $request, 
        RubriqueRepository $rubriqueRepository,
        UserRepository $userRepo, 
        PostRepository $postRepository, 
        CommentRepository $commentRepository,
        PlayerRepository $playerRepo,      // Ajouté
        TeamRepository $teamRepo,        // Ajouté
        OfferRepository $offerRepo,      // Ajouté
        \Twig\Environment $twig
    ): Response {
        
        // STATS RÉELLES DEPUIS LA BDD
        $stats = [
            'totalUsers' => $userRepo->countAllUsers(),
            'totalPlayers' => $playerRepo->count([]), // Compte réel de la table Player
            'activeUsers' => $userRepo->countActiveUsers(true),
            'inactiveUsers' => $userRepo->countActiveUsers(false),
            'bannedUsers' => $userRepo->countBannedUsers(),
            'genderDistribution' => $userRepo->countByGender(),
        ];
        
        $limit = 5;

        // RÉCUPÉRATION DES DONNÉES RÉELLES
        $players = $playerRepo->findAll(); 
        $teams = $teamRepo->findAll();
        $offers = $offerRepo->findAll();
        
        // Si vous n'avez pas encore d'entité Event en BDD, on garde le statique
        $events = $this->getEvents(); 

        // --- Gestion Pagination Rubriques ---
        $pageRubriques = max(1, $request->query->getInt('page_rubriques', 1));
        $allRubriques = $rubriqueRepository->findBy([], ['dateCreation' => 'DESC']);
        $totalRubriques = count($allRubriques);
        $maxPagesRubriques = (int) ceil($totalRubriques / $limit) ?: 1;
        $rubriques = array_slice($allRubriques, ($pageRubriques - 1) * $limit, $limit);

        // --- Gestion Pagination Posts ---
        $pagePosts = max(1, $request->query->getInt('page_posts', 1));
        $allPosts = $postRepository->findBy([], ['dateCreation' => 'DESC']);
        $totalPosts = count($allPosts);
        $maxPagesPosts = (int) ceil($totalPosts / $limit) ?: 1;
        $posts = array_slice($allPosts, ($pagePosts - 1) * $limit, $limit);

        // --- Gestion Pagination Commentaires ---
        $pageComments = max(1, $request->query->getInt('page_comments', 1));
        $allComments = $commentRepository->findAllForAdmin();
        $totalComments = count($allComments);
        $maxPagesComments = (int) ceil($totalComments / $limit) ?: 1;
        $comments = array_slice($allComments, ($pageComments - 1) * $limit, $limit);

        return $this->render('backoffice/dashboard.html.twig', [
            'userStats' => $stats,
            'players' => $players,
            'teams' => $teams,
            'offers' => $offers,
            'events' => $events,
            'rubriques' => $rubriques,
            'rubriquesCurrentPage' => $pageRubriques,
            'rubriquesMaxPages' => $maxPagesRubriques,
            'rubriquesTotal' => $totalRubriques,
            'posts' => $posts,
            'postsCurrentPage' => $pagePosts,
            'postsMaxPages' => $maxPagesPosts,
            'postsTotal' => $totalPosts,
            'comments' => $comments,
            'commentsCurrentPage' => $pageComments,
            'commentsMaxPages' => $maxPagesComments,
            'commentsTotal' => $totalComments,
        ]);
    }

    #[Route('/admin/players', name: 'admin_players', methods: ['GET'])]
    public function players(Request $request, PlayerRepository $playerRepository): Response
    {
        $limit = 5;
        $page = max(1, $request->query->getInt('page', 1));
        $q = $request->query->get('q');
        $sort = $request->query->get('sort');

        // On passe $q ET $sort pour que le compte total soit correct avec les filtres
        $totalPlayers = $playerRepository->countForAdmin($q, $sort);

        $maxPages = (int) ceil($totalPlayers / $limit) ?: 1;

        if ($page > $maxPages) {
            $page = $maxPages;
        }

        $offset = ($page - 1) * $limit;
        $players = $playerRepository->findForAdmin($q, $sort, $limit, $offset);

        return $this->render('backoffice/players.html.twig', [
            'players' => $players,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalPlayers' => $totalPlayers,
            'q' => $q,
            'sort' => $sort // Virgule ajoutée ici
        ]);
    }

#[Route('/admin/players/save', name: 'admin_player_save', methods: ['POST'])]
public function savePlayer(Request $request, EntityManagerInterface $em, PlayerRepository $playerRepository, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): Response
{
    $id = $request->request->get('id');
    $pseudo = $request->request->get('pseudo');
    $status = $request->request->get('status') ?? 'Active'; // Récupère Active, Inactive ou Banned

    if ($id) {
        $player = $playerRepository->find($id);
        if (!$player) {
            throw $this->createNotFoundException('Joueur introuvable');
        }
        $user = $player->getUser();
    } else {
        $user = $userRepository->findOneBy(['pseudo' => $pseudo]);
        if (!$user) {
            $user = new \App\Entity\User();
            $user->setPseudo($pseudo);
            $user->setUsername($pseudo);
            $user->setName($pseudo);
            $user->setEmail(uniqid('player_') . '@example.com');
            $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
            $user->setRoles(['ROLE_USER']);
            $user->setUserType('player');
            $em->persist($user);
        }
        $player = new Player();
        $player->setUser($user);
    }

    // --- LA CORRECTION EST ICI ---
    // On met à jour le statut du Joueur
    $player->setStatus($status);

    // On synchronise avec l'entité User pour que les stats du dashboard soient correctes
    // Si le statut est 'Active', isActive = true. Sinon (Inactive ou Banned), isActive = false.
    if ($user) {
        $user->setIsActive($status === 'Active');
        $em->persist($user);
    }
    // -----------------------------

    $player->setGame($request->request->get('game'));
    $player->setGameRank($request->request->get('rank'));
    $player->setRole($request->request->get('role'));
    $player->setRegion($request->request->get('region'));

    $em->persist($player);
    $em->flush();

    $this->addFlash('success', 'Joueur et compte utilisateur mis à jour avec succès.');
    return $this->redirectToRoute('admin_players');
}

    #[Route('/admin/players/{id}/delete', name: 'admin_player_delete', methods: ['POST'])]
    public function deletePlayer(Player $player, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $player->getId(), $request->request->get('_token'))) {
            $em->remove($player);
            $em->flush();
            $this->addFlash('success', 'Joueur supprimé avec succès.');
        }
        return $this->redirectToRoute('admin_players');
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

        if ($request->isXmlHttpRequest() || $request->query->get('ajax')) {
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

    #[Route('/admin/offers/{id}/applications', name: 'admin_offer_applications', methods: ['GET'])]
    public function offerApplications(int $id, \App\Repository\OfferRepository $offerRepository): Response
    {
        $offer = $offerRepository->find($id);
        if (!$offer) {
            throw $this->createNotFoundException('Offre non trouvée.');
        }

        return $this->render('backoffice/offer_applications.html.twig', [
            'offer' => $offer,
            'applications' => $offer->getPostulations()
        ]);
    }

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

    #[Route('/admin/rubriques', name: 'admin_rubriques', methods: ['GET'])]
    public function rubriques(Request $request, RubriqueRepository $rubriqueRepository, \Twig\Environment $twig): Response
    {
        $limit = 5;
        $page = max(1, $request->query->getInt('page', 1));
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'date_desc');

        $total = $rubriqueRepository->countForAdmin($q);
        $maxPages = (int) ceil($total / $limit) ?: 1;
        if ($page > $maxPages) {
            $page = $maxPages;
        }
        $offset = ($page - 1) * $limit;
        $rubriques = $rubriqueRepository->findForAdmin($q, $sort, $limit, $offset);

        if ($request->isXmlHttpRequest()) {
            return new Response($twig->load('backoffice/rubriques.html.twig')->renderBlock('rubriques_list', [
                'rubriques' => $rubriques,
                'currentPage' => $page,
                'maxPages' => $maxPages,
                'total' => $total,
                'q' => $q,
                'sort' => $sort,
            ]));
        }

        return $this->render('backoffice/rubriques.html.twig', [
            'rubriques' => $rubriques,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'total' => $total,
            'q' => $q,
            'sort' => $sort,
        ]);
    }

    #[Route('/admin/rubriques/{id}/etat', name: 'admin_rubrique_etat', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rubriqueEtat(int $id, Request $request, RubriqueRepository $rubriqueRepository, EntityManagerInterface $em): Response
    {
        $rubrique = $rubriqueRepository->find($id);
        $etat = $request->request->get('etat');
        if ($rubrique && \in_array($etat, ['active', 'archived'], true) && $this->isCsrfTokenValid('admin_rubrique_etat_' . $id, (string) $request->request->get('_token'))) {
            $rubrique->setEtat($etat);
            $em->flush();
            $this->addFlash('success', 'État de la rubrique mis à jour.');
        }
        $page = max(1, (int) $request->request->get('page', $request->query->get('page', 1)));
        $params = ['page' => $page];
        if ($request->request->get('q') !== null && $request->request->get('q') !== '') {
            $params['q'] = $request->request->get('q');
        }
        if ($request->request->get('sort') !== null && $request->request->get('sort') !== '') {
            $params['sort'] = $request->request->get('sort');
        }
        return $this->redirectToRoute('admin_rubriques', $params);
    }

    #[Route('/admin/rubriques/{id}/delete', name: 'admin_rubrique_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rubriqueDelete(int $id, Request $request, RubriqueRepository $rubriqueRepository, EntityManagerInterface $em): Response
    {
        $rubrique = $rubriqueRepository->find($id);
        if ($rubrique && $this->isCsrfTokenValid('admin_rubrique_delete_' . $id, (string) $request->request->get('_token'))) {
            $em->remove($rubrique);
            $em->flush();
            $this->addFlash('success', 'Rubrique supprimée.');
        }
        return $this->redirectToRoute('admin_rubriques');
    }

    #[Route('/admin/posts', name: 'admin_posts', methods: ['GET'])]
    public function posts(Request $request, PostRepository $postRepository, \Twig\Environment $twig): Response
    {
        $limit = 5;
        $page = max(1, $request->query->getInt('page', 1));
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'date_desc');

        $total = $postRepository->countForAdmin($q);
        $maxPages = (int) ceil($total / $limit) ?: 1;
        if ($page > $maxPages) {
            $page = $maxPages;
        }
        $offset = ($page - 1) * $limit;
        $posts = $postRepository->findForAdmin($q, $sort, $limit, $offset);

        if ($request->isXmlHttpRequest()) {
            return new Response($twig->load('backoffice/posts.html.twig')->renderBlock('posts_list', [
                'posts' => $posts,
                'currentPage' => $page,
                'maxPages' => $maxPages,
                'total' => $total,
                'q' => $q,
                'sort' => $sort,
            ]));
        }

        return $this->render('backoffice/posts.html.twig', [
            'posts' => $posts,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'total' => $total,
            'q' => $q,
            'sort' => $sort,
        ]);
    }

    #[Route('/admin/posts/{id}/delete', name: 'admin_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postDelete(int $id, Request $request, PostRepository $postRepository, EntityManagerInterface $em): Response
    {
        $post = $postRepository->find($id);
        if ($post && $this->isCsrfTokenValid('admin_post_delete_' . $id, (string) $request->request->get('_token'))) {
            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Post supprimé.');
        }
        return $this->redirectToRoute('admin_posts');
    }

    #[Route('/admin/posts/{id}/statut', name: 'admin_post_statut', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postStatut(int $id, Request $request, PostRepository $postRepository, EntityManagerInterface $em): Response
    {
        $post = $postRepository->find($id);
        $statut = $request->request->get('statut');
        if ($post && \in_array($statut, ['published', 'archived'], true) && $this->isCsrfTokenValid('admin_post_statut_' . $id, (string) $request->request->get('_token'))) {
            $post->setStatut($statut);
            $em->flush();
            $this->addFlash('success', 'Statut du post mis à jour.');
        }
        $page = max(1, (int) $request->request->get('page', $request->query->get('page', 1)));
        $params = ['page' => $page];
        $q = $request->request->get('q');
        $sort = $request->request->get('sort');
        if ($q !== null && $q !== '') {
            $params['q'] = $q;
        }
        if ($sort !== null && $sort !== '') {
            $params['sort'] = $sort;
        }
        return $this->redirectToRoute('admin_posts', $params);
    }

    #[Route('/admin/comments', name: 'admin_comments', methods: ['GET'])]
    public function comments(Request $request, CommentRepository $commentRepository, \Twig\Environment $twig): Response
    {
        $limit = 5;
        $page = max(1, $request->query->getInt('page', 1));
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'date_desc');

        $total = $commentRepository->countForAdmin($q);
        $maxPages = (int) ceil($total / $limit) ?: 1;
        if ($page > $maxPages) {
            $page = $maxPages;
        }
        $offset = ($page - 1) * $limit;
        $comments = $commentRepository->findForAdminPaginated($q, $sort, $limit, $offset);

        if ($request->isXmlHttpRequest()) {
            return new Response($twig->load('backoffice/comments.html.twig')->renderBlock('comments_list', [
                'comments' => $comments,
                'currentPage' => $page,
                'maxPages' => $maxPages,
                'total' => $total,
                'q' => $q,
                'sort' => $sort,
            ]));
        }

        return $this->render('backoffice/comments.html.twig', [
            'comments' => $comments,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'total' => $total,
            'q' => $q,
            'sort' => $sort,
        ]);
    }

    #[Route('/admin/comments/{id}/delete', name: 'admin_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function commentDelete(int $id, Request $request, CommentRepository $commentRepository, EntityManagerInterface $em): Response
    {
        $comment = $commentRepository->find($id);
        if ($comment && $this->isCsrfTokenValid('admin_comment_delete_' . $id, (string) $request->request->get('_token'))) {
            $em->remove($comment);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }
        return $this->redirectToRoute('admin_comments');
    }

    #[Route('/admin/admins', name: 'admin_admins', methods: ['GET'])]
    public function admins(AdminRepository $adminRepository): Response
    {
        $admins = $adminRepository->findBy([], ['id' => 'ASC']);
        return $this->render('backoffice/admins.html.twig', [
            'admins' => $admins,
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
