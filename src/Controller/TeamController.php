<?php

namespace App\Controller;

use App\Entity\Offer;
use App\Entity\Team;
use App\Form\TeamType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\NotificationService;

class TeamController extends AbstractController
{
    private NotificationService $notificationService;
    private \App\Service\PremiumService $premiumService;
    private \App\Service\OfferLifecycleService $offerLifecycleService;

    public function __construct(
        NotificationService $notificationService,
        \App\Service\PremiumService $premiumService,
        \App\Service\OfferLifecycleService $offerLifecycleService
    ) {
        $this->notificationService = $notificationService;
        $this->premiumService = $premiumService;
        $this->offerLifecycleService = $offerLifecycleService;
    }

    #[Route('/teams', name: 'app_teams')]
    public function index(Request $request, \App\Repository\TeamRepository $teamRepository, \Twig\Environment $twig): Response
    {
        $user = $this->getUser();

        // Handle AJAX Search for Team Discovery
        if ($request->isXmlHttpRequest()) {
            $query = trim($request->query->get('q'));

            $teams = $teamRepository->searchByNameOrGame($query, null, null, 20, 0);

            return new Response($twig->load('frontoffice/teams/index.html.twig')->renderBlock('discovery_list', [
                'teams' => $teams
            ]));
        }

        $myTeams = [];
        if ($user) {
            $ownedTeams = $user->getTeams();
            $joinedTeams = $teamRepository->findTeamsByMember($user);

            // Merge valid requests
            foreach ($ownedTeams as $team)
                $myTeams[$team->getId()] = $team;
            foreach ($joinedTeams as $team)
                $myTeams[$team->getId()] = $team;
        }

        $allTeams = $teamRepository->findAll();

        return $this->render('frontoffice/teams/index.html.twig', [
            'myTeams' => $myTeams,
            'teams' => $allTeams,
        ]);
    }

    #[Route('/teams/create', name: 'team_create')]
    public function create(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = new Team();
        $team->setOwner($user);

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // Manual PHP Validation
            $name = $form->get('name')->getData();
            $games = $form->get('games')->getData();
            
            if (empty($name)) {
                $form->get('name')->addError(new \Symfony\Component\Form\FormError('Team name is required.'));
            }
            
            if (empty($games) || count($games) === 0) {
                 $form->get('games')->addError(new \Symfony\Component\Form\FormError('Please select at least one game.'));
            }
            
            if ($form->getErrors(true)->count() > 0) {
                return $this->render('frontoffice/teams/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Upload logo
            $logoFile = $form->get('logo')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                $logoFile->move(
                    $this->getParameter('team_logos_directory'),
                    $newFilename
                );

                $team->setLogo($newFilename);
            }

            $team->setCreatedAt(new \DateTimeImmutable());

            $em->persist($team);
            $em->flush();

            $this->addFlash('success', 'Team created successfully!');

            return $this->redirectToRoute('app_teams');
        }

        return $this->render('frontoffice/teams/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/my-teams', name: 'my_teams')]
    public function myTeams(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $teams = $user->getTeams();

        return $this->render('frontoffice/teams/my_teams.html.twig', [
            'teams' => $teams,
        ]);
    }
    #[Route('/teams/{id}/manage', name: 'team_manage')]
    public function manage(int $id, EntityManagerInterface $em, Request $request, ValidatorInterface $validator): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = $em->getRepository(Team::class)->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        // Check if user is owner or member
        $isOwner = $team->getOwner() === $user;
        $isCoOwner = $team->isCoOwner($user);
        $isMember = $team->getMembers()->contains($user);

        if (!$isOwner && !$isCoOwner && !$isMember) {
            throw $this->createAccessDeniedException('You are not a member of this team.');
        }

        // Can manage = owner or co-owner
        $canManage = $isOwner || $isCoOwner;

        // Initialize new Offer (only for owners and co-owners)
        $offer = new \App\Entity\Offer();
        $offerForm = $this->createForm(\App\Form\OfferType::class, $offer);

