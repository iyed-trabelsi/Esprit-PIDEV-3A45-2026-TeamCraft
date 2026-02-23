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
use App\Service\ContentModeratorService;
use App\Service\ImageModerationService;

class TeamController extends AbstractController
{
    public function __construct(
        private ContentModeratorService $moderator,
        private ImageModerationService $imageModerator
    ) {
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
            foreach($ownedTeams as $team) $myTeams[$team->getId()] = $team;
            foreach($joinedTeams as $team) $myTeams[$team->getId()] = $team;
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

        if ($team->getOwner() !== $user) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        // Initialize new Offer
        $offer = new \App\Entity\Offer();
        $offerForm = $this->createForm(\App\Form\OfferType::class, $offer);
        $offerForm->handleRequest($request);

        // Handle Offer Creation Form
        if ($offerForm->isSubmitted() && $offerForm->isValid()) {
            $offer->setTeam($team);
            $offer->setDateCreation(new \DateTime());

            // 1. Text Toxicity Check
            if ($this->moderator->isToxic($offer->getTitle()) || ($offer->getDescription() && $this->moderator->isToxic($offer->getDescription()))) {
                $this->addFlash('warning', '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Votre offre contient des propos offensants. Veuillez modifier le message.');
                return $this->render('frontoffice/teams/manage.html.twig', [
                    'team' => $team,
                    'roster' => $team->getMembers(),
                    'offers' => $team->getOffers(),
                    'teamGames' => array_map(fn($g) => $g, $team->getGames() ?? []),
                    'places' => $em->getRepository(\App\Entity\Place::class)->findAll(),
                    'offerForm' => $offerForm->createView()
                ]);
            }

            // 2. Poster Upload & Moderation
            $posterFile = $offerForm->get('poster')->getData();
            if ($posterFile) {
                $originalFilename = pathinfo($posterFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $originalFilename)));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $posterFile->guessExtension();

                try {
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/offers';
                    $posterFile->move($uploadDir, $newFilename);
                    
                    $absolutePath = realpath($uploadDir . \DIRECTORY_SEPARATOR . $newFilename) ?: $uploadDir . \DIRECTORY_SEPARATOR . $newFilename;
                    $result = $this->imageModerator->analyzeImage($absolutePath);

                    if ($result['status'] === 'reject') {
                        @unlink($absolutePath);
                        $this->addFlash('warning', $result['message'] ?? 'Cette image n\'est pas autorisée.');
                         return $this->render('frontoffice/teams/manage.html.twig', [
                            'team' => $team,
                            'roster' => $team->getMembers(),
                            'offers' => $team->getOffers(),
                            'teamGames' => array_map(fn($g) => $g, $team->getGames() ?? []),
                            'places' => $em->getRepository(\App\Entity\Place::class)->findAll(),
                            'offerForm' => $offerForm->createView()
                        ]);
                    } else {
                        $offer->setPoster($newFilename);
                        // We reuse imageSensitivity logic if applicable, but for offers we just follow reject/blur
                        if ($result['status'] === 'pending_review') {
                            $this->addFlash('notice', $result['message'] ?? 'L\'image sera floutée par défaut.');
                        }
                        if (!empty($result['warning_message'])) {
                            $this->addFlash('warning', $result['warning_message']);
                        }
                    }
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

        return $this->render('frontoffice/teams/manage.html.twig', [
            'team' => $team,
            'roster' => $roster,
            'offers' => $team->getOffers(),
            'teamGames' => $teamGames,
            'places' => $places,
            'offerForm' => $offerForm->createView(),
            'eventForm' => $eventForm->createView(),
        ]);
    }

         

    #[Route('/team/{id}/applications', name: 'team_applications', methods: ['GET'])]
    public function applications(int $id, \App\Repository\TeamRepository $teamRepository, \App\Repository\PostulationRepository $postulationRepository): Response
    {
        $team = $teamRepository->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        // Check ownership
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        // Fetch all applications for offers belonging to this team
        $applications = $postulationRepository->createQueryBuilder('p')
            ->join('p.offer', 'o')
            ->where('o.team = :team')
            ->setParameter('team', $team)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('frontoffice/teams/applications.html.twig', [
            'team' => $team,
            'applications' => $applications
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
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        // Prevent removing the owner
        if ($member === $team->getOwner()) {
            $this->addFlash('error', 'You cannot remove the owner from the team.');
            return $this->redirectToRoute('team_manage', ['id' => $id]);
        }

        if ($this->isCsrfTokenValid('remove_member_' . $member->getId(), $request->request->get('_token'))) {
            $team->removeMember($member);
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

        if ($form->isSubmitted()) {
            
             // Manual PHP Validation
            $name = $form->get('name')->getData();
            $games = $form->get('games')->getData();
            
            if (empty($name)) {
                $form->get('name')->addError(new \Symfony\Component\Form\FormError('Le nom de l\'équipe est obligatoire.'));
            } elseif (strlen($name) < 3) {
                $form->get('name')->addError(new \Symfony\Component\Form\FormError('Le nom de l\'équipe doit contenir au moins 3 caractères.'));
            }
            
            if (empty($games) || count($games) === 0) {
                 $form->get('games')->addError(new \Symfony\Component\Form\FormError('Veuillez sélectionner au moins un jeu.'));
            }
            
            // Only proceed if form is valid AND no manual validation errors
            if ($form->isValid() && $form->getErrors(true)->count() === 0) {
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
            // 1. Text Toxicity Check
            if ($this->moderator->isToxic($offer->getTitle()) || ($offer->getDescription() && $this->moderator->isToxic($offer->getDescription()))) {
                $this->addFlash('warning', '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Votre offre contient des propos offensants. Veuillez modifier le message.');
                return $this->render('frontoffice/teams/edit_offer.html.twig', [
                    'offer' => $offer,
                    'form' => $form->createView(),
                ]);
            }

            // 2. Poster Update & Moderation
            $posterFile = $form->get('poster')->getData();
            if ($posterFile) {
                $originalFilename = pathinfo($posterFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $posterFile->guessExtension();

                try {
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/offers';
                    $posterFile->move($uploadDir, $newFilename);
                    
                    $absolutePath = realpath($uploadDir . \DIRECTORY_SEPARATOR . $newFilename) ?: $uploadDir . \DIRECTORY_SEPARATOR . $newFilename;
                    $result = $this->imageModerator->analyzeImage($absolutePath);

                    if ($result['status'] === 'reject') {
                        @unlink($absolutePath);
                        $this->addFlash('warning', $result['message'] ?? 'Cette image n\'est pas autorisée.');
                        return $this->render('frontoffice/teams/edit_offer.html.twig', [
                            'offer' => $offer,
                            'form' => $form->createView(),
                        ]);
                    } else {
                        $offer->setPoster($newFilename);
                        if ($result['status'] === 'pending_review') {
                            $this->addFlash('notice', $result['message'] ?? 'L\'image sera floutée par défaut.');
                        }
                        if (!empty($result['warning_message'])) {
                            $this->addFlash('warning', $result['warning_message']);
                        }
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error uploading poster');
                }
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
}
