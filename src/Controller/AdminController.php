<?php

namespace App\Controller;
use App\Entity\User;
use App\Entity\Player;
use App\Entity\Post;
use App\Entity\Rubrique;
use App\Entity\Comment;
use App\Repository\AdminRepository;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\RubriqueRepository;
use App\Repository\PlayerRepository;
use App\Repository\UserRepository;
use App\Repository\TeamRepository;
use App\Repository\OfferRepository;
use App\Repository\PostulationRepository;
use App\Repository\SignalementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AdminController extends AbstractController
{
#[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        Request $request, 
        RubriqueRepository $rubriqueRepository,
        UserRepository $userRepo, 
        PostRepository $postRepository, 
        CommentRepository $commentRepository,
        PlayerRepository $playerRepo,
        TeamRepository $teamRepo,
        OfferRepository $offerRepo
    ): Response {
        
        // 1. STATS RÉELLES
        $stats = [
            'totalUsers' => $userRepo->countAllUsers(),
            'totalPlayers' => $playerRepo->count([]),
            'activeUsers' => $userRepo->countActiveUsers(true),
            'inactiveUsers' => $userRepo->countActiveUsers(false),
            'bannedUsers' => $userRepo->countBannedUsers(),
            'genderDistribution' => $userRepo->countByGender(),
        ];
        
        $limit = 5;

        // 2. PAGINATION RUBRIQUES
        $pageRubriques = max(1, $request->query->getInt('page_rubriques', 1));
        $allRubriques = $rubriqueRepository->findBy([], ['dateCreation' => 'DESC']);
        $totalRubriques = count($allRubriques);
        $maxPagesRubriques = (int) ceil($totalRubriques / $limit) ?: 1;
        $rubriques = array_slice($allRubriques, ($pageRubriques - 1) * $limit, $limit);

        // 3. PAGINATION POSTS
        $pagePosts = max(1, $request->query->getInt('page_posts', 1));
        $allPosts = $postRepository->findBy([], ['dateCreation' => 'DESC']);
        $totalPosts = count($allPosts);
        $maxPagesPosts = (int) ceil($totalPosts / $limit) ?: 1;
        $posts = array_slice($allPosts, ($pagePosts - 1) * $limit, $limit);

        // 4. PAGINATION COMMENTAIRES
        $pageComments = max(1, $request->query->getInt('page_comments', 1));
        $allComments = $commentRepository->findAllForAdmin();
        $totalComments = count($allComments);
        $maxPagesComments = (int) ceil($totalComments / $limit) ?: 1;
        $comments = array_slice($allComments, ($pageComments - 1) * $limit, $limit);
        
        return $this->render('backoffice/dashboard.html.twig', [
            'userStats' => $stats,
            'players' => $playerRepo->findAll(),
            'teams' => $teamRepo->findAll(),
            'offers' => $offerRepo->findAll(),
            'events' => $this->getEvents(), // Méthode privée en bas
            'rubriques' => $rubriques,
            'rubriquesCurrentPage' => $pageRubriques,
            'rubriquesMaxPages' => $maxPagesRubriques,
            'posts' => $posts,
            'postsCurrentPage' => $pagePosts,
            'postsMaxPages' => $maxPagesPosts,
            'comments' => $comments,
            'commentsCurrentPage' => $pageComments,
            'commentsMaxPages' => $maxPagesComments,
            'rubriquesByTopic' => $rubriqueRepository->countByTopic(),
            'postsByType' => $postRepository->countByType(),
        ]);
    }

    #[Route('/admin/user/{id}/block', name: 'admin_user_block', methods: ['POST'])]
    public function blockUser(User $user, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $user->setIsActive(false); 
        $em->flush();

        $email = (new Email())
            ->from('security@teamcraft.com')
            ->to($user->getEmail())
            ->subject('Alerte de sécurité : Compte TeamCraft suspendu')
            ->html("<h2>Bonjour " . $user->getPseudo() . "</h2><p>Votre compte a été suspendu.</p>");

        $mailer->send($email);
        $this->addFlash('success', 'Utilisateur bloqué et email envoyé.');

        return $this->redirectToRoute('admin_players');
    }

    #[Route('/admin/players', name: 'admin_players', methods: ['GET'])]
    public function players(Request $request, PlayerRepository $playerRepository): Response
    {
        $limit = 5;
        $page = max(1, $request->query->getInt('page', 1));
        $q = $request->query->get('q');
        $sort = $request->query->get('sort');

        $totalPlayers = $playerRepository->countForAdmin($q, $sort);
        $maxPages = (int) ceil($totalPlayers / $limit) ?: 1;
        $offset = ($page - 1) * $limit;
        $players = $playerRepository->findForAdmin($q, $sort, $limit, $offset);

        return $this->render('backoffice/players.html.twig', [
            'players' => $players,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalPlayers' => $totalPlayers,
            'q' => $q,
            'sort' => $sort
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
    public function offers(\Symfony\Component\HttpFoundation\Request $request, \App\Repository\OfferRepository $offerRepository, \App\Repository\PostulationRepository $postulationRepository): Response
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

        // Calculate statistics for all applications
        $allApplications = $postulationRepository->findAll();
        $stats = [
            'accepted' => 0,
            'refused' => 0,
            'pending' => 0,
            'total' => count($allApplications)
        ];

        foreach ($allApplications as $application) {
            $status = strtolower($application->getStatus() ?? 'pending');
            if ($status === 'accepted') {
                $stats['accepted']++;
            } elseif ($status === 'refused') {
                $stats['refused']++;
            } else {
                $stats['pending']++;
            }
        }

        return $this->render('backoffice/offers.html.twig', [
            'offers' => $paginatedOffers,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'totalOffers' => $totalOffers,
            'stats' => $stats
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


        $rubriquesByTopic = $rubriqueRepository->countByTopic();
        $rubriquesByState = $rubriqueRepository->countByState();

        return $this->render('backoffice/rubriques.html.twig', [
            'rubriques' => $rubriques,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'total' => $total,
            'q' => $q,
            'sort' => $sort,
            'rubriquesByTopic' => $rubriquesByTopic,
            'rubriquesByState' => $rubriquesByState
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
    public function posts(Request $request, PostRepository $postRepository, \App\Repository\SignalementRepository $signalementRepository, \Twig\Environment $twig): Response
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

        // Fetch reports (could be paginated too, but for now list all or recent)
        // Let's fetch pending reports primarily, or all.
        // User asked for a table.
        $reports = $signalementRepository->findBy(['status' => 'pending'], ['dateSignalement' => 'DESC']);

        // Statistiques par type de post
        $postsByType = $postRepository->countByType($q);

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
            'reports' => $reports,
            'postsByType' => $postsByType,
        ]);
    }

    #[Route('/admin/report/{id}/dismiss', name: 'admin_report_dismiss', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dismissReport(int $id, Request $request, \App\Repository\SignalementRepository $signalementRepository, EntityManagerInterface $em): Response
    {
        $report = $signalementRepository->find($id);
        if ($report && $this->isCsrfTokenValid('dismiss_report_' . $id, (string) $request->request->get('_token'))) {
            $report->setStatus('dismissed');
            $em->flush();
            $this->addFlash('success', 'Signalement ignoré/traité.');
        }
        return $this->redirectToRoute('admin_posts');
    }

    #[Route('/admin/user/{id}/ban', name: 'admin_user_ban', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function userBan(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);
        
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        if ($user && $user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre statut.');
            return $this->redirectToRoute('admin_posts');
        }

        if ($user && $this->isCsrfTokenValid('ban_user_' . $id, (string) $request->request->get('_token'))) {
            $isBanned = $user->isBanned();
            $user->setIsBanned(!$isBanned);
            $em->flush();

            $message = $user->isBanned() ? 'Utilisateur banni.' : 'Bannissement levé.';
            $this->addFlash('success', $message);
        }
        return $this->redirectToRoute('admin_posts');
    }

    #[Route('/admin/posts/{id}/delete-image', name: 'admin_post_delete_image', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postDeleteImage(int $id, Request $request, PostRepository $postRepository, EntityManagerInterface $em): Response
    {
        $post = $postRepository->find($id);
        if ($post && $this->isCsrfTokenValid('admin_post_delete_image_' . $id, (string) $request->request->get('_token'))) {
            $imagePath = $this->getParameter('uploads_post_dir') . '/' . $post->getImage();
            if ($post->getImage() && file_exists($imagePath)) {
                unlink($imagePath);
            }
            $post->setImage(null);
            $post->setImageSensitivity(null);
            $em->flush();
            $this->addFlash('success', 'Image du post supprimée.');
        }
        return $this->redirectToRoute('admin_posts');
    }

    #[Route('/admin/posts/{id}/delete', name: 'admin_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postDelete(int $id, Request $request, PostRepository $postRepository, EntityManagerInterface $em): Response
    {
        $post = $postRepository->find($id);
        if ($post && $this->isCsrfTokenValid('admin_post_delete_' . $id, (string) $request->request->get('_token'))) {
            $rubrique = $post->getRubrique();
            if ($rubrique) {
                $rubrique->setNbPosts(max(0, $rubrique->getNbPosts() - 1));
            }
            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Post supprimé et compteur mis à jour.');
        }
        return $this->redirectToRoute('admin_posts');
    }

    #[Route('/admin/posts/{id}/statut', name: 'admin_post_statut', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postStatut(int $id, Request $request, PostRepository $postRepository, EntityManagerInterface $em): Response
    {
        $post = $postRepository->find($id);
        $statut = $request->request->get('statut');
        if ($post && \in_array($statut, ['published', 'archived', 'pending_review'], true) && $this->isCsrfTokenValid('admin_post_statut_' . $id, (string) $request->request->get('_token'))) {
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

    #[Route('/admin/rubriques/sync-counts', name: 'admin_rubriques_sync_counts', methods: ['POST'])]
    public function syncCounts(\App\Repository\RubriqueRepository $rubriqueRepository, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('sync_counts', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('admin_rubriques');
        }

        $rubriques = $rubriqueRepository->findAll();
        foreach ($rubriques as $rubrique) {
            $count = count($rubrique->getPosts());
            $rubrique->setNbPosts($count);
        }
        $em->flush();

        $this->addFlash('success', 'Les compteurs de posts ont été synchronisés pour toutes les rubriques.');
        return $this->redirectToRoute('admin_rubriques');
    }
}