        // Only handle form submission if user can manage
        if ($canManage) {
            $offerForm->handleRequest($request);

            // Handle Offer Creation Form
            if ($offerForm->isSubmitted() && $offerForm->isValid()) {
                $offer->setTeam($team);
                $offer->setDateCreation(new \DateTime());

                // Calculate expiration date based on validityPeriod selection
                $validityDays = $offerForm->get('validityPeriod')->getData() ?: 15;
                $expiration = new \DateTime();
                $expiration->modify('+' . $validityDays . ' days');
                $offer->setDateExpiration($expiration);

                // Handle Poster Upload
                $posterFile = $offerForm->get('poster')->getData();
                if ($posterFile) {
                    $originalFilename = pathinfo($posterFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $originalFilename)));
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $posterFile->guessExtension();

                    try {
                        $posterFile->move(
                            $this->getParameter('kernel.project_dir') . '/public/uploads/offers',
                            $newFilename
                        );
                        $offer->setPoster($newFilename);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Error uploading poster');
                    }
                }

                $em->persist($offer);
                $em->flush();

                $this->addFlash('success', 'Offer created successfully!');
                return $this->redirectToRoute('team_manage', ['id' => $id]);
            } elseif ($offerForm->isSubmitted() && !$offerForm->isValid()) {
                foreach ($offerForm->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        // Mock Roster Data for Frontend Demo (REMOVED)
        // Real Roster Data
        $roster = $team->getMembers();

        // Map stored game codes to readable labels
        $gameLabels = [
            'valorant' => 'Valorant',
            'lol' => 'League of Legends',
            'wow' => 'World of Warcraft',
            'csgo' => 'CS:GO',
            'overwatch' => 'Overwatch',
            'fortnite' => 'Fortnite',
        ];

        $teamGames = array_map(function ($gameCode) use ($gameLabels) {
            return $gameLabels[$gameCode] ?? ucfirst($gameCode);
        }, $team->getGames() ?? []);

        // Get all places for event creation modal
        $places = $em->getRepository(\App\Entity\Place::class)->findAll();

        // Initialize new Event for modal
        $evenement = new \App\Entity\Evenement();
        $eventForm = $this->createForm(\App\Form\EvenementType::class, $evenement);
        // Calculate statistics for offers
        $offerStats = [];
        foreach ($team->getOffers() as $offer) {
            $views = $offer->getViews();
            $applications = $offer->getPostulations()->count();
            $conversionRate = $views > 0 ? round(($applications / $views) * 100, 1) : 0;
            
            $offerStats[$offer->getId()] = [
                'views' => $views,
                'applications' => $applications,
                'conversionRate' => $conversionRate
            ];
        }

        return $this->render('frontoffice/teams/manage.html.twig', [
            'team' => $team,
            'roster' => $roster,
            'offers' => $team->getOffers(),
            'teamGames' => $teamGames,
            'places' => $places,
            'offerForm' => $offerForm->createView(),
            'eventForm' => $eventForm->createView(),
            'isOwner' => $isOwner,
            'isCoOwner' => $isCoOwner,
            'canManage' => $canManage,
            'offerStats' => $offerStats
        ]);
    }

         
           



    #[Route('/team/{id}/applications', name: 'team_applications', methods: ['GET'])]
    public function applications(int $id, \App\Repository\TeamRepository $teamRepository, \App\Repository\PostulationRepository $postulationRepository): Response
    {
        $team = $teamRepository->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // Check if user is owner or member
        $isOwner = $team->getOwner() === $user;
        $isCoOwner = $team->isCoOwner($user);
        $isMember = $team->getMembers()->contains($user);

        if (!$isOwner && !$isCoOwner && !$isMember) {
            throw $this->createAccessDeniedException('You are not a member of this team.');
        }

        // Fetch all applications for offers belonging to this team
        $applications = $postulationRepository->createQueryBuilder('p')
            ->join('p.offer', 'o')
            ->where('o.team = :team')
            ->setParameter('team', $team)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Can manage = owner or co-owner
        $canManage = $isOwner || $isCoOwner;

        // Calculate Statistics
        $stats = [
            'pending' => 0,
            'accepted' => 0,
            'refused' => 0,
        ];

        foreach ($applications as $application) {
            $status = strtolower($application->getStatus());
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $this->render('frontoffice/teams/applications.html.twig', [
            'team' => $team,
            'applications' => $applications,
            'isOwner' => $isOwner,
            'canManage' => $canManage,
            'stats' => $stats
        ]);
    }

    #[Route('/team/{id}/remove-member/{userId}', name: 'team_remove_member', methods: ['POST'])]
    public function removeMember(int $id, int $userId, \App\Repository\TeamRepository $teamRepository, \App\Repository\UserRepository $userRepository, Request $request, EntityManagerInterface $em): Response
    {
        $team = $teamRepository->find($id);
        $member = $userRepository->find($userId);

        if (!$team || !$member) {
            throw $this->createNotFoundException('Team or User not found');
        }

        // Check ownership
        $user = $this->getUser();
        // Allow Owner OR Co-Owner to remove members
        $isOwner = $team->getOwner() === $user;
        $isCoOwner = $team->isCoOwner($user);

        if (!$isOwner && !$isCoOwner) {
            throw $this->createAccessDeniedException('You are not allowed to remove members.');
        }

        // Prevent removing the owner
        // Protect Owner from being removed
        if ($member === $team->getOwner()) {
            throw $this->createAccessDeniedException('Cannot remove the team owner.');
        }

        // Protect Co-Owners from being removed by other Co-Owners (Only Owner can remove Co-Owners)
        if ($team->isCoOwner($member) && !$isOwner) {
            throw $this->createAccessDeniedException('Only the Owner can remove a Co-Owner.');
        }

        if ($this->isCsrfTokenValid('remove_member_' . $member->getId(), $request->request->get('_token'))) {
            $team->removeMember($member);

            // If they were a co-owner, remove that role too
            if ($team->isCoOwner($member)) {
                $team->removeCoOwner($member);
            }

            $em->flush();
            $this->addFlash('success', 'Member removed successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('team_manage', ['id' => $id]);
    }

    #[Route('/teams/{id}/edit', name: 'team_edit')]
    public function edit(Request $request, int $id, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = $em->getRepository(Team::class)->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($team->getOwner() !== $user) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Upload logo
            $logoFile = $form->get('logo')->getData();
            if ($logoFile) {
                // Delete old logo if exists (optional, good practice)
                // if ($team->getLogo()) { ... }

                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                $logoFile->move(
                    $this->getParameter('team_logos_directory'),
                    $newFilename
                );

                $team->setLogo($newFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Team updated successfully!');

            return $this->redirectToRoute('team_manage', ['id' => $team->getId()]);
        }

        return $this->render('frontoffice/teams/edit.html.twig', [
            'form' => $form->createView(),
            'team' => $team
        ]);
    }

    #[Route('/teams/{id}/delete', name: 'team_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = $em->getRepository(Team::class)->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($team->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        if ($this->isCsrfTokenValid('delete_team_' . $team->getId(), $request->request->get('_token'))) {
            $em->remove($team);
            $em->flush();
            $this->addFlash('success', 'Team deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        if ($request->query->get('redirect') === 'admin') {
            return $this->redirectToRoute('admin_teams');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_teams');
        }

        return $this->redirectToRoute('app_teams');
    }
    #[Route('/offers/{id}/delete', name: 'offer_delete', methods: ['POST'])]
    public function deleteOffer(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $offer = $em->getRepository(\App\Entity\Offer::class)->find($id);

        if (!$offer) {
            throw $this->createNotFoundException('Offer not found');
        }

        if ($offer->getTeam()->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not authorized to delete this offer.');
        }

        if ($this->isCsrfTokenValid('delete_offer_' . $offer->getId(), $request->request->get('_token'))) {
            $em->remove($offer);
            $em->flush();
            $this->addFlash('success', 'Offer deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        if ($request->request->get('redirect') === 'admin') {
            return $this->redirectToRoute('admin_offers');
        }

        return $this->redirectToRoute('team_manage', ['id' => $offer->getTeam()->getId()]);
    }

    #[Route('/offers/{id}/edit', name: 'offer_edit')]
    public function editOffer(Request $request, int $id, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $offer = $em->getRepository(\App\Entity\Offer::class)->find($id);

        if (!$offer) {
            throw $this->createNotFoundException('Offer not found');
        }

        if ($offer->getTeam()->getOwner() !== $user) {
            throw $this->createAccessDeniedException('You are not authorized to edit this offer.');
        }

        $form = $this->createForm(\App\Form\OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update expiration date if validity period was changed
            $validityDays = $form->get('validityPeriod')->getData();
            if ($validityDays) {
                $expiration = new \DateTime();
                $expiration->modify('+' . $validityDays . ' days');
                $offer->setDateExpiration($expiration);
            }
            
            // Handle Poster Update if needed
            $posterFile = $form->get('poster')->getData();
            if ($posterFile) {
                $originalFilename = pathinfo($posterFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $posterFile->guessExtension();

                try {
                    $posterFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/offers',
                        $newFilename
                    );
                    $offer->setPoster($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error uploading poster');
                }
            }

            // Handle activation if status changed to ACTIVE
            if ($offer->getStatus() === Offer::STATUS_ACTIVE && !$offer->getActivatedAt()) {
                $offer->setActivatedAt(new \DateTime());
                $this->notificationService->createNotification(
                    $user,
                    'OFFER_PUBLISHED',
                    sprintf('Your offer "%s" is now active and visible to players.', $offer->getTitle())
                );
            }

            $em->flush();
            $this->addFlash('success', 'Offer updated successfully.');

            return $this->redirectToRoute('team_manage', ['id' => $offer->getTeam()->getId()]);
        }

        return $this->render('frontoffice/teams/edit_offer.html.twig', [
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/team/{id}/promote/{userId}', name: 'team_promote_co_owner', methods: ['POST'])]
    public function promoteToCoOwner(int $id, int $userId, \App\Repository\TeamRepository $teamRepository, \App\Repository\UserRepository $userRepository, EntityManagerInterface $em, Request $request): Response
    {
        $team = $teamRepository->find($id);
        $userToPromote = $userRepository->find($userId);

        if (!$team || !$userToPromote) {
            throw $this->createNotFoundException('Team or user not found');
        }

        // Only Owner can promote
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Only the specific owner can promote members.');
        }

        if ($this->isCsrfTokenValid('promote_co_owner_' . $userId, $request->request->get('_token'))) {
            $team->addCoOwner($userToPromote);
            $em->flush();
            $this->addFlash('success', $userToPromote->getPseudo() . ' is now a Co-Owner.');
        }

        return $this->redirectToRoute('team_manage', ['id' => $id]);
    }

    #[Route('/team/{id}/demote/{userId}', name: 'team_demote_co_owner', methods: ['POST'])]
    public function demoteFromCoOwner(int $id, int $userId, \App\Repository\TeamRepository $teamRepository, \App\Repository\UserRepository $userRepository, EntityManagerInterface $em, Request $request): Response
    {
        $team = $teamRepository->find($id);
        $userToDemote = $userRepository->find($userId);

        if (!$team || !$userToDemote) {
            throw $this->createNotFoundException('Team or user not found');
        }

        // Only Owner can demote
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Only the specific owner can demote members.');
        }

        if ($this->isCsrfTokenValid('demote_co_owner_' . $userId, $request->request->get('_token'))) {
            $team->removeCoOwner($userToDemote);
            $em->flush();
            $this->addFlash('success', $userToDemote->getPseudo() . ' is no longer a Co-Owner.');
        }

        return $this->redirectToRoute('team_manage', ['id' => $id]);
    }
    #[Route('/offers/{id}/upgrade/{type}', name: 'offer_upgrade', methods: ['POST'])]
    public function upgradeOffer(int $id, string $type, EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $offer = $em->getRepository(Offer::class)->find($id);

        if (!$offer) {
            throw $this->createNotFoundException('Offer not found');
        }

        if ($offer->getTeam()->getOwner() !== $user) {
            throw $this->createAccessDeniedException('You are not authorized to upgrade this offer.');
        }

        if (!$this->isCsrfTokenValid('upgrade_offer_' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('team_manage', ['id' => $offer->getTeam()->getId()]);
        }

        switch (strtoupper($type)) {
            case 'FEATURED':
                $this->premiumService->upgradeToFeatured($offer);
                $this->addFlash('success', 'Offer boosted to FEATURED!');
                break;
            case 'SPONSORED':
                $this->premiumService->upgradeToSponsored($offer);
                $this->addFlash('success', 'Offer boosted to SPONSORED!');
                break;
            default:
                $this->addFlash('error', 'Invalid upgrade type.');
        }

        return $this->redirectToRoute('team_manage', ['id' => $offer->getTeam()->getId()]);
    }
}
